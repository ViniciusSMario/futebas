<?php

namespace App\Enums;

use InvalidArgumentException;

/**
 * Os planos de uso do app, do grátis ao de time.
 *
 * O enum é só a chave; quem descreve cada plano é `config/plans.php`. A
 * herança declarada lá ("o Pro é tudo do Free, mais isto") é resolvida
 * aqui, então um plano nunca repete o que o de baixo já dá — nem na regra,
 * nem no texto da página de preços, que sai destes mesmos números.
 */
enum Plan: string
{
    case FREE = 'free';

    case PRO = 'pro';

    case CLUBE = 'clube';

    /**
     * Para onde cai quem nunca assinou — e quem deixou vencer.
     */
    public static function default(): self
    {
        return self::from((string) config('plans.default', self::FREE->value));
    }

    /**
     * Os planos na ordem em que a página de preços os apresenta: do mais
     * simples ao mais completo, que é a ordem da herança.
     *
     * @return array<int, self>
     */
    public static function catalog(): array
    {
        $plans = self::cases();

        usort($plans, fn (self $a, self $b) => $a->rank() <=> $b->rank());

        return $plans;
    }

    public function label(): string
    {
        return (string) $this->config('label', ucfirst($this->value));
    }

    public function tagline(): string
    {
        return (string) $this->config('tagline', '');
    }

    /** Preço mensal em reais. */
    public function price(): float
    {
        return (float) $this->config('price', 0);
    }

    public function isPaid(): bool
    {
        return $this->price() > 0;
    }

    /** Preço em centavos, que é a unidade que o Stripe cobra. */
    public function priceInCents(): int
    {
        return (int) round($this->price() * 100);
    }

    /**
     * O plano imediatamente abaixo deste, de quem ele herda tudo.
     */
    public function inherits(): ?self
    {
        $parent = $this->config('inherits');

        return $parent === null ? null : self::from((string) $parent);
    }

    /**
     * Quantos degraus acima do plano base este está. É a distância na
     * cadeia de herança, não a ordem em que os cases foram escritos, para
     * que reordenar a config não mude a hierarquia.
     */
    public function rank(): int
    {
        return count($this->ancestors());
    }

    /**
     * Os planos abaixo deste, do mais próximo ao mais distante.
     *
     * @return array<int, self>
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $plan = $this;

        while (($plan = $plan->inherits()) !== null) {
            if (in_array($plan, $ancestors, true)) {
                throw new InvalidArgumentException("Herança circular de planos em [{$this->value}].");
            }

            $ancestors[] = $plan;
        }

        return $ancestors;
    }

    /**
     * Este plano entrega tudo que o outro entrega? É assim que se pergunta
     * "é Pro ou melhor?" sem listar os planos de cima na mão.
     */
    public function covers(self $plan): bool
    {
        return $this === $plan || in_array($plan, $this->ancestors(), true);
    }

    /**
     * Teto mensal do recurso neste plano. `null` = ilimitado.
     */
    public function limit(Feature $feature): ?int
    {
        $limits = $this->limits();

        $limit = $limits[$feature->value] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    /**
     * Todos os tetos, já com os herdados: o que o plano declara sobrescreve
     * o de baixo, e o que ele não declara continua valendo.
     *
     * @return array<string, int|null>
     */
    public function limits(): array
    {
        $limits = [];

        foreach ([...array_reverse($this->ancestors()), $this] as $plan) {
            $limits = array_merge($limits, (array) $plan->config('limits', []));
        }

        return $limits;
    }

    /**
     * Os recursos booleanos deste plano, incluindo os herdados.
     *
     * @return array<int, Feature>
     */
    public function features(): array
    {
        $features = [];

        foreach ([...array_reverse($this->ancestors()), $this] as $plan) {
            foreach ((array) $plan->config('features', []) as $feature) {
                $features[] = Feature::from((string) $feature);
            }
        }

        return array_values(array_unique($features, SORT_REGULAR));
    }

    /**
     * Os recursos que só este plano acrescenta.
     *
     * @return array<int, Feature>
     */
    public function ownFeatures(): array
    {
        return array_map(
            fn (string $feature) => Feature::from($feature),
            (array) $this->config('features', []),
        );
    }

    /**
     * Este plano libera o recurso? Recursos contáveis não passam por aqui —
     * eles têm teto, e quem sabe se ainda cabe é o PlanService, que precisa
     * do uso do mês para responder.
     */
    public function allows(Feature $feature): bool
    {
        return in_array($feature, $this->features(), true);
    }

    /**
     * As linhas do cartão de preço, montadas a partir dos limites e
     * recursos que o app realmente aplica.
     *
     * Um teto igual ao do plano herdado não vira linha: "candidaturas
     * ilimitadas" já foi dito no Pro e não precisa reaparecer no Clube.
     *
     * @return array<int, string>
     */
    public function bullets(): array
    {
        $parent = $this->inherits();

        $bullets = $parent !== null
            ? [__('Tudo do :plan', ['plan' => $parent->label()])]
            : [];

        foreach ((array) $this->config('includes', []) as $line) {
            $bullets[] = __($line);
        }

        foreach ($this->limits() as $key => $limit) {
            $feature = Feature::tryFrom((string) $key);

            if ($feature === null || ! $feature->isQuota()) {
                continue;
            }

            if ($parent !== null && $parent->limit($feature) === $limit) {
                continue;
            }

            $bullets[] = $feature->describeLimit($limit);
        }

        foreach ($this->ownFeatures() as $feature) {
            $bullets[] = $feature->label();
        }

        return $bullets;
    }

    /**
     * O preço cadastrado no Stripe correspondente a este plano.
     */
    public function stripePriceId(): ?string
    {
        $priceId = $this->config('stripe.price_id');

        return $priceId === null ? null : (string) $priceId;
    }

    /**
     * Leitura da config deste plano, aceitando caminho com ponto.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return config("plans.plans.{$this->value}.{$key}", $default);
    }
}
