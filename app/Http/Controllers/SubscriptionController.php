<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use App\Exceptions\BillingUnavailableException;
use App\Services\Billing\BillingGateway;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Meu plano": o que a conta tem hoje, quanto já usou no ciclo e como
 * mudar de plano.
 *
 * O controller não decide nada sobre acesso — quem sabe o plano é o
 * {@see PlanService} e quem cobra é o {@see BillingGateway}. Aqui só se
 * junta as duas coisas em uma página e se traduz falha de cobrança em
 * recado, porque cartão recusado e Stripe fora do ar são situações
 * normais, não erro de programa.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanService $plans,
        private readonly BillingGateway $billing,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('subscription.index', [
            'plans' => Plan::catalog(),
            'currentPlan' => $user->currentPlan(),
            'subscription' => $user->subscription,
            'usage' => $this->plans->usage($user),
            'periodEnd' => $this->plans->periodEnd($user),
            'billingConfigured' => $this->billing->isConfigured(),
            'canSwitchManually' => $this->canSwitchManually(),
        ]);
    }

    /**
     * Manda o usuário para o checkout do plano escolhido.
     */
    public function checkout(Request $request, Plan $plan): RedirectResponse
    {
        if (! $plan->isPaid()) {
            return redirect()->route('subscription.index');
        }

        try {
            $url = $this->billing->checkoutUrl(
                $request->user(),
                $plan,
                route('subscription.success'),
                route('subscription.index'),
            );
        } catch (BillingUnavailableException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->away($url);
    }

    /**
     * Portal do Stripe: trocar cartão, ver faturas, cancelar.
     */
    public function portal(Request $request): RedirectResponse
    {
        try {
            $url = $this->billing->portalUrl($request->user(), route('subscription.index'));
        } catch (BillingUnavailableException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->away($url);
    }

    /**
     * Volta do checkout.
     *
     * O plano não é liberado aqui: quem sincroniza é o webhook, que é a
     * única confirmação confiável de que o pagamento passou — esta rota é
     * só para onde o navegador volta, e o usuário pode fechar a aba antes
     * dela. Por isso o recado fala em "confirmando".
     */
    public function success(): RedirectResponse
    {
        return redirect()
            ->route('subscription.index')
            ->with('status', 'subscription-processing');
    }

    /**
     * Troca de plano na mão, sem passar por cobrança.
     *
     * Existe só para dar para experimentar os limites em desenvolvimento
     * enquanto o Stripe não está ligado. Fora de `local`, ou com a cobrança
     * configurada, a rota simplesmente não existe.
     */
    public function simulate(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($this->canSwitchManually(), 403);

        $this->plans->assign($request->user(), $plan);

        return redirect()
            ->route('subscription.index')
            ->with('status', 'subscription-simulated');
    }

    private function canSwitchManually(): bool
    {
        return app()->isLocal() && ! $this->billing->isConfigured();
    }
}
