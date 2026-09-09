<?php

namespace App\Services\Billing;

use App\Enums\Plan;
use App\Models\User;
use App\Services\PlanService;

/**
 * O contrato da cobrança, para o app não conhecer o Stripe.
 *
 * Só existem três perguntas a fazer a um meio de pagamento aqui: dá para
 * cobrar?, para onde mando quem quer assinar?, e para onde mando quem quer
 * mexer no que já assinou? O resto — plano em vigor, limite do mês — é
 * assunto do {@see PlanService}, que nunca fala com o
 * gateway.
 */
interface BillingGateway
{
    /** Há credenciais suficientes para cobrar alguém? */
    public function isConfigured(): bool;

    /**
     * Cria a sessão de pagamento e devolve a URL para onde mandar o
     * usuário.
     */
    public function checkoutUrl(User $user, Plan $plan, string $successUrl, string $cancelUrl): string;

    /**
     * URL do portal onde o usuário troca o cartão, vê faturas e cancela.
     */
    public function portalUrl(User $user, string $returnUrl): string;
}
