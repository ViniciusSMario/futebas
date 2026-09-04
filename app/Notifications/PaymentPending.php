<?php

namespace App\Notifications;

use App\Models\GamePlayer;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Lembra quem jogou de acertar o que ficou devendo.
 *
 * Sai **uma vez só**, e de propósito. O pagamento aqui é combinado entre
 * pessoas que se conhecem e jogam juntas toda semana — quem quita no pix
 * da mesma noite não precisa de nada, e quem esqueceu precisa de um toque,
 * não de cobrança automática toda manhã. Insistir seria o app se metendo
 * numa conversa que não é dele.
 *
 * Quem baixa como pago continua sendo o organizador: isto não move
 * dinheiro nem status, só avisa.
 */
class PaymentPending extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public GamePlayer $gamePlayer) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_pending',
            'game_id' => $this->gamePlayer->game_id,
            'amount_due' => (string) $this->gamePlayer->amount_due,
            'title' => __('Pagamento em aberto'),
            'body' => $this->summary(),
            'url' => route('games.mine'),
            'icon' => 'heroicon-o-banknotes',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('💸 '.__('Pagamento em aberto'))
            ->body($this->summary())
            ->url(route('games.mine'))
            ->tag('game-'.$this->gamePlayer->game_id.'-payment')
            ->data(['game_id' => $this->gamePlayer->game_id]);
    }

    private function summary(): string
    {
        $game = $this->gamePlayer->game;
        $amount = number_format((float) $this->gamePlayer->amount_due, 2, ',', '.');

        if ($game === null) {
            return __('Você tem R$ :amount em aberto.', ['amount' => $amount]);
        }

        return sprintf(
            'R$ %s da partida de %s — combine com %s',
            $amount,
            $game->date->format('d/m'),
            $game->user?->name ?? __('o organizador'),
        );
    }
}
