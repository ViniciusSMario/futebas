<?php

namespace App\Notifications;

use App\Models\Invitation;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells a player an organizer wants them in a match. Without this the
 * invitation just sits in /invitations waiting to be discovered.
 */
class InvitationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invitation $invitation) {}

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
            'type' => 'invitation_received',
            'invitation_id' => $this->invitation->id,
            'game_id' => $this->invitation->game_id,
            'title' => __('Convite para jogar'),
            'body' => $this->summary(),
            'url' => route('invitations.index'),
            'icon' => 'heroicon-o-envelope',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('⚽ '.__('Convite para jogar'))
            ->body($this->summary())
            ->url(route('invitations.index'))
            ->tag('invitation-'.$this->invitation->id)
            ->data(['invitation_id' => $this->invitation->id]);
    }

    private function summary(): string
    {
        $game = $this->invitation->game;
        $organizer = $this->invitation->organizer?->name ?? __('Um organizador');

        if (! $game) {
            return __(':organizer chamou você para uma partida.', ['organizer' => $organizer]);
        }

        return sprintf(
            '%s chamou você para %s às %s · %s',
            $organizer,
            $game->date->format('d/m'),
            $game->start_time->format('H:i'),
            $game->location,
        );
    }
}
