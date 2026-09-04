<?php

namespace App\Services\Billing;

use App\Enums\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Traduz um evento do Stripe para a assinatura local.
 *
 * O Stripe é a fonte da verdade sobre pagamento; este projeto é a fonte da
 * verdade sobre acesso. Este serviço é a fronteira entre os dois, e é de
 * propósito o único lugar que conhece o formato dos eventos: em todo o
 * resto do app existe só `Subscription` e `Plan`.
 *
 * Eventos desconhecidos são ignorados sem erro — o Stripe manda muito mais
 * coisa do que interessa aqui, e responder 500 para o que não interessa só
 * faz ele reenviar para sempre.
 */
class StripeSubscriptionSync
{
    public function __construct(private readonly PlanService $plans) {}

    /**
     * Status do Stripe para os daqui. `unpaid` e `incomplete_expired` são
     * o fim da linha das tentativas de cobrança, então valem cancelamento.
     */
    private const STATUS_MAP = [
        'active' => Subscription::STATUS_ACTIVE,
        'trialing' => Subscription::STATUS_TRIALING,
        'past_due' => Subscription::STATUS_PAST_DUE,
        'canceled' => Subscription::STATUS_CANCELED,
        'unpaid' => Subscription::STATUS_CANCELED,
        'incomplete' => Subscription::STATUS_INCOMPLETE,
        'incomplete_expired' => Subscription::STATUS_CANCELED,
    ];

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): void
    {
        $type = (string) Arr::get($event, 'type');
        $object = (array) Arr::get($event, 'data.object', []);

        match ($type) {
            'checkout.session.completed' => $this->rememberCustomer($object),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->syncSubscription($object),
            default => null,
        };
    }

    /**
     * Fim do checkout: o que ainda não se sabia é qual cliente do Stripe
     * pertence a esta conta. A assinatura em si chega no evento seguinte.
     *
     * @param  array<string, mixed>  $session
     */
    private function rememberCustomer(array $session): void
    {
        $user = $this->resolveUser($session);
        $customerId = Arr::get($session, 'customer');

        if ($user === null || blank($customerId)) {
            return;
        }

        if ($user->stripe_customer_id !== $customerId) {
            $user->forceFill(['stripe_customer_id' => $customerId])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function syncSubscription(array $subscription): void
    {
        $user = $this->resolveUser($subscription);

        if ($user === null) {
            Log::warning('Webhook do Stripe sem usuário correspondente.', [
                'customer' => Arr::get($subscription, 'customer'),
                'subscription' => Arr::get($subscription, 'id'),
            ]);

            return;
        }

        $plan = $this->resolvePlan($subscription);
        $status = self::STATUS_MAP[(string) Arr::get($subscription, 'status')] ?? Subscription::STATUS_INCOMPLETE;

        // Cancelamento marcado para o fim do período mantém o acesso até
        // lá; cancelamento imediato encerra na data que o Stripe informar.
        $endsAt = match (true) {
            filled(Arr::get($subscription, 'ended_at')) => $this->timestamp(Arr::get($subscription, 'ended_at')),
            (bool) Arr::get($subscription, 'cancel_at_period_end') => $this->timestamp(Arr::get($subscription, 'cancel_at')) ?? $this->timestamp(Arr::get($subscription, 'current_period_end')),
            default => null,
        };

        $this->plans->assign($user, $plan, [
            'status' => $status,
            'stripe_subscription_id' => Arr::get($subscription, 'id'),
            'stripe_price_id' => Arr::get($subscription, 'items.data.0.price.id'),
            'trial_ends_at' => $this->timestamp(Arr::get($subscription, 'trial_end')),
            'current_period_started_at' => $this->timestamp(Arr::get($subscription, 'current_period_start')),
            'current_period_ends_at' => $this->timestamp(Arr::get($subscription, 'current_period_end')),
            'ends_at' => $endsAt,
            'cancel_at_period_end' => (bool) Arr::get($subscription, 'cancel_at_period_end'),
        ]);
    }

    /**
     * Qual plano este preço representa. O id do preço manda; o metadado
     * fica como rede, para assinaturas criadas fora do checkout do app.
     *
     * @param  array<string, mixed>  $subscription
     */
    private function resolvePlan(array $subscription): Plan
    {
        $priceId = Arr::get($subscription, 'items.data.0.price.id');

        foreach (Plan::catalog() as $plan) {
            if (filled($priceId) && $plan->stripePriceId() === $priceId) {
                return $plan;
            }
        }

        return Plan::tryFrom((string) Arr::get($subscription, 'metadata.plan')) ?? Plan::default();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function resolveUser(array $object): ?User
    {
        $userId = Arr::get($object, 'metadata.user_id') ?? Arr::get($object, 'client_reference_id');

        if (filled($userId)) {
            $user = User::find($userId);

            if ($user !== null) {
                return $user;
            }
        }

        $customerId = Arr::get($object, 'customer');

        return blank($customerId) ? null : User::where('stripe_customer_id', $customerId)->first();
    }

    /**
     * O Stripe manda instantes em epoch e o app guarda datas no fuso
     * configurado. Sem converter, um período que termina hoje é gravado
     * três horas à frente — e uma assinatura já encerrada continua
     * valendo até o fim da tarde.
     */
    private function timestamp(mixed $value): ?CarbonInterface
    {
        return blank($value)
            ? null
            : CarbonImmutable::createFromTimestamp((int) $value)->setTimezone(config('app.timezone', 'UTC'));
    }
}
