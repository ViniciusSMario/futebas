<?php

namespace App\Providers;

use App\Services\Billing\BillingGateway;
use App\Services\Billing\NullBillingGateway;
use App\Services\Billing\StripeBillingGateway;
use App\Services\WebPush\Vapid;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // VAPID credentials come from config, so the container can build
        // both the signer and everything that depends on it (the sender,
        // the notification channel) without touching env() at runtime.
        $this->app->singleton(Vapid::class, fn () => new Vapid(
            config('webpush.vapid.public_key'),
            config('webpush.vapid.private_key'),
            (string) config('webpush.vapid.subject'),
        ));

        // Sem chaves do Stripe a cobrança não existe — e o app inteiro
        // segue funcionando no plano Free, como o push sem as chaves
        // VAPID. Quem resolve isso é o container, uma vez, para nenhum
        // controller precisar perguntar se dá para cobrar antes de tentar.
        $this->app->singleton(BillingGateway::class, function () {
            $secret = config('plans.billing.secret');

            return filled($secret)
                ? new StripeBillingGateway(
                    (string) $secret,
                    (string) config('plans.billing.api_base'),
                    (int) config('plans.billing.timeout'),
                )
                : new NullBillingGateway;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
