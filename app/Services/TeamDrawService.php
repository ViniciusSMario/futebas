<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTeam;
use App\Models\PlayerProfile;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Splits a game's confirmed participants into teams.
 *
 * A pelada is decided before kickoff if the draw is careless: put both
 * good players on one side, or both goalkeepers, and the match is over
 * before it starts. So the default mode balances, and pure chance is an
 * option the organizer opts into rather than the only behaviour.
 */
class TeamDrawService
{
    /** Even out goalkeepers and total strength across the teams. */
    public const MODE_BALANCED = 'balanced';

    /** Straight shuffle — some peladas want the luck of the draw. */
    public const MODE_RANDOM = 'random';

    public const MODES = [self::MODE_BALANCED, self::MODE_RANDOM];

    /**
     * How many swap passes the improvement step is allowed. Peladas run
     * 8-30 players, where this converges in a handful of passes; the cap
     * only exists so a pathological set can't spin.
     */
    private const MAX_REBALANCE_PASSES = 50;

    /**
     * Draw (or redraw) teams, replacing any previous draw entirely.
     *
     * @return EloquentCollection<int, GameTeam>
     */
    public function draw(Game $game, int $teamsCount, string $mode = self::MODE_BALANCED): EloquentCollection
    {
        $participants = $game->gamePlayers()
            ->with('user.playerProfile')
            ->where('status', GamePlayer::STATUS_CONFIRMED)
            ->get();

        $squads = $mode === self::MODE_RANDOM
            ? $this->splitAtRandom($participants, $teamsCount)
            : $this->splitBalanced($participants, $teamsCount);

        return DB::transaction(function () use ($game, $teamsCount, $squads) {
            GamePlayer::where('game_id', $game->id)->update(['game_team_id' => null]);
            GameTeam::where('game_id', $game->id)->delete();

            $teams = collect(range(1, $teamsCount))->map(fn (int $number) => GameTeam::create([
                'game_id' => $game->id,
                'name' => __('Time :number', ['number' => $number]),
            ]));

            foreach ($squads as $index => $squad) {
                if ($squad === []) {
                    continue;
                }

                GamePlayer::whereIn('id', array_column($squad, 'id'))
                    ->update(['game_team_id' => $teams[$index]->id]);
            }

            return $game->gameTeams()->with('gamePlayers')->get();
        });
    }

    /**
     * Per-team strength summary for the "Times" tab.
     *
     * Without it the balancing is invisible, and an organizer has no way to
     * tell a balanced draw from the old shuffle except by trusting the
     * label — so the numbers it balanced on are shown back.
     *
     * @param  EloquentCollection<int, GameTeam>  $gameTeams
     * @return array<int, array{average: int, goalkeepers: int}>
     */
    public function summarise(EloquentCollection $gameTeams): array
    {
        $scores = $this->scores($gameTeams->flatMap->gamePlayers);

        return $gameTeams->mapWithKeys(fn (GameTeam $team) => [
            $team->id => [
                'average' => $team->gamePlayers->isEmpty()
                    ? 0
                    : (int) round($team->gamePlayers->avg(fn (GamePlayer $player) => $scores[$player->id])),
                'goalkeepers' => $team->gamePlayers
                    ->filter(fn (GamePlayer $player) => $this->isGoalkeeper($player))
                    ->count(),
            ],
        ])->all();
    }

    /**
     * The strength of every participant, keyed by game_player id.
     *
     * A guest contact has no account, and a registered player may not have
     * filled in a profile — neither has a score. They take the median of
     * everyone who does, so an unknown player doesn't systematically drag
     * whichever team gets them up or down.
     *
     * @param  Collection<int, GamePlayer>|EloquentCollection<int, GamePlayer>  $participants
     * @return array<int, int>
     */
    private function scores(Collection|EloquentCollection $participants): array
    {
        $known = [];

        foreach ($participants as $participant) {
            $profile = $participant->user?->playerProfile;

            if ($profile) {
                $known[$participant->id] = $profile->overallScore();
            }
        }

        $fallback = $this->median(array_values($known)) ?? PlayerProfile::DEFAULT_SCORE;

        $scores = [];

        foreach ($participants as $participant) {
            $scores[$participant->id] = $known[$participant->id] ?? $fallback;
        }

        return $scores;
    }

    /**
     * @param  EloquentCollection<int, GamePlayer>  $participants
     * @return array<int, list<GamePlayer>>
     */
    private function splitAtRandom(EloquentCollection $participants, int $teamsCount): array
    {
        $squads = array_fill(0, $teamsCount, []);

        foreach ($participants->shuffle()->values() as $index => $participant) {
            $squads[$index % $teamsCount][] = $participant;
        }

        return $squads;
    }

    /**
     * @param  EloquentCollection<int, GamePlayer>  $participants
     * @return array<int, list<GamePlayer>>
     */
    private function splitBalanced(EloquentCollection $participants, int $teamsCount): array
    {
        $scores = $this->scores($participants);
        $squads = array_fill(0, $teamsCount, []);

        [$goalkeepers, $outfield] = $participants
            ->partition(fn (GamePlayer $participant) => $this->isGoalkeeper($participant));

        // Goalkeepers are seated first, and only then everyone else. A side
        // left without a keeper is a broken match in a way no amount of
        // strength balancing makes up for, so that constraint wins.
        $squads = $this->seat($goalkeepers, $scores, $squads);
        $squads = $this->seat($outfield, $scores, $squads);

        return $this->rebalance($squads, $scores);
    }

    /**
     * Hand players out strongest-first, each to the emptiest team, ties
     * going to the weakest of those. On equal-sized teams that is exactly a
     * serpentine draft; when the goalkeeper pass has already left the teams
     * uneven, it also repairs the sizes instead of compounding them.
     *
     * @param  Collection<int, GamePlayer>  $players
     * @param  array<int, int>  $scores
     * @param  array<int, list<GamePlayer>>  $squads
     * @return array<int, list<GamePlayer>>
     */
    private function seat(Collection $players, array $scores, array $squads): array
    {
        // Shuffling before the sort is what makes a redraw produce a
        // different lineup: PHP's sort is stable, so equally strong players
        // keep the random order they came in with.
        $ordered = $players->shuffle()->sortByDesc(fn (GamePlayer $player) => $scores[$player->id]);

        foreach ($ordered as $player) {
            $target = 0;

            foreach (array_keys($squads) as $index) {
                $isEmptier = count($squads[$index]) < count($squads[$target]);
                $isEqualButWeaker = count($squads[$index]) === count($squads[$target])
                    && $this->totalScore($squads[$index], $scores) < $this->totalScore($squads[$target], $scores);

                if ($isEmptier || $isEqualButWeaker) {
                    $target = $index;
                }
            }

            $squads[$target][] = $player;
        }

        return $squads;
    }

    /**
     * Close the remaining gap by swapping players between the strongest and
     * weakest teams.
     *
     * A draft alone can leave a spread that a single swap would fix, and the
     * organizer sees that spread on screen. Swaps are same-for-same
     * (goalkeeper for goalkeeper, outfield for outfield) so this can never
     * undo the goalkeeper distribution or change a team's size.
     *
     * @param  array<int, list<GamePlayer>>  $squads
     * @param  array<int, int>  $scores
     * @return array<int, list<GamePlayer>>
     */
    private function rebalance(array $squads, array $scores): array
    {
        for ($pass = 0; $pass < self::MAX_REBALANCE_PASSES; $pass++) {
            $totals = array_map(fn (array $squad) => $this->totalScore($squad, $scores), $squads);
            $spread = max($totals) - min($totals);

            if ($spread === 0) {
                break;
            }

            $strongest = (int) array_search(max($totals), $totals, strict: true);
            $weakest = (int) array_search(min($totals), $totals, strict: true);

            $best = null;

            foreach ($squads[$strongest] as $i => $strongPlayer) {
                foreach ($squads[$weakest] as $j => $weakPlayer) {
                    if ($this->isGoalkeeper($strongPlayer) !== $this->isGoalkeeper($weakPlayer)) {
                        continue;
                    }

                    $delta = $scores[$strongPlayer->id] - $scores[$weakPlayer->id];

                    if ($delta <= 0) {
                        continue;
                    }

                    $newSpread = abs(($totals[$strongest] - $delta) - ($totals[$weakest] + $delta));

                    if ($newSpread < $spread && ($best === null || $newSpread < $best['spread'])) {
                        $best = ['spread' => $newSpread, 'strong' => $i, 'weak' => $j];
                    }
                }
            }

            if ($best === null) {
                break;
            }

            [$squads[$strongest][$best['strong']], $squads[$weakest][$best['weak']]] =
                [$squads[$weakest][$best['weak']], $squads[$strongest][$best['strong']]];
        }

        return $squads;
    }

    /**
     * @param  list<GamePlayer>  $squad
     * @param  array<int, int>  $scores
     */
    private function totalScore(array $squad, array $scores): int
    {
        return array_sum(array_map(fn (GamePlayer $player) => $scores[$player->id], $squad));
    }

    /**
     * A guest contact has no profile, so this app simply doesn't know their
     * position — they are drawn as outfield players. The organizer can move
     * them afterwards if the guest is in fact the keeper.
     */
    private function isGoalkeeper(GamePlayer $participant): bool
    {
        return (bool) $participant->user?->playerProfile?->isGoalkeeper();
    }

    /**
     * @param  list<int>  $values
     */
    private function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 0
            ? (int) round(($values[$middle - 1] + $values[$middle]) / 2)
            : $values[$middle];
    }
}
