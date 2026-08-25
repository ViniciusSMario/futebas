<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerSearchTest extends TestCase
{
    use RefreshDatabase;

    private function createPlayer(array $attributes = []): PlayerProfile
    {
        $user = User::factory()->create([
            'name' => $attributes['name'] ?? 'Jogador Teste',
        ]);

        return PlayerProfile::create(array_merge([
            'user_id' => $user->id,
            'birth_date' => '1995-05-10',
            'state' => 'SP',
            'city' => 'São Paulo',
            'phone' => '11999999999',
            'positions' => ['Atacante'],
            'modalities' => ['Society'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
            'plays_outside_city' => false,
        ], $attributes));
    }

    private function organizer(): User
    {
        return User::factory()->organizer()->create();
    }

    public function test_guests_cannot_access_player_search(): void
    {
        $response = $this->get('/players/search');

        $response->assertRedirect('/login');
    }

    public function test_players_cannot_access_player_search(): void
    {
        $player = User::factory()->create();

        $response = $this->actingAs($player)->get('/players/search');

        $response->assertForbidden();
    }

    public function test_search_page_lists_real_players_from_database(): void
    {
        $viewer = $this->organizer();
        $player = $this->createPlayer(['name' => 'Carlos Souza']);

        $response = $this->actingAs($viewer)->get('/players/search');

        $response->assertOk();
        $response->assertSee('Carlos Souza');
        $response->assertSee(route('players.show', $player));
    }

    public function test_can_filter_players_by_position(): void
    {
        $viewer = $this->organizer();
        $this->createPlayer(['name' => 'Goleiro Um', 'positions' => ['Goleiro']]);
        $this->createPlayer(['name' => 'Atacante Um', 'positions' => ['Atacante']]);

        $response = $this->actingAs($viewer)->get('/players/search?position=Goleiro');

        $response->assertOk();
        $response->assertSee('Goleiro Um');
        $response->assertDontSee('Atacante Um');
    }

    public function test_can_filter_players_by_modality(): void
    {
        $viewer = $this->organizer();
        $this->createPlayer(['name' => 'Futsal Player', 'modalities' => ['Futsal']]);
        $this->createPlayer(['name' => 'Campo Player', 'modalities' => ['Campo']]);

        $response = $this->actingAs($viewer)->get('/players/search?modality=Futsal');

        $response->assertSee('Futsal Player');
        $response->assertDontSee('Campo Player');
    }

    public function test_can_filter_players_by_city(): void
    {
        $viewer = $this->organizer();
        $this->createPlayer(['name' => 'Jogador SP', 'city' => 'São Paulo']);
        $this->createPlayer(['name' => 'Jogador RJ', 'city' => 'Rio de Janeiro']);

        $response = $this->actingAs($viewer)->get('/players/search?city=Rio');

        $response->assertSee('Jogador RJ');
        $response->assertDontSee('Jogador SP');
    }

    public function test_can_filter_players_by_level(): void
    {
        $viewer = $this->organizer();
        $this->createPlayer(['name' => 'Iniciante Player', 'level' => 'Iniciante']);
        $this->createPlayer(['name' => 'Avancado Player', 'level' => 'Avançado']);

        $response = $this->actingAs($viewer)->get('/players/search?level=Iniciante');

        $response->assertSee('Iniciante Player');
        $response->assertDontSee('Avancado Player');
    }

    public function test_can_filter_players_by_max_price(): void
    {
        $viewer = $this->organizer();
        $this->createPlayer(['name' => 'Barato Player', 'price_per_game' => '30.00']);
        $this->createPlayer(['name' => 'Caro Player', 'price_per_game' => '100.00']);

        $response = $this->actingAs($viewer)->get('/players/search?max_price=50');

        $response->assertSee('Barato Player');
        $response->assertDontSee('Caro Player');
    }

    public function test_can_filter_players_by_availability_day(): void
    {
        $viewer = $this->organizer();

        $available = $this->createPlayer(['name' => 'Disponivel Segunda']);
        Availability::create([
            'user_id' => $available->user_id,
            'day_of_week' => 1,
            'start_time' => '18:00',
            'end_time' => '20:00',
        ]);

        $this->createPlayer(['name' => 'Sem Disponibilidade']);

        $response = $this->actingAs($viewer)->get('/players/search?availability=1');

        $response->assertSee('Disponivel Segunda');
        $response->assertDontSee('Sem Disponibilidade');
    }

    public function test_guests_cannot_access_player_profile_show_page(): void
    {
        $player = $this->createPlayer();

        $response = $this->get(route('players.show', $player));

        $response->assertRedirect('/login');
    }

    public function test_player_profile_show_page_displays_details(): void
    {
        $viewer = $this->organizer();
        $player = $this->createPlayer([
            'name' => 'Perfil Detalhado',
            'positions' => ['Meia', 'Volante'],
            'modalities' => ['Campo'],
            'level' => 'Intermediário',
            'price_per_game' => '45.00',
        ]);

        $response = $this->actingAs($viewer)->get(route('players.show', $player));

        $response->assertOk();
        $response->assertSee('Perfil Detalhado');
        $response->assertSee('Meia, Volante');
        $response->assertSee('Campo');
        $response->assertSee('Intermediário');
    }
}
