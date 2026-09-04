<?php

/*
|--------------------------------------------------------------------------
| Vocabulário
|--------------------------------------------------------------------------
|
| As chaves deste arquivo são os valores de App\Enums\Plan (free, pro,
| clube) e de App\Enums\Feature (sos_requests, sos_applications,
| search_highlight, nearby_cities, multiple_organizers, team_reports,
| priority_support). Config é dado puro de propósito — quem valida se este
| arquivo e os enums continuam falando a mesma língua é o PlanCatalogTest.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Plano padrão
    |--------------------------------------------------------------------------
    |
    | Quem nunca assinou nada — e quem deixou a assinatura vencer — cai
    | aqui. O app inteiro funciona neste plano; os pagos ampliam limites,
    | nunca destravam o básico.
    |
    */

    'default' => 'free',

    /*
    |--------------------------------------------------------------------------
    | Catálogo
    |--------------------------------------------------------------------------
    |
    | Cada plano declara quatro coisas, e só estas quatro:
    |
    |   inherits — de qual plano ele herda limites e recursos ("Tudo do Pro")
    |   limits   — teto mensal por recurso contável. `null` = ilimitado
    |   features — chaves de recurso liberadas (booleanas)
    |   includes — linhas de texto que não são gate nenhum, só contexto
    |
    | A página de preços é montada a partir daqui (App\Enums\Plan::bullets()),
    | então o que está escrito para o usuário é literalmente o que o gate
    | aplica: não existe uma lista de vantagens paralela para desencontrar
    | da regra.
    |
    */

    'plans' => [

        'free' => [
            'label' => 'Free',
            'tagline' => 'Para quem só quer jogar',
            'price' => 0.0,
            'inherits' => null,
            'includes' => [
                'Perfil de jogador completo',
                'Buscar jogadores e partidas',
            ],
            'limits' => [
                'sos_requests' => 1,
                'sos_applications' => 2,
            ],
            'features' => [],
            'stripe' => [
                'price_id' => null,
            ],
        ],

        'pro' => [
            'label' => 'Pro',
            'tagline' => 'Para quem vive de pelada',
            'price' => 19.90,
            'inherits' => 'free',
            'includes' => [],
            'limits' => [
                'sos_requests' => 10,
                'sos_applications' => null,
            ],
            'features' => [
                'search_highlight',
                'nearby_cities',
            ],
            'stripe' => [
                'price_id' => env('STRIPE_PRICE_PRO'),
            ],
        ],

        'clube' => [
            'label' => 'Clube',
            'tagline' => 'Para times, quadras e escolinhas',
            'price' => 79.90,
            'inherits' => 'pro',
            'includes' => [],
            'limits' => [
                'sos_requests' => null,
                'sos_applications' => null,
            ],
            'features' => [
                'multiple_organizers',
                'team_reports',
                'priority_support',
            ],
            'stripe' => [
                'price_id' => env('STRIPE_PRICE_CLUBE'),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cobrança
    |--------------------------------------------------------------------------
    |
    | Sem `STRIPE_SECRET` a cobrança fica desligada e o app segue inteiro no
    | plano Free — do mesmo jeito que o push sem as chaves VAPID. Em
    | ambiente local, e só nele, a página de planos deixa trocar de plano na
    | mão, para dar para testar os limites sem Stripe nenhum.
    |
    */

    'billing' => [
        'currency' => env('STRIPE_CURRENCY', 'brl'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Tolerância (segundos) do timestamp na assinatura do webhook.
        'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
        'api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com/v1'),
        'timeout' => (int) env('STRIPE_TIMEOUT', 15),
    ],

];
