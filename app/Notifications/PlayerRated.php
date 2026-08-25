<?php

namespace App\Notifications;

use App\Models\Rating;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells a player an organizer rated them after a match — the moment their
 * public reputation actually changed.
 */
class PlayerRated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Rating $rating) {}

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
            'type' => 'player_rated',
            'rating_id' => $this->rating->id,
            'game_id' => $this->rating->game_id,
            'title' => __('Você foi avaliado'),
            'body' => $this->summary(),
            'url' => route('ratings.show', $this->rating->user_id),
            'icon' => 'heroicon-o-star',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('⭐ '.__('Você foi avaliado'))
            ->body($this->summary())
            ->url(route('ratings.show', $this->rating->user_id))
            ->tag('rating-'.$this->rating->id)
            ->data(['rating_id' => $this->rating->id]);
    }

    private function summary(): string
    {
        $organizer = $this->rating->organizer?->name ?? __('O organizador');
        $game = $this->rating->game;
        $stars = str_repeat('★', $this->rating->overall_rating);

        if (! $game) {
            return sprintf('%s avaliou você: %s', $organizer, $stars);
        }

        return sprintf(
            '%s avaliou você na partida de %s: %s',
            $organizer,
            $game->date->format('d/m'),
            $stars,
        );
    }
}
