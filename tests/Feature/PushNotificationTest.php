<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\PushTestNotification;
use App\Services\WebPush\P256;
use App\Services\WebPush\PushMessage;
use App\Services\WebPush\Vapid;
use App\Services\WebPush\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Device subscriptions and the delivery path to a browser push service.
 */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A subscription payload shaped like the one `PushManager.subscribe()`
     * produces.
     *
     * @return array<string, mixed>
     */
    private function subscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
                'auth' => 'BTBZMqHH6r4Tts7J_aSIgg',
            ],
        ];
    }

    private function withVapidKeys(): void
    {
        $keys = Vapid::generateKeys();

        config([
            'webpush.vapid.public_key' => $keys['public'],
            'webpush.vapid.private_key' => $keys['private'],
            'webpush.vapid.subject' => 'mailto:contato@futebas.test',
        ]);

        // The signer is a singleton built from config, so drop the instance
        // resolved before these values were set.
        $this->app->forgetInstance(Vapid::class);
    }

    public function test_guests_cannot_register_a_device(): void
    {
        $this->postJson('/push-subscriptions', $this->subscriptionPayload())->assertUnauthorized();
    }

    public function test_a_user_can_register_a_device(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload());

        $response->assertCreated();

        $subscription = PushSubscription::sole();
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame('https://fcm.googleapis.com/fcm/send/abc123', $subscription->endpoint);
        $this->assertSame(hash('sha256', $subscription->endpoint), $subscription->endpoint_hash);
    }

    public function test_re_registering_the_same_device_does_not_duplicate_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload());
        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload());

        $this->assertSame(1, PushSubscription::count());
    }

    public function test_one_user_can_register_several_devices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload('https://fcm.googleapis.com/fcm/send/phone'));
        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload('https://fcm.googleapis.com/fcm/send/desktop'));

        $this->assertSame(2, $user->pushSubscriptions()->count());
    }

    public function test_a_device_can_be_unregistered(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload());

        $response = $this->actingAs($user)->deleteJson('/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);

        $response->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_registering_validates_the_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/push-subscriptions', ['endpoint' => 'not-a-url'])
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_a_notification_is_delivered_to_every_registered_device(): void
    {
        $this->withVapidKeys();
        Http::fake(['*' => Http::response('', 201)]);

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload('https://fcm.googleapis.com/fcm/send/phone'));
        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload('https://fcm.googleapis.com/fcm/send/desktop'));

        $user->notify(new PushTestNotification);

        Http::assertSentCount(2);

        Http::assertSent(function ($request) {
            return str_starts_with($request->header('Authorization')[0], 'vapid t=')
                && $request->header('Content-Encoding')[0] === 'aes128gcm'
                && strlen($request->body()) > 86;
        });
    }

    public function test_a_subscription_the_push_service_reports_as_gone_is_deleted(): void
    {
        $this->withVapidKeys();
        Http::fake(['*' => Http::response('', 410)]);

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload());

        app(WebPushSender::class)->send(PushSubscription::sole(), PushMessage::make('Oi'));

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_a_transient_push_failure_does_not_delete_the_subscription(): void
    {
        $this->withVapidKeys();
        Http::fake(['*' => Http::response('', 500)]);

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload());

        $delivered = app(WebPushSender::class)->send(PushSubscription::sole(), PushMessage::make('Oi'));

        $this->assertFalse($delivered);
        $this->assertSame(1, PushSubscription::count());
    }

    public function test_nothing_is_sent_when_vapid_keys_are_missing(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
        $this->app->forgetInstance(Vapid::class);

        Http::fake();

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload());

        $user->notify(new PushTestNotification);

        Http::assertNothingSent();
    }

    public function test_the_test_push_endpoint_reports_when_push_is_not_configured(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
        $this->app->forgetInstance(Vapid::class);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/push-subscriptions/teste')->assertStatus(503);
    }

    public function test_the_payload_the_service_worker_receives_carries_the_click_target(): void
    {
        $message = PushMessage::make('Precisa-se de Goleiro!')
            ->body('22/08 às 19:00')
            ->url('https://futebas.test/sos/oportunidades/1')
            ->tag('sos-1')
            ->requireInteraction();

        $payload = json_decode($message->toJson(), true);

        $this->assertSame('Precisa-se de Goleiro!', $payload['title']);
        $this->assertSame('https://futebas.test/sos/oportunidades/1', $payload['url']);
        $this->assertSame('sos-1', $payload['tag']);
        $this->assertTrue($payload['requireInteraction']);
    }

    public function test_stored_keys_round_trip_back_to_raw_bytes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/push-subscriptions', $this->subscriptionPayload());

        $subscription = PushSubscription::sole();

        $this->assertSame(P256::PUBLIC_KEY_LENGTH, strlen($subscription->rawPublicKey()));
        $this->assertSame(16, strlen($subscription->rawAuthToken()));
    }
}
