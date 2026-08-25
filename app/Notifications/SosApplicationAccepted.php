<?php

namespace App\Notifications;

use App\Models\SosApplication;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The goalkeeper won the SOS: they are now confirmed in the match.
 */
class SosApplicationAccepted extends Notification implements ShouldQueue
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
            'type' => 'sos_application_accepted',
            'sos_request_id' => $this->application->sos_request_id,
            'game_id' => $this->application->sosRequest->game_id,
            'title' => __('Você foi escolhido!'),
            'body' => $this->summary(),
            'url' => route('games.mine'),
            'icon' => 'heroicon-o-check-badge',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('✅ '.__('Você foi escolhido!'))
            ->body($this->summary())
            ->url(route('games.mine'))
            ->tag('sos-'.$this->application->sos_request_id.'-result')
            ->requireInteraction();
    }

    private function summary(): string
    {
        $game = $this->application->sosRequest->game;

        return sprintf(
            'Você é o goleiro de %s às %s em %s, %s. Combinado: R$ %s',
            $game->date->format('d/m'),
            $game->start_time->format('H:i'),
            $game->location,
            $game->city,
            number_format((float) $this->application->asking_price, 2, ',', '.'),
        );
    }
}
