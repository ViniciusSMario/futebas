<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Game;
use App\Models\Invitation;
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

    private function createGame(User $organizer): Game
    {
        return Game::create([
            'user_id' => $organizer->id,
            'team_name' => 'Amigos FC',
            'location' => 'Arena X',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '20:00',
            'end_time' => '21:00',
            'max_players' => 10,
            'price' => '50.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
        ]);
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

        // O filtro virou select do catálogo do IBGE, então o valor chega
        // inteiro e a comparação é exata: meia palavra não filtra mais nada.
        $response = $this->actingAs($viewer)->get('/players/search?city=Rio+de+Janeiro');

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

    public function test_can_find_a_player_by_part_of_the_name(): void
    {
        $viewer = $this->organizer();
        $this->createPlayer(['name' => 'Gustavo Lima']);
        $this->createPlayer(['name' => 'Carlos Andrade']);

        $response = $this->actingAs($viewer)->get('/players/search?q=gust');

        $response->assertOk();
        $response->assertSee('Gustavo Lima');
        $response->assertDontSee('Carlos Andrade');
    }

    public function test_searching_for_a_game_hides_who_is_already_invited(): void
    {
        $viewer = $this->organizer();
        $game = $this->createGame($viewer);

        $convidado = $this->createPlayer(['name' => 'Ja Convidado']);
        $livre = $this->createPlayer(['name' => 'Ainda Livre']);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $viewer->id,
            'user_id' => $convidado->user_id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($viewer)->get('/players/search?game='.$game->id);

        $response->assertOk();
        $response->assertDontSee('Ja Convidado');
        $response->assertSee('Ainda Livre');
        // Com partida em vista o card convida, em vez de só levar ao perfil.
        $response->assertSee(route('games.invitations.store', [$game, $livre]), false);
    }

    public function test_another_organizers_game_is_ignored_as_search_context(): void
    {
        $viewer = $this->organizer();
        $alheio = $this->createGame($this->organizer());

        $inGame = $this->createPlayer(['name' => 'Jogador Alheio']);
        $alheio->gamePlayers()->create([
            'user_id' => $inGame->user_id,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'amount_due' => '0.00',
            'joined_at' => now(),
        ]);

        // A partida não é dele: o contexto simplesmente não vale, e a busca
        // volta a ser a busca comum - nada de esconder gente por causa de uma
        // partida que ele não organiza.
        $response = $this->actingAs($viewer)->get('/players/search?game='.$alheio->id);

        $response->assertOk();
        $response->assertSee('Jogador Alheio');
    }

    public function test_advanced_filters_stay_folded_when_none_is_set(): void
    {
        $response = $this->actingAs($this->organizer())->get('/players/search?position=Goleiro');

        $response->assertOk();
        $response->assertSee('advanced: false', false);
    }

    public function test_advanced_filters_unfold_when_one_of_them_is_set(): void
    {
        // Um filtro valendo e escondido é a forma mais rápida de a busca
        // parecer quebrada: com "Nível" aplicado, o painel abre sozinho.
        $response = $this->actingAs($this->organizer())->get('/players/search?level=Avançado');

        $response->assertOk();
        $response->assertSee('advanced: true', false);
    }
}
