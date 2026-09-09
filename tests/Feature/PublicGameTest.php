<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicGameTest extends TestCase
{
    use RefreshDatabase;

    private function createGame(User $organizer, array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Futebol de Quarta',
            'location' => 'Arena X',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '20:00',
            'end_time' => '21:00',
            'max_players' => 2,
            'price' => '10.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
            'requires_approval' => false,
        ], $attributes));
    }

    public function test_the_public_page_is_visible_to_guests(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $response = $this->get(route('public-games.show', $game));

        $response->assertOk();
        $response->assertSee('Futebol de Quarta');
        $response->assertSee('Jogar sem cadastro');
        $response->assertSee('Criar Conta e Participar');
    }

    public function test_the_public_page_is_still_visible_to_an_authenticated_user_who_has_not_requested_yet(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $response = $this->actingAs($player)->get(route('public-games.show', $game));

        $response->assertOk();
    }

    public function test_an_authenticated_user_with_an_active_request_is_redirected_to_minhas_partidas(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, ['requires_approval' => true, 'max_players' => 1]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_PENDING,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($player)->get(route('public-games.show', $game));

        $response->assertRedirect(route('games.mine'));
    }

    public function test_an_authenticated_user_whose_request_was_cancelled_can_see_the_public_page_again(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CANCELLED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($player)->get(route('public-games.show', $game));

        $response->assertOk();
    }

    public function test_authenticated_player_joins_directly_from_the_public_link(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $response = $this->actingAs($player)->get(route('public-games.join', $game));

        $response->assertRedirect(route('public-games.show', $game));
        $this->assertSame(GamePlayer::STATUS_CONFIRMED, GamePlayer::where('game_id', $game->id)->where('user_id', $player->id)->first()->status);
    }

    public function test_authenticated_player_joins_waiting_list_when_the_game_is_full(): void
    {
        $organizer = User::factory()->organizer()->create();
        $confirmedPlayer = User::factory()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, ['max_players' => 1]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $confirmedPlayer->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($player)->get(route('public-games.join', $game));

        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, GamePlayer::where('game_id', $game->id)->where('user_id', $player->id)->first()->status);
    }

    public function test_guest_is_sent_to_register_and_joins_the_game_after_registering(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $joinResponse = $this->get(route('public-games.join', $game));
        $joinResponse->assertRedirect(route('register'));

        $registerResponse = $this->post('/register', [
            'role' => 'player',
            'name' => 'Jogador Avulso',
            'email' => 'avulso@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'birth_date' => '1995-05-10',
            'state' => 'PI',
            'city' => 'Teresina',
            'phone' => '86988887777',
            'position_primary' => 'Atacante',
            'modalities' => ['Society'],
            'level' => 'Avançado',
            'price_per_game' => '10.00',
            'days' => [1, 3],
            'start_time' => '18:00',
            'end_time' => '20:00',
        ]);

        $registerResponse->assertRedirect(route('public-games.show', $game));

        $user = User::where('email', 'avulso@example.com')->firstOrFail();
        $gamePlayer = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($gamePlayer);
        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $gamePlayer->status);
    }

    public function test_guest_who_already_has_an_account_joins_the_game_after_logging_in(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);
        $player = User::factory()->create();

        $loginLinkResponse = $this->get(route('public-games.login', $game));
        $loginLinkResponse->assertRedirect(route('login'));

        $loginResponse = $this->post('/login', [
            'email' => $player->email,
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect(route('public-games.show', $game));
        $this->assertSame(GamePlayer::STATUS_CONFIRMED, GamePlayer::where('game_id', $game->id)->where('user_id', $player->id)->first()->status);
    }

    public function test_joining_a_cancelled_game_is_rejected(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, ['status' => Game::STATUS_CANCELLED]);

        $response = $this->actingAs($player)->get(route('public-games.join', $game));

        $response->assertRedirect(route('public-games.show', $game));
        $this->assertSame(0, GamePlayer::where('game_id', $game->id)->count());
    }

    public function test_register_page_hides_the_organizer_option_and_forces_player_role_when_a_game_is_pending(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $this->get(route('public-games.join', $game));

        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee($game->team_name);
        $response->assertDontSee('Preciso encontrar jogadores para completar minhas partidas.');
        $response->assertDontSee('Escolher outro perfil');
    }

    public function test_register_page_offers_both_roles_without_a_pending_game(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee(__('Organizador'));
        $response->assertSee(__('Jogador'));
    }

    public function test_guest_can_join_without_an_account_by_providing_just_a_name(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $response = $this->post(route('public-games.join-guest', $game), [
            'name' => 'Fabrício Lemos',
            'phone' => '86999998888',
        ]);

        $response->assertRedirect(route('public-games.show', $game));
        $this->assertGuest();

        $guestPlayer = GuestPlayer::where('organizer_id', $organizer->id)->where('name', 'Fabrício Lemos')->first();
        $this->assertNotNull($guestPlayer);
        $this->assertSame('86999998888', $guestPlayer->phone);

        $gamePlayer = GamePlayer::where('game_id', $game->id)->where('guest_player_id', $guestPlayer->id)->first();
        $this->assertNotNull($gamePlayer);
        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $gamePlayer->status);
    }

    public function test_guest_joining_without_an_account_respects_capacity_and_waiting_list(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['max_players' => 1]);
        $confirmedPlayer = User::factory()->create();

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $confirmedPlayer->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->post(route('public-games.join-guest', $game), ['name' => 'Fabrício Lemos']);

        $guestPlayer = GuestPlayer::where('name', 'Fabrício Lemos')->first();
        $gamePlayer = GamePlayer::where('guest_player_id', $guestPlayer->id)->first();
        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, $gamePlayer->status);
    }

    public function test_guest_joining_twice_with_the_same_name_and_phone_reuses_the_same_contact(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $this->post(route('public-games.join-guest', $game), ['name' => 'Fabrício Lemos', 'phone' => '86999998888']);
        $this->post(route('public-games.join-guest', $game), ['name' => 'Fabrício Lemos', 'phone' => '86999998888']);

        $this->assertSame(1, GuestPlayer::count());
        $this->assertSame(1, GamePlayer::where('game_id', $game->id)->count());
    }
}
