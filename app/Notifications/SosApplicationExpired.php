<?php

namespace App\Notifications;

use App\Models\SosApplication;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Avisa o goleiro de que a chamada em que ele se candidatou venceu sem
 * ninguém ser escolhido.
 *
 * Existe porque o único jeito de uma candidatura terminar sem resposta era
 * o silêncio: quem cancela avisa os candidatos, quem escolhe avisa os dois
 * lados, mas quem simplesmente deixa o prazo passar não avisava ninguém —
 * e a candidatura ficava "aguardando" para sempre na tela do goleiro. É
 * uma resposta ruim, mas é uma resposta.
 */
class SosApplicationExpired extends Notification implements ShouldQueue
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
            'type' => 'sos_application_expired',
            'sos_request_id' => $this->application->sos_request_id,
            'title' => __('A chamada de goleiro venceu'),
            'body' => $this->summary(),
            'url' => route('sos-opportunities.index'),
            'icon' => 'heroicon-o-clock',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('⌛ '.__('A chamada de goleiro venceu'))
            ->body($this->summary())
            ->url(route('sos-opportunities.index'))
            ->tag('sos-'.$this->application->sos_request_id.'-expired')
            ->data(['sos_request_id' => $this->application->sos_request_id]);
    }

    private function summary(): string
    {
        $game = $this->application->sosRequest?->game;

        if ($game === null) {
            return __('O prazo acabou e ninguém foi escolhido. Sua candidatura não vale mais.');
        }

        return sprintf(
            '%s às %s, em %s: o prazo acabou sem escolha',
            $game->date->format('d/m'),
            $game->start_time->format('H:i'),
            $game->city,
        );
    }
}
