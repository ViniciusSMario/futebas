<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
