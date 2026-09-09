<?php

namespace App\Services\Billing;

/**
 * Conferência da assinatura dos webhooks do Stripe.
 *
 * O cabeçalho `Stripe-Signature` vem como `t=<timestamp>,v1=<hmac>,...`: o
 * HMAC-SHA256 de `"<timestamp>.<corpo cru>"` com o segredo do endpoint. Sem
 * essa conferência qualquer um que descobrisse a URL poderia se dar um
 * plano Clube, então um webhook que não confere é descartado, não
 * processado "por via das dúvidas".
 *
 * A tolerância no timestamp é o que impede reenviar um evento verdadeiro
 * capturado semanas atrás.
 */
class StripeSignature
{
    public static function verify(string $payload, ?string $header, ?string $secret, int $tolerance = 300): bool
    {
        if (blank($header) || blank($secret)) {
            return false;
        }

        [$timestamp, $signatures] = self::parse((string) $header);

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, (string) $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: int|null, 1: array<int, string>}
     */
    private static function parse(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't' && is_numeric($value)) {
                $timestamp = (int) $value;
            }

            // Só a v1 interessa: v0 é usada em testes de outro esquema.
            if ($key === 'v1' && filled($value)) {
                $signatures[] = (string) $value;
            }
        }

        return [$timestamp, $signatures];
    }
}
