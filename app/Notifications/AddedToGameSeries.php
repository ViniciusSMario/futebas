<?php

namespace App\Notifications;

use App\Models\GameSeries;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells a player they're now a regular in a weekly pelada, and will be
 * added to every occurrence from here on.
 *
 * This fires once, when they're added to the series — not week after week
 * as occurrences are generated. A standing commitment they already agreed
 * to isn't news, and the matches show up in "Minhas Partidas" anyway.
 */
class AddedToGameSeries extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public GameSeries $gameSeries) {}

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
            'type' => 'added_to_game_series',
            'game_series_id' => $this->gameSeries->id,
            'title' => __('Você é mensalista!'),
            'body' => $this->summary(),
            'url' => route('games.mine'),
            'icon' => 'heroicon-o-arrow-path',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('🔁 '.__('Você é mensalista!'))
            ->body($this->summary())
            ->url(route('games.mine'))
            ->tag('series-'.$this->gameSeries->id)
            ->data(['game_series_id' => $this->gameSeries->id]);
    }

    private function summary(): string
    {
        return sprintf(
            '%s toda %s às %s · %s — você entra automaticamente em cada partida',
            $this->gameSeries->team_name,
            $this->gameSeries->dayName(),
            $this->gameSeries->start_time->format('H:i'),
            $this->gameSeries->location,
        );
    }
}
