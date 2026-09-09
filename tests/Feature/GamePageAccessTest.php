<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTeam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tela da partida deixou de ser só do organizador.
 *
 * Antes, quem jogava não tinha partida nenhuma para abrir: sabia da pelada
 * o que cabia no cartão de "Minhas Partidas", e nem quem mais ia, nem o
 * time em que caiu, nem o endereço para chegar lá. O que este arquivo
 * fixa é o par que faz isso valer a pena — que o participante entra, e que
 * entrando ele não recebe junto a papelada do organizador.
 */
class GamePageAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->organizer()->create(['name' => 'Zé Organizador']);
    }

    public function test_the_organizer_opens_their_own_game(): void
    {
        $game = $this->game();

        $this->actingAs($this->organizer)
            ->get(route('games.show', $game))
            ->assertOk()
            ->assertSee('Furacão FC');
    }

    public function test_a_confirmed_participant_opens_the_game(): void
    {
        $game = $this->game();
        $player = User::factory()->create(['name' => 'Rafael Jogador']);
        $this->join($game, $player);

        $this->actingAs($player)
            ->get(route('games.show', $game))
            ->assertOk()
            ->assertSee('Furacão FC');
    }

    /**
     * Estar na lista de espera também é estar na partida: é justamente
     * quem mais precisa acompanhar se abriu vaga.
     */
    public function test_someone_on_the_waiting_list_opens_the_game(): void
    {
        $game = $this->game();
        $player = User::factory()->create();
        $this->join($game, $player, GamePlayer::STATUS_WAITING_LIST);

        $this->actingAs($player)
            ->get(route('games.show', $game))
            ->assertOk();
    }

    public function test_a_stranger_is_forbidden(): void
    {
        $game = $this->game();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('games.show', $game))
            ->assertForbidden();
    }

    /**
     * Quem cancelou a própria vaga, ou foi removido pelo organizador,
     * deixa de ser participante — a mesma leitura que `hasParticipant()`
     * já tem em todo o resto do app.
     */
    public function test_a_cancelled_participant_is_forbidden(): void
    {
        $game = $this->game();
        $player = User::factory()->create();
        $this->join($game, $player, GamePlayer::STATUS_CANCELLED);

        $this->actingAs($player)
            ->get(route('games.show', $game))
            ->assertForbidden();
    }

    public function test_another_organizer_cannot_open_someone_elses_game(): void
    {
        $game = $this->game();
        $other = User::factory()->organizer()->create();

        $this->actingAs($other)
            ->get(route('games.show', $game))
            ->assertForbidden();
    }

    /**
     * Convites e Pagamentos são as duas abas que só existem para
     * administrar. Não basta escondê-las do menu: pedir a aba pela URL
     * também não pode entregá-la.
     */
    public function test_the_admin_tabs_are_neither_shown_nor_reachable_by_a_participant(): void
    {
        $game = $this->game();
        $player = User::factory()->create();
        $this->join($game, $player);

        $response = $this->actingAs($player)->get(route('games.show', $game));

        $response->assertOk();
        $response->assertDontSee(route('games.show', ['game' => $game, 'tab' => 'pagamentos']));
        $response->assertDontSee(route('games.show', ['game' => $game, 'tab' => 'convites']));

        // Pedida na mão, a aba de pagamentos cai em "Informações" em vez de
        // renderizar o resumo financeiro da partida. E "Informações" para
        // quem joga também não é a do organizador: o link público, que é
        // dele para divulgar, não aparece.
        $this->actingAs($player)
            ->get(route('games.show', ['game' => $game, 'tab' => 'pagamentos']))
            ->assertOk()
            ->assertDontSee('Total arrecadado')
            ->assertDontSee('Link público');
    }

    public function test_a_participant_sees_who_else_is_playing(): void
    {
        $game = $this->game();
        $player = User::factory()->create(['name' => 'Rafael Jogador']);
        $teammate = User::factory()->create(['name' => 'Gustavo Meia']);
        $this->join($game, $player);
        $this->join($game, $teammate);

        $this->actingAs($player)
            ->get(route('games.show', ['game' => $game, 'tab' => 'participantes']))
            ->assertOk()
            ->assertSee('Gustavo Meia');
    }

    /**
     * O que o participante não vê na mesma lista: quanto os outros devem e
     * os botões que mexem nisso.
     */
    public function test_a_participant_does_not_see_the_money_or_the_admin_actions(): void
    {
        $game = $this->game();
        $player = User::factory()->create();
        $teammate = User::factory()->create(['name' => 'Gustavo Meia']);
        $this->join($game, $player);
        $this->join($game, $teammate);

        $response = $this->actingAs($player)
            ->get(route('games.show', ['game' => $game, 'tab' => 'participantes']));

        $response->assertOk();
        $response->assertDontSee('Marcar como Pago');
        $response->assertDontSee('Adicionar Jogador');
        $response->assertDontSee('Remover');
    }

    public function test_a_participant_sees_the_drawn_teams_but_not_the_draw_form(): void
    {
        $game = $this->game();
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player);

        $team = GameTeam::create(['game_id' => $game->id, 'name' => 'Time A']);
        $gamePlayer->update(['game_team_id' => $team->id]);

        $response = $this->actingAs($player)
            ->get(route('games.show', ['game' => $game, 'tab' => 'times']));

        $response->assertOk();
        $response->assertSee('Time A');
        $response->assertDontSee('Sortear Times');
    }

    /**
     * `games.show` mudou para o grupo compartilhado, que é registrado antes
     * do grupo do organizador. Sem o `whereNumber` na rota, `/games/create`
     * casaria com `/games/{game}` e a ação principal de quem organiza
     * viraria um 404.
     */
    public function test_the_create_page_still_resolves_after_the_show_route_moved(): void
    {
        $this->actingAs($this->organizer)
            ->get('/games/create')
            ->assertOk();
    }

    // ==================== APOIO ====================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function game(array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $this->organizer->id,
            'team_name' => 'Furacão FC',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => today()->addDays(3)->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'max_players' => 10,
            'price' => '25.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
        ], $attributes));
    }

    private function join(Game $game, User $user, string $status = GamePlayer::STATUS_CONFIRMED): GamePlayer
    {
        return GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'status' => $status,
            'amount_due' => $game->price,
            'joined_at' => now()->subDay(),
        ]);
    }
}
