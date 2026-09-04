<?php

namespace App\Notifications;

use App\Models\SosRequest;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Avisa o organizador de que o prazo do SOS acabou e ele continua sem
 * goleiro.
 *
 * O aviso mais acionável dos três: a partida é hoje, ninguém foi escolhido,
 * e quanto antes ele souber mais chance tem de resolver por fora. Quando
 * havia candidatos aguardando, o número vai junto — é a diferença entre
 * "não apareceu ninguém" e "apareceram três e você não viu".
 */
class SosRequestExpired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SosRequest $sosRequest, public int $pendingCount) {}

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
            'type' => 'sos_request_expired',
            'sos_request_id' => $this->sosRequest->id,
            'pending_count' => $this->pendingCount,
            'title' => __('Seu SOS venceu sem goleiro'),
            'body' => $this->summary(),
            'url' => route('sos.show', $this->sosRequest),
            'icon' => 'heroicon-o-exclamation-triangle',
        ];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('⌛ '.__('Seu SOS venceu sem goleiro'))
            ->body($this->summary())
            ->url(route('sos.show', $this->sosRequest))
            ->tag('sos-'.$this->sosRequest->id.'-expired')
            ->data(['sos_request_id' => $this->sosRequest->id]);
    }

    private function summary(): string
    {
        if ($this->pendingCount > 0) {
            return trans_choice(
                'O prazo acabou com :count candidatura sem resposta.|O prazo acabou com :count candidaturas sem resposta.',
                $this->pendingCount,
                ['count' => $this->pendingCount],
            );
        }

        return __('O prazo acabou e nenhum goleiro se candidatou.');
    }
}
