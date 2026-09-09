<?php

namespace App\Http\Controllers;

use App\Services\Billing\StripeSignature;
use App\Services\Billing\StripeSubscriptionSync;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Onde o Stripe conta o que aconteceu com uma assinatura.
 *
 * É a única rota do app que muda plano de alguém sem que a pessoa esteja
 * logada, então tudo depende da assinatura do cabeçalho: corpo cru,
 * conferência de HMAC, e nada de confiar no que o JSON diz antes disso.
 *
 * Responde 200 para evento que não interessa. O Stripe reenvia o que não
 * receber 2xx, e ficar reenviando um `invoice.created` que este app nunca
 * vai usar só entope a fila dos dois lados.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripeSubscriptionSync $sync) {}

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();

        $verified = StripeSignature::verify(
            $payload,
            $request->header('Stripe-Signature'),
            config('plans.billing.webhook_secret'),
            (int) config('plans.billing.webhook_tolerance', 300),
        );

        if (! $verified) {
            return response('Assinatura inválida.', 403);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response('Corpo inválido.', 400);
        }

        $this->sync->handle($event);

        return response('', 200);
    }
}
