<?php

namespace App\Notifications;

use App\Models\SosApplication;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells the organizer a goalkeeper has applied, so they can compare the
 * candidates without keeping the page open.
 */
class SosApplicationReceived extends Notification implements ShouldQueue
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
            'type' => 'sos_application_received',
            'sos_request_id' => $this->application->sos_request_id,
            'sos_application_id' => $this->application->id,
            'title' => __('Nova candidatura ao SOS'),
            'body' => $this->summary(),
            'url' => route('sos.show', $this->application->sos_request_id),
            'icon' => 'heroicon-o-hand-raised',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('🙋 '.__('Nova candidatura ao SOS'))
            ->body($this->summary())
            ->url(route('sos.show', $this->application->sos_request_id))
            ->tag('sos-'.$this->application->sos_request_id.'-applications')
            ->data(['sos_request_id' => $this->application->sos_request_id]);
    }

    private function summary(): string
    {
        return sprintf(
            '%s se candidatou por R$ %s',
            $this->application->user->name,
            number_format((float) $this->application->asking_price, 2, ',', '.'),
        );
    }
}
