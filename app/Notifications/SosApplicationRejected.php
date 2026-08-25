<?php

namespace App\Notifications;

use App\Models\SosApplication;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The SOS went to someone else — sent to every remaining candidate as soon
 * as the organizer decides, so nobody is left holding the slot.
 */
class SosApplicationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SosApplication $application) {}

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
            'type' => 'sos_application_rejected',
            'sos_request_id' => $this->application->sos_request_id,
            'title' => __('SOS preenchido'),
            'body' => $this->summary(),
            'url' => route('sos-opportunities.index'),
            'icon' => 'heroicon-o-x-circle',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make(__('SOS preenchido'))
            ->body($this->summary())
            ->url(route('sos-opportunities.index'))
            ->tag('sos-'.$this->application->sos_request_id.'-result');
    }

    private function summary(): string
    {
        $game = $this->application->sosRequest->game;

        return sprintf(
            'A vaga de %s às %s em %s foi preenchida por outro goleiro.',
            $game->date->format('d/m'),
            $game->start_time->format('H:i'),
            $game->location,
        );
    }
}
