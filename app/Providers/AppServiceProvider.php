<?php

namespace App\Providers;

use App\Services\Billing\BillingGateway;
use App\Services\Billing\NullBillingGateway;
use App\Services\Billing\StripeBillingGateway;
use App\Services\WebPush\Vapid;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->rateLimiters();
    }

    /**
     * Limites das rotas que qualquer pessoa alcança sem estar logada.
     *
     * São nomeados, e não `throttle:10,60` espalhado pelas rotas, porque o
     * número sozinho não diz nada: o que precisa ficar registrado é por que
     * dez e não cem, e sobretudo por quem se conta. Aqui quase tudo é
     * contado por IP, e IP no Brasil é um endereço compartilhado — operadora
     * móvel põe um bairro inteiro atrás do mesmo. Um limite apertado demais
     * não barra abuso: barra a pelada de quarta.
     */
    private function rateLimiters(): void
    {
        // O link público de uma partida nasce para circular em grupo de
        // WhatsApp: dezenas de pessoas abrindo ao mesmo tempo, da mesma
        // operadora, é o uso normal e não pode ser confundido com ataque.
        RateLimiter::for('public-game', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // O POST que cria participante convidado — a única rota sem login
        // que grava algo. Contado por IP **e por partida**: um engraçadinho
        // lotando uma pelada de nomes falsos para no décimo, enquanto o
        // pessoal do mesmo wi-fi entrando em partidas diferentes não
        // esbarra em nada. Sem a partida na chave, seria o contrário.
        //
        // A chave usa o caminho, e não o parâmetro `game`: o binding de rota
        // já resolveu o slug em modelo quando este middleware roda, e um
        // modelo não vira string sozinho. O caminho carrega o slug e é a
        // mesma coisa, sem depender da ordem dos middlewares.
        RateLimiter::for('guest-join', fn (Request $request) => Limit::perHour(10)
            ->by($request->ip().'|'.$request->path()));

        // Criação de conta. O login já tem limite por credencial dentro do
        // LoginRequest; criar conta não tinha nenhum.
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // O limite do LoginRequest é por e-mail + IP, o que não segura quem
        // varre uma lista de e-mails a partir de um IP só. Este segura, e é
        // folgado o bastante para uma casa inteira errar a senha.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Catálogo do IBGE que já vem no projeto, com cache longo. Não há
        // segredo a proteger; o limite existe só para a rota não virar um
        // jeito barato de ocupar processo.
        RateLimiter::for('cities', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
