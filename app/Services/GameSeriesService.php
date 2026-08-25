<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameSeries;
use App\Models\GameSeriesMember;
use App\Models\GuestPlayer;
use App\Models\User;
use App\Notifications\AddedToGameSeries;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a weekly pelada stocked with upcoming occurrences and its regulars
 * seated in each of them.
 *
 * There is no scheduler running in this project, so generation is pull-based:
 * whenever the organizer looks at their series, the window is topped up.
 * `series:generate` does the same for every active series and can be put on
 * a cron once one exists. Both paths are idempotent — a date that already
 * has an occurrence is skipped, and the unique index on
 * (game_series_id, date) is the backstop.
 */
class GameSeriesService
{
    public function __construct(private readonly GamePlayerService $gamePlayerService) {}

    /**
     * Create whatever occurrences are missing from the series' rolling
     * window, seating the regulars in each new one.
     *
     * @return Collection<int, Game> The occurrences created by this call.
     */
    public function syncUpcoming(GameSeries $series): Collection
    {
        $created = DB::transaction(function () use ($series) {
            $locked = GameSeries::whereKey($series->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isActive()) {
                return new Collection;
            }

            $existingDates = $locked->games()
                ->pluck('date')
                ->map(fn ($date) => $date->format('Y-m-d'))
                ->all();

            $games = $locked->upcomingDates()
                ->reject(fn ($date) => in_array($date->format('Y-m-d'), $existingDates, true))
                ->map(fn ($date) => Game::create($locked->occurrenceAttributes($date)));

            return new Collection($games->all());
        });

        foreach ($created as $game) {
            $this->seatMembers($series, $game);
        }

        return $created;
    }

    /**
     * Add a regular to the series and seat them in the occurrences that
     * have already been generated, so joining mid-window doesn't mean
     * waiting a month to play.
     */
    public function addMember(GameSeries $series, User|GuestPlayer $participant): GameSeriesMember
    {
        $identity = $participant instanceof User
            ? ['user_id' => $participant->id]
            : ['guest_player_id' => $participant->id];

        $member = GameSeriesMember::firstOrCreate([
            'game_series_id' => $series->id,
            ...$identity,
        ]);

        foreach ($this->upcomingGames($series) as $game) {
            $this->seat($game, $participant);
        }

        // An organizer who plays their own pelada is a regular like anyone
        // else, but doesn't need telling about it.
        if ($participant instanceof User && $participant->id !== $series->user_id) {
            $participant->notify(new AddedToGameSeries($series));
        }

        return $member;
    }

    /**
     * Drop a regular from the series. Occurrences already generated keep
     * them — the organizer removes those one by one if they mean to, since
     * silently pulling someone out of next week's match they'd already
     * been confirmed for would be a nasty surprise.
     */
    public function removeMember(GameSeriesMember $member): void
    {
        $member->delete();
    }

    /**
     * Stop generating occurrences. Matches already on the calendar are
     * left exactly as they are, including the ones yet to be played.
     */
    public function end(GameSeries $series): void
    {
        $series->update(['status' => GameSeries::STATUS_ENDED]);
    }

    /**
     * Seat every regular in a freshly created occurrence.
     */
    private function seatMembers(GameSeries $series, Game $game): void
    {
        $members = $series->members()->with(['user', 'guestPlayer'])->get();

        foreach ($members as $member) {
            $participant = $member->participant();

            if ($participant) {
                $this->seat($game, $participant);
            }
        }
    }

    /**
     * Put one participant into an occurrence, through the same service
     * every other join path uses. Approval is bypassed: the organizer put
     * this person on the regulars list, which is the approval.
     */
    private function seat(Game $game, User|GuestPlayer $participant): GamePlayer
    {
        return $participant instanceof User
            ? $this->gamePlayerService->join($game, $participant, bypassApproval: true)
            : $this->gamePlayerService->joinGuest($game, $participant);
    }

    /**
     * The series' occurrences that haven't started yet — the ones a new
     * regular can still be seated in.
     *
     * @return Collection<int, Game>
     */
    private function upcomingGames(GameSeries $series): Collection
    {
        return $series->games()
            ->where('status', Game::STATUS_OPEN)
            ->upcoming()
            ->get();
    }
}
