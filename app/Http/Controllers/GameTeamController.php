<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameTeamDrawRequest;
use App\Models\Game;
use App\Services\TeamDrawService;
use Illuminate\Http\RedirectResponse;

class GameTeamController extends Controller
{
    /**
     * Draw (or redraw) teams from the game's confirmed participants,
     * replacing any previous draw entirely.
     */
    public function draw(GameTeamDrawRequest $request, Game $game, TeamDrawService $teams): RedirectResponse
    {
        // Someone else's game is a real authorization failure. The match
        // being over is not — it's a state the organizer can reach with a
        // stale tab or the back button, and it deserves an explanation
        // rather than a 403 wall.
        abort_unless($game->user_id === $request->user()->id, 403);

        if (! $game->isOpen()) {
            return $this->backToTeams($game)->withErrors([
                'teams_count' => $game->status === Game::STATUS_CANCELLED
                    ? __('Esta partida foi cancelada, então não dá para sortear os times.')
                    : __('Esta partida já foi finalizada, então não dá para sortear os times de novo.'),
            ]);
        }

        $teamsCount = (int) $request->validated('teams_count');
        $confirmedCount = $game->confirmedPlayersCount();

        if ($confirmedCount === 0) {
            return $this->backToTeams($game)
                ->withErrors(['teams_count' => __('Não há jogadores confirmados para sortear times.')]);
        }

        // Asked for more teams than there are people: drawing anyway would
        // hand back empty teams, which is never what was meant.
        if ($teamsCount > $confirmedCount) {
            return $this->backToTeams($game)->withErrors([
                'teams_count' => __('Só há :count jogadores confirmados — não dá para formar :teams times.', [
                    'count' => $confirmedCount,
                    'teams' => $teamsCount,
                ]),
            ]);
        }

        $teams->draw($game, $teamsCount, $request->validated('mode'));

        return $this->backToTeams($game)->with('status', 'teams-drawn');
    }

    private function backToTeams(Game $game): RedirectResponse
    {
        return redirect()->route('games.show', ['game' => $game, 'tab' => 'times']);
    }
}
