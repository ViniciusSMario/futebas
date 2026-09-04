<?php

namespace App\Services\Billing;

use App\Enums\Plan;
use App\Exceptions\BillingUnavailableException;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe pela API REST, sem SDK.
 *
 * O `stripe/stripe-php` traz uma árvore de dependências inteira para
 * fazer, aqui, três chamadas HTTP — a mesma conta que levou o Web Push a
 * ser implementado na mão neste projeto. O que o app usa do Stripe é
 * pequeno e estável: criar cliente, abrir checkout, abrir o portal. O
 * resto chega pelos webhooks.
 *
 * A codificação de formulário do Laravel já escreve `line_items[0][price]`
 * do jeito que o Stripe espera, então os payloads abaixo são literalmente
 * os da documentação.
 */
class StripeBillingGateway implements BillingGateway
{
    public function __construct(
        private readonly ?string $secret,
        private readonly string $apiBase = 'https://api.stripe.com/v1',
        private readonly int $timeout = 15,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->secret);
    }

    /**
     * Abre o checkout de assinatura do plano.
     *
     * O `client_reference_id` e o metadado com o plano são o que permite ao
     * webhook saber de quem é a assinatura mesmo se o cliente do Stripe
     * tiver sido criado em outra sessão.
     */
    public function checkoutUrl(User $user, Plan $plan, string $successUrl, string $cancelUrl): string
    {
        $this->ensureConfigured();

        $priceId = $plan->stripePriceId();

        if (blank($priceId)) {
            throw BillingUnavailableException::missingPrice($plan->label());
        }

        $response = $this->request()->asForm()->post($this->url('checkout/sessions'), [
            'mode' => 'subscription',
            'customer' => $this->customerId($user),
            'client_reference_id' => (string) $user->id,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
            'subscription_data' => [
                'metadata' => ['user_id' => (string) $user->id, 'plan' => $plan->value],
            ],
            'metadata' => ['user_id' => (string) $user->id, 'plan' => $plan->value],
            'locale' => 'pt-BR',
        ]);

        return (string) $this->result($response)['url'];
    }

    public function portalUrl(User $user, string $returnUrl): string
    {
        $this->ensureConfigured();

        $response = $this->request()->asForm()->post($this->url('billing_portal/sessions'), [
            'customer' => $this->customerId($user),
            'return_url' => $returnUrl,
            'locale' => 'pt-BR',
        ]);

        return (string) $this->result($response)['url'];
    }

    /**
     * O cliente do Stripe correspondente à conta, criado na primeira vez.
     *
     * O id fica em `users.stripe_customer_id` para que uma segunda
     * assinatura — ou o portal — reencontre o mesmo cliente, com o
     * histórico de faturas junto.
     */
    public function customerId(User $user): string
    {
        if (filled($user->stripe_customer_id)) {
            return (string) $user->stripe_customer_id;
        }

        $response = $this->request()->asForm()->post($this->url('customers'), [
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user_id' => (string) $user->id],
        ]);

        $customerId = (string) $this->result($response)['id'];

        $user->forceFill(['stripe_customer_id' => $customerId])->save();

        return $customerId;
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) $this->secret)
            ->timeout($this->timeout)
            ->acceptJson();
    }

    private function url(string $path): string
    {
        return rtrim($this->apiBase, '/').'/'.ltrim($path, '/');
    }

    /**
     * Desembrulha a resposta, transformando erro do Stripe em uma exceção
     * que o controller sabe mostrar.
     *
     * @return array<string, mixed>
     */
    private function result(Response $response): array
    {
        if ($response->failed()) {
            $message = (string) ($response->json('error.message') ?? $response->status());

            // A mensagem do Stripe é para nós, não para o usuário — vai
            // para o log inteira e volta resumida para a tela.
            Log::warning('Stripe respondeu com erro.', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            throw BillingUnavailableException::gatewayFailed($message);
        }

        return (array) $response->json();
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw BillingUnavailableException::notConfigured();
        }
    }
}
