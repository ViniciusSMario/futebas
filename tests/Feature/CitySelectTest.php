<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameSeries;
use App\Models\User;
use App\Support\Cities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cidade deixou de ser campo aberto: é um par estado + cidade, escolhido
 * de um catálogo que vive dentro do projeto.
 *
 * O que estes testes protegem é a parte que o select não protege sozinho —
 * o servidor recusar um par que não existe. Sem isso bastava um POST à mão
 * para gravar "Teresina/SP" e furar toda a busca por região.
 */
class CitySelectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    // ==================== CATÁLOGO ====================

    public function test_the_catalog_ships_with_the_project(): void
    {
        $this->assertContains('Teresina', Cities::for('PI'));
        $this->assertContains('São Paulo', Cities::for('SP'));

        $this->assertTrue(Cities::has('PI', 'Teresina'));
        $this->assertFalse(Cities::has('SP', 'Teresina'));
        $this->assertFalse(Cities::has('PI', 'Cidade Inventada'));

        // Todo estado tem municípios: um estado vazio significaria um
        // select de cidade impossível de preencher.
        foreach (array_keys(Cities::states()) as $uf) {
            $this->assertNotEmpty(Cities::for($uf), "Estado sem municípios: {$uf}");
        }
    }

    public function test_lookup_ignores_case_but_not_the_state(): void
    {
        $this->assertSame('Teresina', Cities::canonical('PI', 'teresina'));
        $this->assertSame('Teresina', Cities::canonical('pi', 'TERESINA'));
        $this->assertNull(Cities::canonical('RJ', 'teresina'));
    }

    // ==================== ENDPOINT ====================

    public function test_cities_endpoint_is_public_and_lists_a_state(): void
    {
        // Pública de propósito: o cadastro precisa dela antes de existir
        // conta para autenticar.
        $response = $this->getJson(route('cities.index', 'PI'));

        $response->assertOk();
        $this->assertContains('Teresina', $response->json());
    }

    public function test_cities_endpoint_rejects_something_that_is_not_a_state(): void
    {
        $this->getJson(route('cities.index', 'XX'))->assertNotFound();
    }

    // ==================== FORMULÁRIOS ====================

    public function test_the_game_form_already_carries_the_organizers_state(): void
    {
        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)->get(route('games.create'));

        $response->assertOk();
        // As opções da UF de quem organiza vêm prontas do servidor: sem
        // esperar JS para o caso mais comum.
        $response->assertSee('Teresina');
        $response->assertSee('Parnaíba');
        $response->assertDontSee('Sorocaba');
    }

    public function test_creating_a_game_stores_the_state_beside_the_city(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)
            ->post(route('games.store'), $this->gamePayload())
            ->assertRedirect();

        $game = Game::firstOrFail();

        $this->assertSame('Teresina', $game->city);
        $this->assertSame('PI', $game->state);
    }

    public function test_a_city_from_another_state_is_refused(): void
    {
        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)
            ->post(route('games.store'), $this->gamePayload(['state' => 'SP']));

        $response->assertSessionHasErrors('city');
        $this->assertSame(0, Game::count());
    }

    public function test_an_invented_city_is_refused(): void
    {
        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)
            ->post(route('games.store'), $this->gamePayload(['city' => 'Cidade Inventada']));

        $response->assertSessionHasErrors('city');
        $this->assertSame(0, Game::count());
    }

    public function test_a_weekly_pelada_passes_its_state_on_to_every_occurrence(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('game-series.store'), [
            'team_name' => 'Pelada de Quinta',
            'modality' => 'Society',
            'day_of_week' => 4,
            'start_time' => '19:00',
            'end_time' => '20:00',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'max_players' => 14,
            'price' => '25.00',
        ])->assertRedirect();

        $series = GameSeries::firstOrFail();

        $this->assertSame('PI', $series->state);
        $this->assertNotEmpty($series->games);
        $this->assertSame(['PI'], $series->games->pluck('state')->unique()->values()->all());
    }

    public function test_publishing_an_sos_records_the_state_of_the_match(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('sos.store'), [
            'source' => 'new',
            'team_name' => 'Pelada da quinta',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '19:00',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'offered_value' => '60.00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('PI', Game::firstOrFail()->state);
    }

    public function test_the_sos_form_refuses_a_pair_that_does_not_exist(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('sos.store'), [
            'source' => 'new',
            'team_name' => 'Pelada da quinta',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '19:00',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'RJ',
            'modality' => 'Society',
            'offered_value' => '60.00',
        ])->assertSessionHasErrors('city');

        $this->assertSame(0, Game::count());
    }

    // ==================== APOIO ====================

    private function organizer(): User
    {
        return User::factory()->organizer()->create(['city' => 'Teresina', 'state' => 'PI']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function gamePayload(array $overrides = []): array
    {
        return array_merge([
            'team_name' => 'Furacão FC',
            'modality' => 'Society',
            'date' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'price' => '25.00',
            'max_players' => 20,
        ], $overrides);
    }
}
