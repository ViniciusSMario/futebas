<?php

namespace App\Notifications;

use App\Models\Game;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells participants that something they'd already planned around — when,
 * where or how much — has moved. The caller passes the changes already
 * described in words, since only it has both the old and the new values.
 */
class GameUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $changes  Human-readable "Campo: antes → depois" lines.
     */
    public function __construct(public Game $game, public array $changes) {}

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
            'type' => 'game_updated',
            'game_id' => $this->game->id,
            'changes' => $this->changes,
            'title' => __('Partida alterada'),
            'body' => $this->summary(),
            'url' => route('games.mine'),
            'icon' => 'heroicon-o-pencil-square',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('📝 '.__('Partida alterada'))
            ->body($this->summary())
            ->url(route('games.mine'))
            // Successive edits replace one another rather than stacking up.
            ->tag('game-'.$this->game->id.'-updated')
            ->data(['game_id' => $this->game->id]);
    }

    private function summary(): string
    {
        return sprintf(
            '%s às %s · %s',
            $this->game->date->format('d/m'),
            $this->game->start_time->format('H:i'),
            implode(' · ', $this->changes),
        );
    }
}
