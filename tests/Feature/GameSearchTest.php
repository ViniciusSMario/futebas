<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSearchTest extends TestCase
{
    use RefreshDatabase;

    private function organizer(): User
    {
        return User::factory()->organizer()->create();
    }

    private function createGame(User $organizer, array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Furacão FC',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'modality' => 'Society',
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'max_players' => 10,
            'price' => '25.00',
            'positions' => ['Goleiro', 'Zagueiro'],
            'status' => Game::STATUS_OPEN,
        ], $attributes));
    }

    /** Fill a game to capacity with confirmed participants. */
    private function fillGame(Game $game): void
    {
        foreach (range(1, $game->max_players) as $i) {
            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => User::factory()->create()->id,
                'status' => GamePlayer::STATUS_CONFIRMED,
                'payment_status' => GamePlayer::PAYMENT_PENDING,
                'amount_due' => $game->price,
                'joined_at' => now(),
            ]);
        }
    }

    public function test_guests_cannot_access_game_search(): void
    {
        $this->get('/games/search')->assertRedirect('/login');
    }

    public function test_search_lists_open_upcoming_games_with_a_link_to_join(): void
    {
        $player = User::factory()->create();
        $game = $this->createGame($this->organizer(), ['location' => 'Quadra do Bairro Novo']);

        $response = $this->actingAs($player)->get('/games/search');

        $response->assertOk();
        $response->assertSee('Quadra do Bairro Novo');
        $response->assertSee(route('public-games.show', $game));
    }

    public function test_search_shows_an_empty_state_when_there_is_nothing_to_join(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/games/search');

        $response->assertOk();
        $response->assertSee('Nenhuma partida encontrada');
        $response->assertSee('Ainda não há peladas abertas por aqui.', false);
    }

    public function test_organizers_can_also_search_games(): void
    {
        $viewer = $this->organizer();
        $this->createGame($this->organizer(), ['location' => 'Quadra Aberta']);

        $response = $this->actingAs($viewer)->get('/games/search');

        $response->assertOk();
        $response->assertSee('Quadra Aberta');
    }

    public function test_search_hides_the_users_own_games(): void
    {
        $organizer = $this->organizer();
        $this->createGame($organizer, ['location' => 'Quadra Propria']);
        $this->createGame($this->organizer(), ['location' => 'Quadra Alheia']);

        $response = $this->actingAs($organizer)->get('/games/search');

        $response->assertOk();
        $response->assertDontSee('Quadra Propria');
        $response->assertSee('Quadra Alheia');
    }

    public function test_search_hides_games_the_user_already_joined(): void
    {
        $player = User::factory()->create();
        $joined = $this->createGame($this->organizer(), ['location' => 'Quadra Ja Confirmada']);
        $this->createGame($this->organizer(), ['location' => 'Quadra Disponivel']);

        GamePlayer::create([
            'game_id' => $joined->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_WAITING_LIST,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $joined->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($player)->get('/games/search');

        $response->assertOk();
        $response->assertDontSee('Quadra Ja Confirmada');
        $response->assertSee('Quadra Disponivel');
    }

    public function test_search_shows_again_a_game_the_user_cancelled_out_of(): void
    {
        $player = User::factory()->create();
        $game = $this->createGame($this->organizer(), ['location' => 'Quadra Reaberta']);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CANCELLED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($player)->get('/games/search')->assertSee('Quadra Reaberta');
    }

    public function test_search_hides_cancelled_and_finished_games(): void
    {
        $player = User::factory()->create();
        $organizer = $this->organizer();
        $this->createGame($organizer, ['location' => 'Quadra Cancelada', 'status' => Game::STATUS_CANCELLED]);
        $this->createGame($organizer, ['location' => 'Quadra Finalizada', 'status' => Game::STATUS_FINISHED]);
        $this->createGame($organizer, ['location' => 'Quadra Aberta']);

        $response = $this->actingAs($player)->get('/games/search');

        $response->assertDontSee('Quadra Cancelada');
        $response->assertDontSee('Quadra Finalizada');
        $response->assertSee('Quadra Aberta');
    }

    public function test_search_hides_games_that_already_started(): void
    {
        $this->travelTo(today()->setTime(12, 0));

        $player = User::factory()->create();
        $organizer = $this->organizer();
        $this->createGame($organizer, ['location' => 'Quadra de Ontem', 'date' => today()->subDay()->format('Y-m-d')]);
        $this->createGame($organizer, ['location' => 'Quadra Ja Comecou', 'date' => today()->format('Y-m-d'), 'start_time' => '10:00']);
        $this->createGame($organizer, ['location' => 'Quadra Hoje a Noite', 'date' => today()->format('Y-m-d'), 'start_time' => '19:00']);

        $response = $this->actingAs($player)->get('/games/search');

        $response->assertDontSee('Quadra de Ontem');
        $response->assertDontSee('Quadra Ja Comecou');
        $response->assertSee('Quadra Hoje a Noite');
    }

    public function test_can_filter_games_by_city(): void
    {
        $player = User::factory()->create();
        $organizer = $this->organizer();
        $this->createGame($organizer, ['location' => 'Quadra Teresina', 'city' => 'Teresina']);
        $this->createGame($organizer, ['location' => 'Quadra Parnaiba', 'city' => 'Parnaíba']);

        $response = $this->actingAs($player)->get('/games/search?city=Teresina');

        $response->assertSee('Quadra Teresina');
        $response->assertDontSee('Quadra Parnaiba');
    }

    public function test_can_filter_games_by_modality(): void
    {
        $player = User::factory()->create();
        $organizer = $this->organizer();
        $this->createGame($organizer, ['location' => 'Quadra Futsal', 'modality' => 'Futsal']);
        $this->createGame($organizer, ['location' => 'Quadra Campo', 'modality' => 'Campo']);

        $response = $this->actingAs($player)->get('/games/search?modality=Futsal');

        $response->assertSee('Quadra Futsal');
        $response->assertDontSee('Quadra Campo');
    }

    public function test_position_filter_matches_wanted_positions_and_open_games(): void
    {
        $player = User::factory()->create();
        $organizer = $this->organizer();
        $this->createGame($organizer, ['location' => 'Quadra Procura Goleiro', 'positions' => ['Goleiro']]);
        $this->createGame($organizer, ['location' => 'Quadra Procura Atacante', 'positions' => ['Atacante']]);
        $this->createGame($organizer, ['location' => 'Quadra Qualquer Posicao', 'positions' => []]);

        $response = $this->actingAs($player)->get('/games/search?position=Goleiro');

        $response->assertSee('Quadra Procura Goleiro');
        $response->assertSee('Quadra Qualquer Posicao');
        $response->assertDontSee('Quadra Procura Atacante');
    }

    public function test_can_filter_games_by_max_price(): void
    {
        $player = User::factory()->create();
        $organizer = $this->organizer();
        $this->createGame($organizer, ['location' => 'Quadra Barata', 'price' => '20.00']);
        $this->createGame($organizer, ['location' => 'Quadra Cara', 'price' => '80.00']);

        $response = $this->actingAs($player)->get('/games/search?max_price=30');

        $response->assertSee('Quadra Barata');
        $response->assertDontSee('Quadra Cara');
    }

    public function test_can_filter_games_happening_today(): void
    {
        $this->travelTo(today()->setTime(8, 0));

        $player = User::factory()->create();
        $organizer = $this->organizer();
        $this->createGame($organizer, ['location' => 'Quadra de Hoje', 'date' => today()->format('Y-m-d'), 'start_time' => '20:00']);
        $this->createGame($organizer, ['location' => 'Quadra de Amanha', 'date' => today()->addDay()->format('Y-m-d')]);

        $response = $this->actingAs($player)->get('/games/search?period=today');

        $response->assertSee('Quadra de Hoje');
        $response->assertDontSee('Quadra de Amanha');
    }

    public function test_weekend_filter_only_returns_saturday_and_sunday_games(): void
    {
        // A Wednesday, so the upcoming weekend is unambiguous.
        $this->travelTo(today()->next('Wednesday')->setTime(9, 0));

        $player = User::factory()->create();
        $organizer = $this->organizer();
        $this->createGame($organizer, ['location' => 'Quadra de Sabado', 'date' => today()->next('Saturday')->format('Y-m-d')]);
        $this->createGame($organizer, ['location' => 'Quadra de Quinta', 'date' => today()->next('Thursday')->format('Y-m-d')]);

        $response = $this->actingAs($player)->get('/games/search?period=weekend');

        $response->assertSee('Quadra de Sabado');
        $response->assertDontSee('Quadra de Quinta');
    }

    public function test_with_spots_filter_hides_full_games(): void
    {
        $player = User::factory()->create();
        $organizer = $this->organizer();
        $full = $this->createGame($organizer, ['location' => 'Quadra Lotada', 'max_players' => 2]);
        $this->fillGame($full);
        $this->createGame($organizer, ['location' => 'Quadra Com Vaga']);

        $response = $this->actingAs($player)->get('/games/search?with_spots=1');

        $response->assertSee('Quadra Com Vaga');
        $response->assertDontSee('Quadra Lotada');
    }

    public function test_full_games_are_listed_by_default_as_a_waiting_list_option(): void
    {
        $player = User::factory()->create();
        $full = $this->createGame($this->organizer(), ['location' => 'Quadra Lotada', 'max_players' => 2]);
        $this->fillGame($full);

        $response = $this->actingAs($player)->get('/games/search');

        $response->assertSee('Quadra Lotada');
        $response->assertSee('Entrar na lista de espera');
    }

    public function test_text_query_matches_team_location_and_city(): void
    {
        $player = User::factory()->create();
        $organizer = $this->organizer();
        $this->createGame($organizer, ['team_name' => 'Leões do Norte', 'location' => 'Ginasio A']);
        $this->createGame($organizer, ['team_name' => 'Tigres FC', 'location' => 'Ginasio B']);

        $response = $this->actingAs($player)->get('/games/search?q=Leões');

        $response->assertSee('Ginasio A');
        $response->assertDontSee('Ginasio B');
    }
}
