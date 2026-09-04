<?php

namespace App\Notifications;

use App\Models\Game;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * "Sua partida é amanhã" e, mais perto, "é daqui a pouco".
 *
 * São dois disparos com propósitos diferentes, e por isso o número de
 * horas vem no construtor em vez de virarem duas classes: a véspera é para
 * quem ainda pode desistir a tempo de liberar a vaga (o corte de
 * cancelamento é justamente 24h), e o aviso curto é para quem confirmou e
 * simplesmente esqueceu — que é o que vira falta.
 */
class GameReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Game $game, public int $hoursBefore) {}

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
            'type' => 'game_reminder',
            'game_id' => $this->game->id,
            'hours_before' => $this->hoursBefore,
            'title' => $this->title(),
            'body' => $this->summary(),
            'url' => $this->url(),
            'icon' => 'heroicon-o-clock',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('⏰ '.$this->title())
            ->body($this->summary())
            ->url($this->url())
            // Uma tag por disparo: o aviso das 2h substitui o da véspera na
            // bandeja em vez de empilhar em cima dele.
            ->tag('game-'.$this->game->id.'-reminder-'.$this->hoursBefore)
            ->data(['game_id' => $this->game->id]);
    }

    private function title(): string
    {
        return $this->hoursBefore >= 24
            ? __('Sua partida é amanhã')
            : __('Sua partida começa em breve');
    }

    /**
     * A lista "Minhas Partidas", e não a página da partida: `games.show`
     * é rota de organizador, e este aviso vai para quem joga também.
     */
    private function url(): string
    {
        return route('games.mine');
    }

    private function summary(): string
    {
        return sprintf(
            '%s às %s — %s, %s',
            $this->game->date->format('d/m'),
            $this->game->start_time->format('H:i'),
            $this->game->location,
            $this->game->city,
        );
    }
}
