<?php

namespace App\Notifications;

use App\Models\Game;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells everyone counting on a match that it's off. The one notification
 * nobody can afford to miss, so it keeps the banner on screen.
 */
class GameCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Game $game) {}

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
            'type' => 'game_cancelled',
            'game_id' => $this->game->id,
            'title' => __('Partida cancelada'),
            'body' => $this->summary(),
            'url' => route('games.mine'),
            'icon' => 'heroicon-o-x-circle',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('🚫 '.__('Partida cancelada'))
            ->body($this->summary())
            ->url(route('games.mine'))
            ->tag('game-'.$this->game->id.'-cancelled')
            ->requireInteraction()
            ->data(['game_id' => $this->game->id]);
    }

    private function summary(): string
    {
        return sprintf(
            '%s às %s · %s foi cancelada pelo organizador',
            $this->game->date->format('d/m'),
            $this->game->start_time->format('H:i'),
            $this->game->location,
        );
    }
}
