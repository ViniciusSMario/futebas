<?php

namespace App\Notifications;

use App\Models\Game;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells the organizer of an approval-gated match that a spot freed up and
 * somebody is queued for it. Those games are never auto-promoted — vetting
 * each entrant is the point of the setting — so without this the vacancy
 * would sit there unnoticed.
 */
class WaitingListSpotOpened extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Game $game, public int $waitingCount) {}

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
            'type' => 'waiting_list_spot_opened',
            'game_id' => $this->game->id,
            'waiting_count' => $this->waitingCount,
            'title' => __('Abriu vaga na sua partida'),
            'body' => $this->summary(),
            'url' => $this->url(),
            'icon' => 'heroicon-o-user-plus',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('🔓 '.__('Abriu vaga na sua partida'))
            ->body($this->summary())
            ->url($this->url())
            ->tag('game-'.$this->game->id.'-spot-opened')
            ->data(['game_id' => $this->game->id]);
    }

    private function url(): string
    {
        return route('games.show', ['game' => $this->game, 'tab' => 'participantes']);
    }

    private function summary(): string
    {
        return sprintf(
            '%s na lista de espera para %s às %s — confirme quem entra',
            trans_choice(':count jogador|:count jogadores', $this->waitingCount, ['count' => $this->waitingCount]),
            $this->game->date->format('d/m'),
            $this->game->start_time->format('H:i'),
        );
    }
}
