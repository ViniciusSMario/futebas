<?php

namespace App\Notifications\Channels;

use App\Services\WebPush\PushMessage;
use App\Services\WebPush\WebPushSender;
use Illuminate\Notifications\Notification;

/**
 * Notification channel that delivers to every browser the notifiable has
 * subscribed with.
 *
 * A notification opts in by listing this class in `via()` and exposing a
 * `toWebPush()` method returning a {@see PushMessage}.
 */
class WebPushChannel
{
    public function __construct(private readonly WebPushSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush') || ! $this->sender->isEnabled()) {
            return;
        }

        if (! method_exists($notifiable, 'pushSubscriptions')) {
            return;
        }

        $message = $notification->toWebPush($notifiable);

        if (! $message instanceof PushMessage) {
            return;
        }

        foreach ($notifiable->pushSubscriptions()->get() as $subscription) {
            $this->sender->send($subscription, $message);
        }
    }
}
