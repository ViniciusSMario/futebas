<?php

namespace App\Notifications;

use App\Models\GamePlayer;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells a player they're in — let through by the organizer, or moved up
 * automatically when a spot freed. Until this existed, getting a spot was
 * silent: the only way to find out was to open the app and look.
 */
class GamePlayerConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $promoted  Whether a spot opened up, rather than the organizer letting them in.
     */
    public function __construct(public GamePlayer $gamePlayer, public bool $promoted = false) {}

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
            'type' => 'game_player_confirmed',
            'game_id' => $this->gamePlayer->game_id,
            'game_player_id' => $this->gamePlayer->id,
            'title' => $this->title(),
            'body' => $this->summary(),
            'url' => route('games.mine'),
            'icon' => 'heroicon-o-check-circle',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('🎉 '.$this->title())
            ->body($this->summary())
            ->url(route('games.mine'))
            ->tag('game-'.$this->gamePlayer->game_id.'-confirmed')
            ->requireInteraction()
            ->data(['game_id' => $this->gamePlayer->game_id]);
    }

    private function title(): string
    {
        return $this->promoted ? __('Abriu vaga para você!') : __('Vaga confirmada!');
    }

    private function summary(): string
    {
        $game = $this->gamePlayer->game;

        if (! $game) {
            return __('Você está confirmado na partida.');
        }

        if ($this->promoted) {
            return sprintf(
                'Alguém saiu e você entrou: %s às %s · %s',
                $game->date->format('d/m'),
                $game->start_time->format('H:i'),
                $game->location,
            );
        }

        return sprintf(
            'Você está confirmado para %s às %s · %s',
            $game->date->format('d/m'),
            $game->start_time->format('H:i'),
            $game->location,
        );
    }
}
