<?php

namespace App\Notifications;

use App\Models\Invitation;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells the organizer how an invitation ended. Accept and decline share
 * one class because they're the same event to the organizer — the answer
 * arrived — and differ only in the word for it.
 */
class InvitationAnswered extends Notification implements ShouldQueue
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
            'type' => 'invitation_answered',
            'invitation_id' => $this->invitation->id,
            'game_id' => $this->invitation->game_id,
            'status' => $this->invitation->status,
            'title' => $this->title(),
            'body' => $this->summary(),
            'url' => $this->url(),
            'icon' => $this->wasAccepted() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make(($this->wasAccepted() ? '✅ ' : '❌ ').$this->title())
            ->body($this->summary())
            ->url($this->url())
            ->tag('invitation-'.$this->invitation->id.'-answer')
            ->data(['invitation_id' => $this->invitation->id]);
    }

    private function wasAccepted(): bool
    {
        return $this->invitation->status === Invitation::STATUS_ACCEPTED;
    }

    private function title(): string
    {
        return $this->wasAccepted() ? __('Convite aceito') : __('Convite recusado');
    }

    private function url(): string
    {
        $game = $this->invitation->game;

        return $game
            ? route('games.show', ['game' => $game, 'tab' => 'convites'])
            : route('games.mine');
    }

    private function summary(): string
    {
        $player = $this->invitation->user?->name ?? __('Um jogador');
        $verb = $this->wasAccepted() ? __('aceitou') : __('recusou');
        $game = $this->invitation->game;

        if (! $game) {
            return sprintf('%s %s o convite.', $player, $verb);
        }

        return sprintf(
            '%s %s o convite para %s às %s',
            $player,
            $verb,
            $game->date->format('d/m'),
            $game->start_time->format('H:i'),
        );
    }
}
