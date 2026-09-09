<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Levantada quando a cobrança não está de pé: sem chaves do Stripe, sem
 * preço cadastrado para o plano, ou o Stripe respondeu com erro.
 *
 * Como o app inteiro funciona no plano Free, isto nunca derruba nada — a
 * página de planos vira uma vitrine e diz que a assinatura ainda não está
 * disponível.
 */
class BillingUnavailableException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self(__('A assinatura ainda não está disponível. Tente de novo em breve.'));
    }

    public static function missingPrice(string $plan): self
    {
        return new self(__('O plano :plan ainda não tem preço cadastrado na cobrança.', ['plan' => $plan]));
    }

    public static function gatewayFailed(string $detail): self
    {
        return new self(__('Não foi possível falar com a cobrança agora. :detail', ['detail' => $detail]));
    }
}
