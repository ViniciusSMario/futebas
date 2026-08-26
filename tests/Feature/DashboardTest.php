<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** A match starting the given number of hours from now. */
    private function createGame(User $organizer, int $startsInHours, array $attributes = []): Game
    {
        $start = now()->addHours($startsInHours);

        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Furacão FC',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'modality' => 'Society',
            'date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHour()->format('H:i'),
            'max_players' => 10,
            'price' => '25.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
        ], $attributes));
    }

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
        $response->assertSee('Criar Partida');
        $response->assertSee('Minhas Partidas');
    }

    public function test_organizer_dashboard_highlights_the_next_upcoming_match(): void
    {
        $organizer = User::factory()->organizer()->create();

        // The later match must not win, so ordering is actually exercised.
        $this->createGame($organizer, 96, ['location' => 'Quadra Distante']);
        $this->createGame($organizer, 24, ['location' => 'Arena Mais Próxima']);

        $response = $this->actingAs($organizer)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Sua próxima partida');
        $response->assertSee('Arena Mais Próxima');
        $response->assertDontSee('Quadra Distante');
    }

    public function test_organizer_dashboard_without_upcoming_matches_prompts_to_create_one(): void
    {
        $organizer = User::factory()->organizer()->create();

        // Already played: it is not what the organizer needs to act on.
        $this->createGame($organizer, -48, ['status' => Game::STATUS_FINISHED]);

        $response = $this->actingAs($organizer)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Nenhuma partida marcada');
        $response->assertDontSee('Sua próxima partida');
    }

    public function test_player_dashboard_highlights_the_next_confirmed_match(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();

        $joined = $this->createGame($organizer, 48, ['location' => 'Arena Confirmada']);
        $this->createGame($organizer, 24, ['location' => 'Arena Alheia']);

        GamePlayer::create([
            'game_id' => $joined->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $joined->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($player)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Arena Confirmada');
        // A sooner match the player did not join must not be surfaced.
        $response->assertDontSee('Arena Alheia');
    }

    public function test_player_dashboard_ignores_matches_the_player_is_only_waitlisted_for(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();

        $game = $this->createGame($organizer, 48, ['location' => 'Arena Lotada']);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_WAITING_LIST,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($player)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Arena Lotada');
        $response->assertSee('Nenhuma partida confirmada');
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
