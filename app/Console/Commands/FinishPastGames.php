<?php

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;

/**
 * Closes matches whose scheduled end — plus the grace period — has passed
 * and that nobody finished by hand.
 *
 * Unlike `series:generate`, this one the app really cannot do lazily. A
 * match that stays open is not merely missing from a calendar: it is
 * missing from everyone's record. Ratings are refused until the match is
 * finished, and `Game::refreshParticipantStats()` only runs then, so an
 * organizer who never comes back silently erases that evening from the
 * attendance of every player who was there — the same attendance the
 * player search sorts on.
 *
 * Finishing is deliberately conservative: it never touches cancelled
 * matches, never re-finishes, and waits out `AUTO_FINISH_GRACE_HOURS` so a
 * match running late — or one with no end time recorded, where
 * `finishesAt()` falls back to kickoff — is not closed underneath the
 * people playing it.
 */
class FinishPastGames extends Command
{
    protected $signature = 'games:finish';

    protected $description = 'Finaliza partidas cujo horário de término já passou e que ninguém encerrou';

    public function handle(): int
    {
        $finished = 0;

        Game::query()
            ->awaitingAutoFinish()
            ->orderBy('date')
            ->each(function (Game $game) use (&$finished) {
                // A consulta só aproxima (data <= hoje); a hora exata, com
                // a folga, é decidida aqui.
                if (! $game->isEligibleToAutoFinish()) {
                    return;
                }

                $game->finish();
                $finished++;

                $this->line(sprintf(
                    '%s — %s %s',
                    $game->team_name,
                    $game->date->format('d/m/Y'),
                    $game->finishesAt()->format('H:i'),
                ));
            });

        $this->info(sprintf('%d partida(s) finalizada(s).', $finished));

        return self::SUCCESS;
    }
}
