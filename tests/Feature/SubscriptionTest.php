<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use App\Services\Billing\NullBillingGateway;
use App\Services\Billing\StripeBillingGateway;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A página "Meu plano" e a cobrança.
 *
 * Duas coisas se repetem aqui e são o coração do desenho: sem chaves do
 * Stripe o app continua inteiro (só não dá para assinar), e o que libera
 * um plano é o webhook — nunca o navegador voltando do checkout, que
 * qualquer um consegue simular digitando a URL.
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('subscription.index'))->assertRedirect(route('login'));
    }

    public function test_page_shows_the_catalog_and_the_current_plan(): void
    {
        $user = User::factory()->organizer()->create();

        $response = $this->actingAs($user)->get(route('subscription.index'));

        $response->assertOk();
        $response->assertSee('Free');
        $response->assertSee('Pro');
        $response->assertSee('Clube');
        $response->assertSee('19,90');
        $response->assertSee('79,90');
        // O limite do plano atual, com o consumo do ciclo.
        $response->assertSee('SOS Goleiro');
        $response->assertSee('Seu plano');
    }

    public function test_the_page_offers_no_purchase_when_billing_is_off(): void
    {
        $this->assertInstanceOf(NullBillingGateway::class, app(BillingGateway::class));

        $response = $this->actingAs(User::factory()->create())->get(route('subscription.index'));

        $response->assertOk();
        $response->assertSee('Em breve');
        $response->assertDontSee('Gerenciar assinatura');
    }

    public function test_checkout_without_billing_comes_back_with_a_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('subscription.index'))
            ->post(route('subscription.checkout', Plan::PRO));

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHas('error');

        $this->assertSame(Plan::FREE, $user->refresh()->currentPlan());
    }

    public function test_checkout_sends_the_user_to_stripe(): void
    {
        $this->configureBilling();

        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_teste']),
            '*/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.com/c/pay/teste']),
        ]);

        $user = User::factory()->create();

        $this->assertInstanceOf(StripeBillingGateway::class, app(BillingGateway::class));

        $response = $this->actingAs($user)->post(route('subscription.checkout', Plan::PRO));

        $response->assertRedirect('https://checkout.stripe.com/c/pay/teste');

        // O cliente do Stripe fica guardado para a próxima assinatura — e
        // para o portal — reencontrarem o mesmo histórico.
        $this->assertSame('cus_teste', $user->refresh()->stripe_customer_id);

        // Voltar do checkout não libera nada: quem confirma é o webhook.
        $this->actingAs($user)->get(route('subscription.success'))->assertRedirect(route('subscription.index'));
        $this->assertSame(Plan::FREE, $user->refresh()->currentPlan());
    }

    public function test_portal_reuses_the_customer_the_account_already_has(): void
    {
        $this->configureBilling();

        Http::fake([
            '*/billing_portal/sessions' => Http::response(['url' => 'https://billing.stripe.com/p/session/teste']),
        ]);

        $user = User::factory()->create();
        $user->forceFill(['stripe_customer_id' => 'cus_existente'])->save();

        $this->actingAs($user)
            ->get(route('subscription.portal'))
            ->assertRedirect('https://billing.stripe.com/p/session/teste');

        // Nenhum cliente novo: o histórico de faturas mora no que já existe.
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'billing_portal')
            && $request['customer'] === 'cus_existente');
    }

    public function test_free_plan_has_nothing_to_check_out(): void
    {
        $this->configureBilling();
        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('subscription.checkout', Plan::FREE))
            ->assertRedirect(route('subscription.index'));

        Http::assertNothingSent();
    }

    public function test_manual_switch_only_exists_in_local_development(): void
    {
        $user = User::factory()->create();

        // Ambiente de testes: a rota existe, mas não responde.
        $this->actingAs($user)->post(route('subscription.simulate', Plan::CLUBE))->assertForbidden();

        // Fora do ambiente de testes o CSRF volta a valer, então o token
        // vai junto: o que este teste checa é o gate de ambiente.
        $this->app->detectEnvironment(fn () => 'local');

        $this->actingAs($user)
            ->withSession(['_token' => 'token-de-teste'])
            ->post(route('subscription.simulate', Plan::CLUBE), ['_token' => 'token-de-teste'])
            ->assertRedirect(route('subscription.index'));

        $this->assertSame(Plan::CLUBE, $user->refresh()->currentPlan());
    }

    public function test_manual_switch_is_off_once_billing_is_configured(): void
    {
        $this->configureBilling();
        $this->app->detectEnvironment(fn () => 'local');

        $this->actingAs(User::factory()->create())
            ->withSession(['_token' => 'token-de-teste'])
            ->post(route('subscription.simulate', Plan::PRO), ['_token' => 'token-de-teste'])
            ->assertForbidden();
    }

    // ==================== WEBHOOK ====================

    public function test_webhook_rejects_an_unsigned_call(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('webhooks.stripe'), $this->subscriptionEvent($user))->assertForbidden();

        $this->assertSame(Plan::FREE, $user->refresh()->currentPlan());
    }

    public function test_webhook_rejects_a_forged_signature(): void
    {
        $this->configureBilling();

        $user = User::factory()->create();
        $payload = json_encode($this->subscriptionEvent($user));

        $this->call(
            'POST',
            route('webhooks.stripe'),
            server: ['HTTP_STRIPE_SIGNATURE' => 't='.time().',v1='.str_repeat('a', 64), 'CONTENT_TYPE' => 'application/json'],
            content: $payload,
        )->assertForbidden();

        $this->assertSame(Plan::FREE, $user->refresh()->currentPlan());
    }

    public function test_webhook_rejects_a_replayed_signature(): void
    {
        $this->configureBilling();

        $user = User::factory()->create();

        // Assinatura verdadeira, mas de duas horas atrás: fora da janela.
        $this->sendWebhook($this->subscriptionEvent($user), timestamp: time() - 7200)->assertForbidden();

        $this->assertSame(Plan::FREE, $user->refresh()->currentPlan());
    }

    public function test_webhook_grants_the_plan_matching_the_stripe_price(): void
    {
        $this->configureBilling();

        $user = User::factory()->create();

        $this->sendWebhook($this->subscriptionEvent($user))->assertOk();

        $user->refresh();

        $this->assertSame(Plan::PRO, $user->currentPlan());
        $this->assertSame('sub_teste', $user->subscription->stripe_subscription_id);
        $this->assertSame(Subscription::STATUS_ACTIVE, $user->subscription->status);
        // A cópia usada pela ordenação da busca acompanha.
        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan' => 'pro']);
    }

    public function test_webhook_keeps_access_until_the_end_of_a_cancelled_period(): void
    {
        $this->configureBilling();

        $user = User::factory()->create();
        $endsAt = now()->addDays(12);

        $event = $this->subscriptionEvent($user, [
            'cancel_at_period_end' => true,
            'current_period_end' => $endsAt->timestamp,
        ]);

        $this->sendWebhook($event)->assertOk();

        $user->refresh();

        $this->assertSame(Plan::PRO, $user->currentPlan());
        $this->assertTrue($user->subscription->onGracePeriod());
    }

    public function test_webhook_takes_the_plan_away_when_stripe_ends_it(): void
    {
        $this->configureBilling();

        $user = User::factory()->create();
        app(PlanService::class)->assign($user, Plan::PRO, ['stripe_subscription_id' => 'sub_teste']);

        $event = $this->subscriptionEvent($user, [
            'status' => 'canceled',
            'ended_at' => now()->subMinute()->timestamp,
        ], type: 'customer.subscription.deleted');

        $this->sendWebhook($event)->assertOk();

        $this->assertSame(Plan::FREE, $user->refresh()->currentPlan());
    }

    public function test_webhook_ignores_events_it_has_no_use_for(): void
    {
        $this->configureBilling();

        $this->sendWebhook([
            'type' => 'invoice.created',
            'data' => ['object' => ['id' => 'in_teste']],
        ])->assertOk();

        $this->assertDatabaseCount('subscriptions', 0);
    }

    // ==================== APOIO ====================

    private function configureBilling(): void
    {
        config([
            'plans.billing.secret' => 'sk_test_123',
            'plans.billing.webhook_secret' => 'whsec_teste',
            'plans.plans.pro.stripe.price_id' => 'price_pro_teste',
        ]);

        // O gateway é singleton e lê a config ao ser construído.
        $this->app->forgetInstance(BillingGateway::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function subscriptionEvent(User $user, array $overrides = [], string $type = 'customer.subscription.updated'): array
    {
        return [
            'type' => $type,
            'data' => [
                'object' => array_merge([
                    'id' => 'sub_teste',
                    'customer' => 'cus_teste',
                    'status' => 'active',
                    'cancel_at_period_end' => false,
                    'current_period_start' => now()->subDay()->timestamp,
                    'current_period_end' => now()->addMonth()->timestamp,
                    'items' => ['data' => [['price' => ['id' => 'price_pro_teste']]]],
                    'metadata' => ['user_id' => (string) $user->id],
                ], $overrides),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function sendWebhook(array $event, ?int $timestamp = null): TestResponse
    {
        $payload = json_encode($event);
        $timestamp ??= time();

        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, (string) config('plans.billing.webhook_secret'));

        return $this->call(
            'POST',
            route('webhooks.stripe'),
            server: [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $payload,
        );
    }
}
