<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Enums\Plan;
use App\Exceptions\PlanLimitReachedException;
use App\Models\Game;
use App\Models\PlayerProfile;
use App\Models\SosRequest;
use App\Models\User;
use App\Services\PlanService;
use App\Services\SosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Onde o plano encosta na vida real: publicar SOS, se candidatar a um, e
 * os recursos que o Pro acrescenta à busca.
 *
 * Os limites são contados na origem (quantos SOS existem, quantas
 * candidaturas existem), então os testes criam as linhas de verdade em vez
 * de mexer em um contador.
 */
class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->organizer = User::factory()->organizer()->create([
            'city' => 'Teresina',
            'state' => 'PI',
        ]);
    }

    // ==================== SOS DO ORGANIZADOR ====================

    public function test_free_organizer_publishes_one_sos_per_month(): void
    {
        $this->actingAs($this->organizer)
            ->post(route('sos.store'), $this->publishPayload('Pelada de quinta'))
            ->assertRedirect();

        $this->assertSame(1, SosRequest::count());

        $response = $this->actingAs($this->organizer)
            ->post(route('sos.store'), $this->publishPayload('Pelada de sexta'));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Nem o SOS, nem a partida que o formulário criaria junto.
        $this->assertSame(1, SosRequest::count());
        $this->assertSame(1, Game::count());
    }

    public function test_the_refusal_points_at_the_plan_that_solves_it(): void
    {
        $this->publish();

        $response = $this->actingAs($this->organizer)
            ->post(route('sos.store'), $this->publishPayload('Outra'));

        $response->assertSessionHas('error', fn (string $error) => str_contains($error, 'Pro')
            && str_contains($error, '10'));
    }

    public function test_pro_organizer_publishes_beyond_the_free_limit(): void
    {
        app(PlanService::class)->assign($this->organizer, Plan::PRO);

        foreach (range(1, 3) as $i) {
            $this->actingAs($this->organizer->refresh())
                ->post(route('sos.store'), $this->publishPayload("Pelada {$i}"))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(3, SosRequest::count());
    }

    public function test_the_service_refuses_even_when_the_controller_is_bypassed(): void
    {
        $this->publish();

        $this->expectException(PlanLimitReachedException::class);

        app(SosService::class)->publish($this->game('Direto no serviço'), $this->organizer->refresh(), 60.0);
    }

    public function test_last_months_calls_do_not_count_against_this_month(): void
    {
        $sos = $this->publish();
        $sos->forceFill(['created_at' => now()->subMonth()])->save();

        $this->assertSame(0, app(PlanService::class)->used($this->organizer->refresh(), Feature::SOS_REQUESTS));

        $this->actingAs($this->organizer)
            ->post(route('sos.store'), $this->publishPayload('Mês novo'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, SosRequest::count());
    }

    // ==================== CANDIDATURAS DO GOLEIRO ====================

    public function test_free_goalkeeper_applies_to_two_calls_per_month(): void
    {
        $goalkeeper = $this->goalkeeper();
        $calls = [$this->publish(), $this->publish('B'), $this->publish('C')];

        foreach (array_slice($calls, 0, 2) as $call) {
            $this->actingAs($goalkeeper)
                ->post(route('sos-opportunities.apply', $call), ['asking_price' => '70'])
                ->assertSessionHasNoErrors();
        }

        $response = $this->actingAs($goalkeeper)
            ->post(route('sos-opportunities.apply', $calls[2]), ['asking_price' => '70']);

        $response->assertSessionHas('error');
        $this->assertSame(2, $goalkeeper->sosApplications()->count());
    }

    public function test_revising_an_existing_bid_does_not_spend_a_new_slot(): void
    {
        $goalkeeper = $this->goalkeeper();
        $calls = [$this->publish(), $this->publish('B')];

        foreach ($calls as $call) {
            $this->actingAs($goalkeeper)->post(route('sos-opportunities.apply', $call), ['asking_price' => '70']);
        }

        // No limite, mas mexendo em uma candidatura que já existe.
        $this->actingAs($goalkeeper)
            ->post(route('sos-opportunities.apply', $calls[0]), ['asking_price' => '55'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $goalkeeper->sosApplications()->count());
        $this->assertSame('55.00', $goalkeeper->sosApplications()->first()->asking_price);
    }

    public function test_pro_goalkeeper_applies_without_limit(): void
    {
        $goalkeeper = $this->goalkeeper();
        app(PlanService::class)->assign($goalkeeper, Plan::PRO);

        foreach ([$this->publish(), $this->publish('B'), $this->publish('C')] as $call) {
            $this->actingAs($goalkeeper->refresh())
                ->post(route('sos-opportunities.apply', $call), ['asking_price' => '70'])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(3, $goalkeeper->sosApplications()->count());
    }

    // ==================== BUSCA ====================

    public function test_subscribers_come_first_in_the_player_search(): void
    {
        $free = $this->player('Zé do Free');
        $pro = $this->player('Ana Pro');

        app(PlanService::class)->assign($pro->user, Plan::PRO);

        // O Free foi cadastrado antes, então a ordenação padrão ("mais
        // recentes") o colocaria embaixo de qualquer jeito: é o destaque, e
        // não a data, que precisa estar decidindo aqui.
        $free->user->forceFill(['created_at' => now()])->save();
        $free->forceFill(['created_at' => now()])->save();

        $response = $this->actingAs($this->organizer)->get(route('players.search'));

        $response->assertOk();
        $response->assertSeeInOrder(['Ana Pro', 'Zé do Free']);
    }

    public function test_the_highlight_survives_an_explicit_sort(): void
    {
        $cheapFree = $this->player('Barato Free', ['price_per_game' => '10.00']);
        $expensivePro = $this->player('Caro Pro', ['price_per_game' => '90.00']);

        app(PlanService::class)->assign($expensivePro->user, Plan::PRO);

        $response = $this->actingAs($this->organizer)->get(route('players.search', ['sort' => 'price']));

        $response->assertOk();
        $response->assertSeeInOrder(['Caro Pro', 'Barato Free']);
        $this->assertNotNull($cheapFree->id);
    }

    public function test_nearby_cities_filter_is_ignored_without_the_plan(): void
    {
        $this->player('Da Cidade', ['city' => 'Teresina']);
        $this->player('Vizinho', ['city' => 'Altos', 'state' => 'PI', 'plays_outside_city' => true]);

        $response = $this->actingAs($this->organizer)
            ->get(route('players.search', ['city' => 'Teresina', 'nearby' => 1]));

        $response->assertOk();
        $response->assertSee('Da Cidade');
        $response->assertDontSee('Vizinho');
    }

    public function test_nearby_cities_filter_widens_the_search_for_subscribers(): void
    {
        $this->player('Da Cidade', ['city' => 'Teresina']);
        $this->player('Vizinho', ['city' => 'Altos', 'state' => 'PI', 'plays_outside_city' => true]);
        $this->player('Parado', ['city' => 'Altos', 'state' => 'PI', 'plays_outside_city' => false]);

        app(PlanService::class)->assign($this->organizer, Plan::PRO);

        $response = $this->actingAs($this->organizer->refresh())
            ->get(route('players.search', ['city' => 'Teresina', 'nearby' => 1]));

        $response->assertOk();
        $response->assertSee('Da Cidade');
        $response->assertSee('Vizinho');
        // Quem não joga fora da própria cidade continua de fora: o filtro
        // amplia a distância, não a disposição de se deslocar.
        $response->assertDontSee('Parado');
    }

    // ==================== MIDDLEWARE ====================

    public function test_plan_middleware_invites_instead_of_forbidding(): void
    {
        Route::middleware(['web', 'auth', 'plan:team_reports'])
            ->get('/_teste/relatorios', fn () => 'ok');

        $this->actingAs($this->organizer)
            ->get('/_teste/relatorios')
            ->assertRedirect(route('subscription.index'));

        app(PlanService::class)->assign($this->organizer, Plan::CLUBE);

        $this->actingAs($this->organizer->refresh())
            ->get('/_teste/relatorios')
            ->assertOk();
    }

    // ==================== APOIO ====================

    /**
     * @return array<string, mixed>
     */
    private function publishPayload(string $teamName): array
    {
        return [
            'source' => 'new',
            'team_name' => $teamName,
            'location' => 'Arena Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '19:00',
            'offered_value' => '60',
        ];
    }

    private function game(string $teamName): Game
    {
        return Game::create([
            'user_id' => $this->organizer->id,
            'team_name' => $teamName,
            'location' => 'Arena Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '19:00',
            'max_players' => 10,
            'price' => '20.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
        ]);
    }

    private function publish(string $teamName = 'Pelada'): SosRequest
    {
        return SosRequest::create([
            'game_id' => $this->game($teamName)->id,
            'organizer_id' => $this->organizer->id,
            'position' => SosRequest::POSITION,
            'offered_value' => '60.00',
            'status' => SosRequest::STATUS_OPEN,
            'expires_at' => now()->addDay(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function player(string $name, array $attributes = []): PlayerProfile
    {
        $user = User::factory()->create(['name' => $name, 'state' => $attributes['state'] ?? 'PI']);

        return PlayerProfile::create(array_merge([
            'user_id' => $user->id,
            'birth_date' => '1995-05-10',
            'state' => 'PI',
            'city' => 'Teresina',
            'phone' => '86999999999',
            'positions' => ['Atacante'],
            'modalities' => ['Society'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
            'plays_outside_city' => false,
        ], $attributes));
    }

    private function goalkeeper(string $name = 'Goleiro Teste'): User
    {
        $profile = $this->player($name, ['positions' => ['Goleiro']]);

        return $profile->user;
    }
}
