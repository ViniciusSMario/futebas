<?php

namespace App\Notifications;

use App\Models\SosApplication;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A chamada acabou sem vencedor: o organizador desistiu dela, ou a própria
 * partida foi cancelada.
 *
 * Existe porque esse fim é diferente de perder a vaga para alguém, e até
 * aqui os dois usavam a mesma mensagem — quem cancelava um SOS avisava os
 * candidatos de que "a vaga foi preenchida por outro goleiro", o que
 * simplesmente não tinha acontecido. Um goleiro que reservou a noite
 * merece saber qual das duas coisas ocorreu.
 */
class SosApplicationCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SosApplication $application,
        /** A partida inteira caiu, não só a chamada. */
        public bool $matchCancelled = false,
    ) {}

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
            'type' => 'sos_application_cancelled',
            'sos_request_id' => $this->application->sos_request_id,
            'match_cancelled' => $this->matchCancelled,
            'title' => $this->title(),
            'body' => $this->summary(),
            'url' => route('sos-opportunities.index'),
            'icon' => 'heroicon-o-x-circle',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make($this->title())
            ->body($this->summary())
            ->url(route('sos-opportunities.index'))
            // Mesma tag do resultado do SOS: a resposta é uma só, e a
            // última substitui a anterior na bandeja.
            ->tag('sos-'.$this->application->sos_request_id.'-result');
    }

    private function title(): string
    {
        return $this->matchCancelled
            ? __('A partida foi cancelada')
            : __('Chamada de goleiro cancelada');
    }

    private function summary(): string
    {
        $game = $this->application->sosRequest?->game;

        if ($game === null) {
            return $this->matchCancelled
                ? __('A partida não vai mais acontecer.')
                : __('O organizador encerrou a chamada.');
        }

        return sprintf(
            $this->matchCancelled
                ? 'A pelada de %s às %s em %s não vai acontecer.'
                : 'O organizador encerrou a chamada da pelada de %s às %s em %s.',
            $game->date->format('d/m'),
            $game->start_time->format('H:i'),
            $game->location,
        );
    }
}
