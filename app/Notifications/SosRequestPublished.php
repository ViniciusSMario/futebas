<?php

namespace App\Notifications;

use App\Models\SosRequest;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Broadcast to every goalkeeper in the match's region when an organizer
 * publishes an SOS. This is the notification that starts the competition.
 */
class SosRequestPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SosRequest $sosRequest) {}

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
        $game = $this->sosRequest->game;

        return [
            'type' => 'sos_request_published',
            'sos_request_id' => $this->sosRequest->id,
            'game_id' => $game->id,
            'title' => __('Precisa-se de :position!', ['position' => $this->sosRequest->position]),
            'body' => $this->summary(),
            'url' => route('sos-opportunities.show', $this->sosRequest),
            'icon' => 'heroicon-o-megaphone',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('🧤 '.__('Precisa-se de :position!', ['position' => $this->sosRequest->position]))
            ->body($this->summary())
            ->url(route('sos-opportunities.show', $this->sosRequest))
            // One banner per request, however many times it is re-sent.
            ->tag('sos-'.$this->sosRequest->id)
            ->requireInteraction()
            ->action('apply', __('Me candidatar'))
            ->data(['sos_request_id' => $this->sosRequest->id]);
    }

    private function summary(): string
    {
        $game = $this->sosRequest->game;

        return sprintf(
            '%s às %s · %s, %s · R$ %s',
            $game->date->format('d/m'),
            $game->start_time->format('H:i'),
            $game->location,
            $game->city,
            number_format((float) $this->sosRequest->offered_value, 2, ',', '.'),
        );
    }
}
