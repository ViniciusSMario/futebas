<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_players_see_the_player_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Maria Silva']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Olá, Maria!');
        $response->assertSee('Seu futebol. Sua região. Sua partida.');
        $response->assertSee('Minhas Partidas');
        $response->assertSee('Convites');
        $response->assertSee('Meu Perfil');
    }

    public function test_authenticated_organizers_see_the_organizer_dashboard(): void
    {
        $user = User::factory()->organizer()->create(['name' => 'João Souza']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Olá, João!');
        $response->assertSee('Preciso de Goleiro');
        $response->assertSee('Procurar Jogadores');
        $response->assertSee('Criar Solicitação');
        $response->assertSee('Minhas Partidas');
    }

    public function test_player_dashboard_action_links_resolve_to_working_routes(): void
    {
        $user = User::factory()->create();

        foreach (['games.mine', 'player-profile.edit', 'availability.edit', 'invitations.index', 'games.search'] as $routeName) {
            $response = $this->actingAs($user)->get(route($routeName));

            $response->assertOk();
        }
    }

    public function test_organizer_dashboard_action_links_resolve_to_working_routes(): void
    {
        $user = User::factory()->organizer()->create();

        foreach (['players.search', 'games.mine', 'games.create', 'games.search', 'sos.index'] as $routeName) {
            $response = $this->actingAs($user)->get(route($routeName));

            $response->assertOk();
        }
    }
}
