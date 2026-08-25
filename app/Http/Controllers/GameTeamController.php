<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameTeamDrawRequest;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class GameTeamController extends Controller
{
    /**
     * Randomly draw (or redraw) teams from the game's confirmed
     * participants, replacing any previous draw entirely.
     */
    public function draw(GameTeamDrawRequest $request, Game $game): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($game->isOpen(), 403);

        $teamsCount = (int) $request->validated('teams_count');

        $confirmedPlayers = $game->gamePlayers()->where('status', GamePlayer::STATUS_CONFIRMED)->get();

        if ($confirmedPlayers->isEmpty()) {
            return redirect()
                ->route('games.show', ['game' => $game, 'tab' => 'times'])
                ->withErrors(['teams_count' => __('Não há jogadores confirmados para sortear times.')]);
        }

        DB::transaction(function () use ($game, $teamsCount, $confirmedPlayers) {
            GamePlayer::where('game_id', $game->id)->update(['game_team_id' => null]);
            GameTeam::where('game_id', $game->id)->delete();

            $teams = collect(range(1, $teamsCount))->map(fn (int $number) => GameTeam::create([
                'game_id' => $game->id,
                'name' => __('Time :number', ['number' => $number]),
            ]));

            $confirmedPlayers->shuffle()->values()->each(function (GamePlayer $gamePlayer, int $index) use ($teams, $teamsCount) {
                $gamePlayer->update(['game_team_id' => $teams[$index % $teamsCount]->id]);
            });
        });

        return redirect()->route('games.show', ['game' => $game, 'tab' => 'times'])->with('status', 'teams-drawn');
    }
}
