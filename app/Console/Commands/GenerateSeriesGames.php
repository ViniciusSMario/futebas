<?php

namespace App\Console\Commands;

use App\Models\GameSeries;
use App\Services\GameSeriesService;
use Illuminate\Console\Command;

/**
 * Tops up every active weekly pelada's window of upcoming occurrences.
 *
 * The app doesn't depend on this running — the organizer opening their
 * series does the same work — but scheduling it keeps calendars filled for
 * organizers who don't log in for a while.
 */
class GenerateSeriesGames extends Command
{
    protected $signature = 'series:generate';

    protected $description = 'Gera as próximas partidas de cada pelada semanal ativa';

    public function handle(GameSeriesService $service): int
    {
        $created = 0;

        GameSeries::query()
            ->where('status', GameSeries::STATUS_ACTIVE)
            ->each(function (GameSeries $series) use ($service, &$created) {
                $games = $service->syncUpcoming($series);
                $created += $games->count();

                if ($games->isNotEmpty()) {
                    $this->line(sprintf('%s: %d partida(s) criada(s)', $series->team_name, $games->count()));
                }
            });

        $this->info(sprintf('%d partida(s) gerada(s).', $created));

        return self::SUCCESS;
    }
}
