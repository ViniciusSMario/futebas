<?php

namespace App\Notifications;

use App\Models\GamePlayer;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells the organizer somebody joined their match on their own — through
 * the public link or by accepting an invitation. Carries the resulting
 * status, since "is waiting for your approval" is the one that needs an
 * answer.
 */
class GamePlayerJoined extends Notification implements ShouldQueue
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
            'type' => 'game_player_joined',
            'game_id' => $this->gamePlayer->game_id,
            'game_player_id' => $this->gamePlayer->id,
            'status' => $this->gamePlayer->status,
            'title' => $this->title(),
            'body' => $this->summary(),
            'url' => $this->url(),
            'icon' => $this->needsApproval() ? 'heroicon-o-hand-raised' : 'heroicon-o-user-plus',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make(($this->needsApproval() ? '🙋 ' : '➕ ').$this->title())
            ->body($this->summary())
            ->url($this->url())
            ->tag('game-'.$this->gamePlayer->game_id.'-joins')
            ->data(['game_id' => $this->gamePlayer->game_id]);
    }

    private function needsApproval(): bool
    {
        return $this->gamePlayer->status === GamePlayer::STATUS_PENDING;
    }

    private function title(): string
    {
        return $this->needsApproval() ? __('Pedido de participação') : __('Novo jogador na partida');
    }

    private function url(): string
    {
        return route('games.show', ['game' => $this->gamePlayer->game_id, 'tab' => 'participantes']);
    }

    private function summary(): string
    {
        $outcome = match ($this->gamePlayer->status) {
            GamePlayer::STATUS_PENDING => __('quer entrar e aguarda sua aprovação'),
            GamePlayer::STATUS_WAITING_LIST => __('entrou na lista de espera'),
            default => __('entrou na partida'),
        };

        $game = $this->gamePlayer->game;

        return $game
            ? sprintf('%s %s · %s', $this->gamePlayer->displayName(), $outcome, $game->date->format('d/m'))
            : sprintf('%s %s', $this->gamePlayer->displayName(), $outcome);
    }
}
