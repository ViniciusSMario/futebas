<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers an encrypted payload to a browser push service.
 *
 * Failures are never allowed to bubble up: a dead device must not break
 * the request that triggered the notification. Subscriptions the push
 * service reports as gone (404/410) are pruned on the spot.
 */
class WebPushSender
{
    public function __construct(private readonly Vapid $vapid) {}

    public function isEnabled(): bool
    {
        return $this->vapid->isConfigured();
    }

    /**
     * @return bool whether the push service accepted the message
     */
    public function send(PushSubscription $subscription, PushMessage $message): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $body = PushEncryption::encrypt(
                $message->toJson(),
                $subscription->rawPublicKey(),
                $subscription->rawAuthToken(),
            );

            $response = Http::withHeaders([
                'Authorization' => $this->vapid->authorizationHeader($subscription->endpoint),
                'Content-Encoding' => 'aes128gcm',
                'Content-Type' => 'application/octet-stream',
                'TTL' => (string) ($message->ttlSeconds() ?? config('webpush.ttl')),
                'Urgency' => config('webpush.urgency'),
            ])
                ->timeout((int) config('webpush.timeout'))
                ->withBody($body, 'application/octet-stream')
                ->post($subscription->endpoint);
        } catch (ConnectionException $exception) {
            Log::warning('Push não entregue (falha de conexão).', [
                'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::error('Push não entregue (erro inesperado).', [
                'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        // The browser uninstalled the app or revoked permission: the
        // endpoint will never work again, so stop trying.
        if (in_array($response->status(), [404, 410], strict: true)) {
            $subscription->delete();

            return false;
        }

        if ($response->failed()) {
            Log::warning('Push recusado pelo serviço.', [
                'subscription_id' => $subscription->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $subscription->forceFill(['last_used_at' => now()])->save();

        return true;
    }
}
