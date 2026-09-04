<?php

namespace App\Services\Billing;

use App\Enums\Plan;
use App\Exceptions\BillingUnavailableException;
use App\Models\User;

/**
 * A cobrança desligada.
 *
 * É o que roda enquanto não houver `STRIPE_SECRET` no .env — em
 * desenvolvimento, em testes, e em produção antes da integração ir ao ar.
 * Mesma escolha do Web Push sem as chaves VAPID: o recurso some, o app
 * continua inteiro.
 */
class NullBillingGateway implements BillingGateway
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function checkoutUrl(User $user, Plan $plan, string $successUrl, string $cancelUrl): string
    {
        throw BillingUnavailableException::notConfigured();
    }

    public function portalUrl(User $user, string $returnUrl): string
    {
        throw BillingUnavailableException::notConfigured();
    }
}
