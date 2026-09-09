<?php

namespace App\Models;

use App\Enums\Plan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A assinatura de um usuário — uma por conta, viva ou vencida.
 *
 * Ela é a fonte da verdade sobre o plano de alguém, porque só ela conhece
 * as datas: uma assinatura cancelada ainda vale até o fim do período pago,
 * e uma vencida não vale mais nada mesmo com `plan` preenchido. Por isso o
 * app pergunta o plano ao usuário ({@see User::plan()}), que pergunta
 * aqui — e nunca lê a coluna `plan` da tabela `users`, que é só uma cópia
 * para a busca conseguir ordenar por ela.
 */
#[Fillable([
    'user_id',
    'plan',
    'status',
    'stripe_subscription_id',
    'stripe_price_id',
    'trial_ends_at',
    'current_period_started_at',
    'current_period_ends_at',
    'ends_at',
    'cancel_at_period_end',
])]
class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIALING = 'trialing';

    /** Pagamento falhou e o Stripe ainda está tentando de novo. */
    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    /** Checkout começou mas nunca foi pago. */
    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_TRIALING,
        self::STATUS_PAST_DUE,
        self::STATUS_CANCELED,
        self::STATUS_INCOMPLETE,
    ];

    /**
     * Status que ainda dão acesso ao plano.
     *
     * `past_due` entra na lista de propósito: a cobrança falhou, o Stripe
     * vai tentar de novo nos próximos dias, e tirar o plano de alguém no
     * primeiro cartão recusado é pior para os dois lados. O app avisa em
     * vez de cortar — quando o Stripe desistir, ele manda `canceled`.
     */
    public const ACTIVE_STATUSES = [self::STATUS_ACTIVE, self::STATUS_TRIALING, self::STATUS_PAST_DUE];

    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'trial_ends_at' => 'datetime',
            'current_period_started_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // A cópia em `users.plan` só serve para a busca ordenar, mas se ela
        // atrasar o destaque some da página sem ninguém perceber. Refazê-la
        // a cada gravação é barato e mantém as duas versões juntas.
        static::saved(fn (self $subscription) => $subscription->syncUserPlan());
        static::deleted(fn (self $subscription) => $subscription->syncUserPlan());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A assinatura ainda dá acesso ao plano contratado?
     */
    public function isActive(): bool
    {
        if (! in_array($this->status, self::ACTIVE_STATUSES, true)) {
            // Cancelada, mas paga até o fim do período: o acesso continua.
            return $this->onGracePeriod();
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    /**
     * O plano que vale agora — o contratado enquanto a assinatura estiver
     * de pé, o padrão assim que ela vencer.
     */
    public function effectivePlan(): Plan
    {
        return $this->isActive() ? $this->plan : Plan::default();
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /** Cancelada, mas ainda dentro do período já pago. */
    public function onGracePeriod(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isFuture();
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    /**
     * Início do ciclo de cobrança atual, se houver um em curso.
     *
     * É o que faz a contagem mensal de uso acompanhar a fatura de quem
     * assina, em vez do calendário. Fora de um ciclo vivo devolve `null` e
     * quem chama volta para o mês corrente.
     */
    public function currentPeriodStart(): ?CarbonInterface
    {
        if ($this->current_period_started_at === null || ! $this->isActive()) {
            return null;
        }

        if ($this->current_period_ends_at !== null && $this->current_period_ends_at->isPast()) {
            return null;
        }

        return $this->current_period_started_at;
    }

    /**
     * Refaz a cópia do plano efetivo na conta do usuário.
     */
    public function syncUserPlan(): void
    {
        $user = $this->user()->first();

        if ($user === null) {
            return;
        }

        $plan = $this->exists ? $this->effectivePlan() : Plan::default();

        if ($user->plan !== $plan) {
            $user->forceFill(['plan' => $plan])->save();
        }
    }
}
