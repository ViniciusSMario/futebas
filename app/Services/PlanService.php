<?php

namespace App\Services;

use App\Enums\Feature;
use App\Enums\Plan;
use App\Exceptions\PlanLimitReachedException;
use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * O que o plano de cada usuário libera, e quanto dele já foi usado no mês.
 *
 * O uso é **contado na origem** — quantos SOS aquele organizador publicou,
 * quantas candidaturas aquele goleiro enviou — e nunca guardado em um
 * contador próprio. É a mesma disciplina das médias de avaliação e da
 * ficha de presença: um número que se recalcula não tem como divergir do
 * que aconteceu de fato, e aqui isso importa mais ainda, porque um
 * contador errado ou cobra de quem não usou, ou libera de graça.
 *
 * O mês é o ciclo de cobrança de quem assina e o mês do calendário para
 * quem está no Free: quem paga no dia 10 renova o limite no dia 10.
 */
class PlanService
{
    public function planFor(User $user): Plan
    {
        return $user->currentPlan();
    }

    /**
     * Teto mensal do recurso para este usuário. `null` = ilimitado.
     */
    public function limit(User $user, Feature $feature): ?int
    {
        return $this->planFor($user)->limit($feature);
    }

    /**
     * Quanto do recurso já foi usado no ciclo atual.
     */
    public function used(User $user, Feature $feature): int
    {
        $since = $this->periodStart($user);

        return match ($feature) {
            // Um SOS publicado avisou toda a região na hora em que saiu.
            // Cancelar depois não desfaz isso, então continua contando.
            Feature::SOS_REQUESTS => SosRequest::query()
                ->where('organizer_id', $user->id)
                ->where('created_at', '>=', $since)
                ->count(),

            // Revisar o preço pedido reaproveita a mesma linha (o serviço
            // usa updateOrCreate), então mudar de ideia sobre uma vaga não
            // gasta uma segunda candidatura.
            Feature::SOS_APPLICATIONS => SosApplication::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', $since)
                ->count(),

            default => 0,
        };
    }

    /**
     * Quanto ainda cabe. `null` = ilimitado; nunca devolve negativo.
     */
    public function remaining(User $user, Feature $feature): ?int
    {
        $limit = $this->limit($user, $feature);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->used($user, $feature));
    }

    /**
     * Ainda cabe mais um uso deste recurso?
     */
    public function hasQuota(User $user, Feature $feature): bool
    {
        $remaining = $this->remaining($user, $feature);

        return $remaining === null || $remaining > 0;
    }

    /**
     * Porteiro dos recursos contáveis: deixa passar ou explica por que não.
     *
     * @throws PlanLimitReachedException
     */
    public function ensureQuota(User $user, Feature $feature): void
    {
        if ($this->hasQuota($user, $feature)) {
            return;
        }

        throw PlanLimitReachedException::quotaExhausted(
            $feature,
            (int) $this->limit($user, $feature),
            $this->upgradeFor($feature, $this->planFor($user)),
        );
    }

    /**
     * Porteiro dos recursos booleanos.
     *
     * @throws PlanLimitReachedException
     */
    public function ensureAllows(User $user, Feature $feature): void
    {
        if ($user->planAllows($feature)) {
            return;
        }

        throw PlanLimitReachedException::featureUnavailable(
            $feature,
            $this->upgradeFor($feature, $this->planFor($user)),
        );
    }

    /**
     * O plano mais barato que melhora este recurso para quem está em
     * `$current` — o que vale sugerir na hora que o limite bate.
     */
    public function upgradeFor(Feature $feature, Plan $current): ?Plan
    {
        foreach (Plan::catalog() as $plan) {
            if ($plan->rank() <= $current->rank()) {
                continue;
            }

            if ($feature->isQuota()) {
                $limit = $plan->limit($feature);
                $currentLimit = $current->limit($feature);

                if ($limit === null || ($currentLimit !== null && $limit > $currentLimit)) {
                    return $plan;
                }

                continue;
            }

            if ($plan->allows($feature)) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Começo do ciclo de uso: a fatura de quem assina, o mês do calendário
     * para todo mundo mais.
     */
    public function periodStart(User $user): CarbonInterface
    {
        return $user->subscription?->currentPeriodStart() ?? now()->startOfMonth();
    }

    /**
     * Quando os limites voltam a zerar.
     */
    public function periodEnd(User $user): CarbonInterface
    {
        $subscription = $user->subscription;

        if ($subscription?->currentPeriodStart() !== null && $subscription->current_period_ends_at !== null) {
            return $subscription->current_period_ends_at;
        }

        return now()->endOfMonth();
    }

    /**
     * Os recursos contáveis que fazem sentido para este usuário: um
     * organizador nunca se candidata, um jogador nunca publica um SOS.
     *
     * @return array<int, Feature>
     */
    public function quotasFor(User $user): array
    {
        return $user->hasRole(User::ROLE_ORGANIZER)
            ? [Feature::SOS_REQUESTS]
            : [Feature::SOS_APPLICATIONS];
    }

    /**
     * O consumo do ciclo, do jeito que a página "Meu plano" mostra.
     *
     * @return array<int, array{feature: Feature, used: int, limit: int|null, remaining: int|null}>
     */
    public function usage(User $user): array
    {
        return array_map(fn (Feature $feature) => [
            'feature' => $feature,
            'used' => $this->used($user, $feature),
            'limit' => $this->limit($user, $feature),
            'remaining' => $this->remaining($user, $feature),
        ], $this->quotasFor($user));
    }

    /**
     * Coloca o usuário em um plano.
     *
     * É por aqui que passam tanto a sincronização vinda do Stripe quanto a
     * troca manual em ambiente local — uma assinatura por conta, sempre a
     * mesma linha, para não existirem duas "atuais" ao mesmo tempo.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function assign(User $user, Plan $plan, array $attributes = []): Subscription
    {
        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan' => $plan,
                'status' => $attributes['status'] ?? Subscription::STATUS_ACTIVE,
                'current_period_started_at' => $attributes['current_period_started_at'] ?? now(),
                'current_period_ends_at' => $attributes['current_period_ends_at'] ?? now()->addMonth(),
                'ends_at' => $attributes['ends_at'] ?? null,
                'cancel_at_period_end' => $attributes['cancel_at_period_end'] ?? false,
                ...array_intersect_key($attributes, array_flip([
                    'stripe_subscription_id',
                    'stripe_price_id',
                    'trial_ends_at',
                ])),
            ],
        );

        $user->setRelation('subscription', $subscription);

        return $subscription;
    }
}
