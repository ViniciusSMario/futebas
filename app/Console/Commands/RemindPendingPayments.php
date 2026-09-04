<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Notifications\PaymentPending;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Um toque, um só, em quem ficou devendo a partida.
 *
 * Roda uma vez por dia e nunca repete: `game_players.payment_reminded_at`
 * é o registro de que já foi avisado. Esse limite é a decisão de produto
 * inteira — pagamento de pelada é combinado entre gente que se conhece, e
 * um robô cobrando toda manhã estragaria mais relação do que resolveria
 * dívida.
 *
 * Só entram partidas já finalizadas e de dias anteriores: quem quita no
 * pix da mesma noite nunca chega a receber nada.
 */
class RemindPendingPayments extends Command
{
    protected $signature = 'payments:remind';

    protected $description = 'Lembra uma única vez quem ficou devendo a partida';

    public function handle(): int
    {
        $sent = 0;

        GamePlayer::query()
            // Convidado sem cadastro não tem para onde receber aviso: essa
            // cobrança continua sendo do organizador, na mão.
            ->whereNotNull('user_id')
            ->where('status', GamePlayer::STATUS_CONFIRMED)
            ->where('payment_status', GamePlayer::PAYMENT_PENDING)
            // Goleiro de SOS entra com valor zero: é pago, não paga.
            ->where('amount_due', '>', 0)
            ->whereNull('payment_reminded_at')
            ->whereHas('game', fn (Builder $game) => $game
                ->where('status', Game::STATUS_FINISHED)
                ->whereDate('date', '<', now()->toDateString()))
            ->with(['game.user', 'user'])
            ->each(function (GamePlayer $gamePlayer) use (&$sent) {
                $gamePlayer->user?->notify(new PaymentPending($gamePlayer));

                $gamePlayer->forceFill(['payment_reminded_at' => now()])->save();
                $sent++;

                $this->line(sprintf(
                    '%s — R$ %s da partida de %s',
                    $gamePlayer->user?->name ?? '?',
                    number_format((float) $gamePlayer->amount_due, 2, ',', '.'),
                    $gamePlayer->game?->date->format('d/m') ?? '?',
                ));
            });

        $this->info(sprintf('%d lembrete(s) de pagamento enviado(s).', $sent));

        return self::SUCCESS;
    }
}
