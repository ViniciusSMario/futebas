<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush\PushMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent only when the user taps "enviar teste" after enabling
 * notifications, to prove the whole chain works on their device.
 */
class PushTestNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable): PushMessage
    {
        return PushMessage::make('⚽ '.__('Notificações ativadas'))
            ->body(__('Você vai receber os SOS de goleiro da sua região por aqui.'))
            ->url(route('dashboard'))
            ->tag('push-test');
    }
}
