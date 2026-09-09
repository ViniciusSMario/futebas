<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Enums\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O catálogo de planos: o que cada um promete, e qual deles vale para uma
 * conta em cada momento da vida de uma assinatura.
 *
 * A parte mais importante daqui é a primeira: `config/plans.php` é dado
 * solto, então nada além deste teste impede um erro de digitação de
 * desligar um limite sem que ninguém perceba.
 */
class PlanCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_only_uses_keys_that_exist_in_the_enums(): void
    {
        foreach (config('plans.plans') as $key => $definition) {
            $this->assertNotNull(Plan::tryFrom($key), "Plano desconhecido na config: {$key}");

            foreach (array_keys($definition['limits'] ?? []) as $limitKey) {
                $feature = Feature::tryFrom((string) $limitKey);

                $this->assertNotNull($feature, "Limite desconhecido em {$key}: {$limitKey}");
                $this->assertTrue($feature->isQuota(), "{$limitKey} não é um recurso contável.");
            }

            foreach ($definition['features'] ?? [] as $featureKey) {
                $feature = Feature::tryFrom((string) $featureKey);

                $this->assertNotNull($feature, "Recurso desconhecido em {$key}: {$featureKey}");
                $this->assertFalse($feature->isQuota(), "{$featureKey} tem teto e deveria estar em 'limits'.");
            }
        }
    }

    public function test_every_plan_has_a_definition(): void
    {
        foreach (Plan::cases() as $plan) {
            $this->assertNotEmpty($plan->label());
            $this->assertNotEmpty($plan->bullets());
        }
    }

    public function test_paid_plans_inherit_everything_below_them(): void
    {
        $this->assertTrue(Plan::PRO->covers(Plan::FREE));
        $this->assertTrue(Plan::CLUBE->covers(Plan::PRO));
        $this->assertTrue(Plan::CLUBE->covers(Plan::FREE));
        $this->assertFalse(Plan::FREE->covers(Plan::PRO));

        // "Tudo do Pro" precisa valer na regra, não só no texto.
        $this->assertTrue(Plan::CLUBE->allows(Feature::SEARCH_HIGHLIGHT));
        $this->assertTrue(Plan::CLUBE->allows(Feature::NEARBY_CITIES));
        $this->assertFalse(Plan::FREE->allows(Feature::SEARCH_HIGHLIGHT));
    }

    public function test_limits_match_the_advertised_plans(): void
    {
        $this->assertSame(1, Plan::FREE->limit(Feature::SOS_REQUESTS));
        $this->assertSame(2, Plan::FREE->limit(Feature::SOS_APPLICATIONS));

        $this->assertSame(10, Plan::PRO->limit(Feature::SOS_REQUESTS));
        $this->assertNull(Plan::PRO->limit(Feature::SOS_APPLICATIONS));

        $this->assertNull(Plan::CLUBE->limit(Feature::SOS_REQUESTS));
        $this->assertNull(Plan::CLUBE->limit(Feature::SOS_APPLICATIONS));
    }

    public function test_price_page_lines_come_from_the_enforced_limits(): void
    {
        $this->assertContains('1 SOS Goleiro por mês', Plan::FREE->bullets());
        $this->assertContains('Candidatura a 2 vagas por mês', Plan::FREE->bullets());

        $this->assertContains('Tudo do Free', Plan::PRO->bullets());
        $this->assertContains('10 SOS Goleiro por mês', Plan::PRO->bullets());
        $this->assertContains('Candidaturas ilimitadas', Plan::PRO->bullets());
        $this->assertContains('Destaque na busca e no ranking', Plan::PRO->bullets());
    }

    public function test_a_limit_unchanged_from_the_plan_below_is_not_repeated(): void
    {
        $bullets = Plan::CLUBE->bullets();

        $this->assertContains('Tudo do Pro', $bullets);
        $this->assertContains('SOS Goleiro ilimitado', $bullets);
        // Candidaturas já eram ilimitadas no Pro: repetir aqui só faria a
        // lista parecer maior sem oferecer nada novo.
        $this->assertNotContains('Candidaturas ilimitadas', $bullets);
    }

    public function test_account_without_subscription_is_on_the_default_plan(): void
    {
        $user = User::factory()->create();

        $this->assertSame(Plan::FREE, $user->currentPlan());
        $this->assertSame('free', $user->plan->value);
    }

    public function test_active_subscription_decides_the_plan(): void
    {
        $user = $this->subscribed(Plan::PRO);

        $this->assertSame(Plan::PRO, $user->currentPlan());
        $this->assertTrue($user->onPlan(Plan::FREE));
        $this->assertTrue($user->planAllows(Feature::SEARCH_HIGHLIGHT));
    }

    public function test_expired_subscription_falls_back_to_the_default_plan(): void
    {
        $user = $this->subscribed(Plan::PRO, [
            'status' => Subscription::STATUS_CANCELED,
            'ends_at' => now()->subDay(),
        ]);

        $this->assertSame(Plan::FREE, $user->currentPlan());
        $this->assertFalse($user->planAllows(Feature::SEARCH_HIGHLIGHT));
    }

    public function test_cancelled_subscription_keeps_the_plan_until_the_paid_period_ends(): void
    {
        $user = $this->subscribed(Plan::CLUBE, [
            'status' => Subscription::STATUS_CANCELED,
            'cancel_at_period_end' => true,
            'ends_at' => now()->addDays(10),
        ]);

        $this->assertSame(Plan::CLUBE, $user->currentPlan());
        $this->assertTrue($user->subscription->onGracePeriod());
    }

    public function test_a_failed_payment_does_not_cut_access_immediately(): void
    {
        $user = $this->subscribed(Plan::PRO, ['status' => Subscription::STATUS_PAST_DUE]);

        $this->assertSame(Plan::PRO, $user->currentPlan());
        $this->assertTrue($user->subscription->isPastDue());
    }

    public function test_the_plan_column_follows_the_subscription(): void
    {
        $user = $this->subscribed(Plan::PRO);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan' => 'pro']);

        $user->subscription->update([
            'status' => Subscription::STATUS_CANCELED,
            'ends_at' => now()->subDay(),
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan' => 'free']);
    }

    public function test_plans_sync_command_repairs_a_column_left_behind_by_time(): void
    {
        $user = $this->subscribed(Plan::PRO, ['current_period_ends_at' => now()->addDay()]);

        // A assinatura vence pela passagem do tempo, sem nenhuma gravação
        // para atualizar a cópia — o caso que o comando existe para pegar.
        $user->subscription->forceFill(['ends_at' => now()->subMinute()])->saveQuietly();
        $user->forceFill(['plan' => 'pro'])->save();

        $this->artisan('plans:sync')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan' => 'free']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscribed(Plan $plan, array $attributes = []): User
    {
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_started_at' => now()->subDays(3),
            'current_period_ends_at' => now()->addMonth(),
            ...$attributes,
        ]);

        return $user->refresh();
    }
}
