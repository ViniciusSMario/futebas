<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Notifications\GameReminder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Lembra os confirmados — e o organizador — da partida que está chegando:
 * uma vez na véspera, outra pouco antes do apito.
 *
 * Cada disparo é registrado em `games.reminded_*_at`. Não é otimização: o
 * scheduler roda de novo a cada hora e um servidor que ficou fora do ar
 * volta com trabalho acumulado, então sem esse registro a mesma partida
 * seria lembrada em toda rodada até começar.
 *
 * Nada é enviado depois do apito inicial. Um lembrete atrasado não avisa
 * de nada — só constrange quem não foi.
 */
class RemindUpcomingGames extends Command
{
    protected $signature = 'games:remind';

    protected $description = 'Envia os lembretes de véspera e de última hora das partidas que vêm aí';

    public function handle(): int
    {
        $sent = 0;

        Game::query()
            ->where('status', Game::STATUS_OPEN)
            // Nada antes de hoje e nada além de amanhã: fora dessa faixa
            // nenhum dos dois lembretes pode estar vencendo.
            ->whereDate('date', '>=', now()->toDateString())
            ->whereDate('date', '<=', now()->addDay()->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereNull('reminded_24h_at')
                ->orWhereNull('reminded_2h_at'))
            ->orderBy('date')
            ->each(function (Game $game) use (&$sent) {
                $hours = $this->dueReminder($game);

                if ($hours === null) {
                    return;
                }

                $recipients = $game->reminderRecipients();

                Notification::send($recipients, new GameReminder($game, $hours));

                $this->stamp($game, $hours);
                $sent++;

                $this->line(sprintf(
                    '%s — %s às %s (%dh antes, %d pessoa(s))',
                    $game->team_name,
                    $game->date->format('d/m'),
                    $game->start_time->format('H:i'),
                    $hours,
                    $recipients->count(),
                ));
            });

        $this->info(sprintf('%d lembrete(s) enviado(s).', $sent));

        return self::SUCCESS;
    }

    /**
     * Qual lembrete está vencendo agora, se algum.
     *
     * Testa do mais próximo para o mais distante: uma partida criada em
     * cima da hora tem os dois prazos já vencidos ao mesmo tempo, e nesse
     * caso quem vale é o curto — mandar os dois seria dizer "é amanhã"
     * sobre algo que é daqui a pouco.
     */
    private function dueReminder(Game $game): ?int
    {
        $startsAt = $game->startsAt();

        if ($startsAt->isPast()) {
            return null;
        }

        if ($game->reminded_2h_at === null && now()->greaterThanOrEqualTo($startsAt->copy()->subHours(Game::REMINDER_LATE_HOURS))) {
            return Game::REMINDER_LATE_HOURS;
        }

        if ($game->reminded_24h_at === null && now()->greaterThanOrEqualTo($startsAt->copy()->subHours(Game::REMINDER_EARLY_HOURS))) {
            return Game::REMINDER_EARLY_HOURS;
        }

        return null;
    }

    /**
     * Registra o envio. Ao mandar o curto, o da véspera também é marcado:
     * ele perdeu o sentido e não pode sair depois.
     */
    private function stamp(Game $game, int $hours): void
    {
        $game->forceFill($hours === Game::REMINDER_LATE_HOURS
            ? [
                'reminded_2h_at' => now(),
                'reminded_24h_at' => $game->reminded_24h_at ?? now(),
            ]
            : ['reminded_24h_at' => now()])->save();
    }
}
