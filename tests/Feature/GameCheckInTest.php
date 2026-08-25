<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\User;
use App\Services\GamePlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCheckInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A match starting in the given number of hours from now, so the
     * check-in window's position is explicit in every test.
     */
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

    private function join(Game $game, User $user, array $attributes = []): GamePlayer
    {
        return GamePlayer::create(array_merge([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ], $attributes));
    }

    public function test_guests_cannot_check_in(): void
    {
        $game = $this->createGame(User::factory()->create(), 4);

        $this->post(route('games.check-in', $game))->assertRedirect('/login');
    }

    public function test_confirmed_player_can_check_in_once_the_window_is_open(): void
    {
        // Starts in 4h, so the 12h window is already open.
        $game = $this->createGame(User::factory()->create(), 4);
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player);

        $response = $this->actingAs($player)->post(route('games.check-in', $game));

        $response->assertRedirect(route('games.mine'));
        $response->assertSessionHas('status', 'checked-in');
        $this->assertNotNull($gamePlayer->fresh()->checked_in_at);
    }

    public function test_player_cannot_check_in_before_the_window_opens(): void
    {
        // Starts in 30h — check-in only opens 12h before.
        $game = $this->createGame(User::factory()->create(), 30);
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player);

        $this->actingAs($player)->post(route('games.check-in', $game))->assertStatus(422);
        $this->assertNull($gamePlayer->fresh()->checked_in_at);
    }

    public function test_player_cannot_check_in_after_the_match_started(): void
    {
        $game = $this->createGame(User::factory()->create(), -1);
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player);

        $this->actingAs($player)->post(route('games.check-in', $game))->assertStatus(422);
        $this->assertNull($gamePlayer->fresh()->checked_in_at);
    }

    public function test_waiting_list_participant_cannot_check_in(): void
    {
        $game = $this->createGame(User::factory()->create(), 4);
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player, ['status' => GamePlayer::STATUS_WAITING_LIST]);

        $this->actingAs($player)->post(route('games.check-in', $game))->assertStatus(422);
        $this->assertNull($gamePlayer->fresh()->checked_in_at);
    }

    public function test_someone_who_is_not_in_the_game_cannot_check_in(): void
    {
        $game = $this->createGame(User::factory()->create(), 4);

        $this->actingAs(User::factory()->create())
            ->post(route('games.check-in', $game))
            ->assertNotFound();
    }

    public function test_cancelled_game_does_not_accept_check_in(): void
    {
        $game = $this->createGame(User::factory()->create(), 4, ['status' => Game::STATUS_CANCELLED]);
        $player = User::factory()->create();
        $this->join($game, $player);

        $this->actingAs($player)->post(route('games.check-in', $game))->assertStatus(422);
    }

    public function test_player_can_undo_a_check_in_while_the_window_is_open(): void
    {
        $game = $this->createGame(User::factory()->create(), 4);
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player, ['checked_in_at' => now()]);

        $response = $this->actingAs($player)->delete(route('games.check-in.undo', $game));

        $response->assertSessionHas('status', 'check-in-undone');
        $this->assertNull($gamePlayer->fresh()->checked_in_at);
    }

    public function test_check_in_cannot_be_undone_after_the_match_started(): void
    {
        $game = $this->createGame(User::factory()->create(), -1);
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player, ['checked_in_at' => now()->subHours(2)]);

        $this->actingAs($player)->delete(route('games.check-in.undo', $game))->assertStatus(422);
        $this->assertNotNull($gamePlayer->fresh()->checked_in_at);
    }

    public function test_checking_in_clears_a_no_show_the_organizer_had_marked(): void
    {
        $game = $this->createGame(User::factory()->create(), 4);
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player, ['no_show' => true]);

        $this->actingAs($player)->post(route('games.check-in', $game));

        $this->assertFalse($gamePlayer->fresh()->no_show);
    }

    public function test_check_in_button_shows_on_my_games_and_turns_into_a_confirmation(): void
    {
        $game = $this->createGame(User::factory()->create(), 4);
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player);

        $this->actingAs($player)->get('/games/mine')->assertSee('Confirmar presença');

        $gamePlayer->update(['checked_in_at' => now()]);

        $response = $this->actingAs($player)->get('/games/mine');
        $response->assertSee('Presença confirmada');
        $response->assertDontSee('Confirmar presença');
    }

    public function test_check_in_button_is_absent_before_the_window_opens(): void
    {
        $game = $this->createGame(User::factory()->create(), 30);
        $player = User::factory()->create();
        $this->join($game, $player);

        $this->actingAs($player)->get('/games/mine')->assertDontSee('Confirmar presença');
    }

    public function test_organizer_who_plays_can_check_in_from_their_own_game_card(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, 4);
        $gamePlayer = $this->join($game, $organizer);

        $this->actingAs($organizer)->get('/games/mine')->assertSee('Confirmar presença');

        $this->actingAs($organizer)->post(route('games.check-in', $game))->assertSessionHas('status', 'checked-in');
        $this->assertNotNull($gamePlayer->fresh()->checked_in_at);
    }

    public function test_dashboard_prompts_the_player_to_check_in_and_stops_once_confirmed(): void
    {
        $game = $this->createGame(User::factory()->create(), 4);
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player);

        $this->actingAs($player)->get('/dashboard')->assertSee('Você joga hoje!');

        $gamePlayer->update(['checked_in_at' => now()]);

        $this->actingAs($player)->get('/dashboard')->assertDontSee('Você joga hoje!');
    }

    public function test_organizer_sees_who_confirmed_and_who_did_not(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, 4);
        $this->join($game, User::factory()->create(['name' => 'Ana Presente']), ['checked_in_at' => now()]);
        $this->join($game, User::factory()->create(['name' => 'Bruno Ausente']));

        $response = $this->actingAs($organizer)->get(route('games.show', ['game' => $game, 'tab' => 'participantes']));

        $response->assertOk();
        $response->assertSee('1 de 2 confirmaram presença');
        $response->assertSeeInOrder(['Ana Presente', 'Presente']);
        $response->assertSee('Não confirmou');
    }

    public function test_guest_participants_are_never_shown_as_unconfirmed(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, 4);
        $guest = GuestPlayer::create(['organizer_id' => $organizer->id, 'name' => 'Convidado Zé']);

        GamePlayer::create([
            'game_id' => $game->id,
            'guest_player_id' => $guest->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($organizer)->get(route('games.show', ['game' => $game, 'tab' => 'participantes']));

        $response->assertSee('Convidado Zé');
        $response->assertDontSee('Não confirmou');
    }

    public function test_check_in_summary_is_hidden_before_the_window_opens(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, 30);
        $this->join($game, User::factory()->create());

        $this->actingAs($organizer)
            ->get(route('games.show', ['game' => $game, 'tab' => 'participantes']))
            ->assertDontSee('Presença de hoje');
    }

    public function test_organizer_can_mark_and_unmark_a_no_show(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, 4);
        $gamePlayer = $this->join($game, User::factory()->create());

        $response = $this->actingAs($organizer)->patch(route('game-players.no-show', [$game, $gamePlayer]));

        $response->assertSessionHas('status', 'no-show-updated');
        $this->assertTrue($gamePlayer->fresh()->no_show);

        $this->actingAs($organizer)->patch(route('game-players.no-show', [$game, $gamePlayer]));
        $this->assertFalse($gamePlayer->fresh()->no_show);
    }

    public function test_no_show_cannot_be_marked_before_the_check_in_window_opens(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, 30);
        $gamePlayer = $this->join($game, User::factory()->create());

        $this->actingAs($organizer)
            ->patch(route('game-players.no-show', [$game, $gamePlayer]))
            ->assertStatus(422);

        $this->assertFalse($gamePlayer->fresh()->no_show);
    }

    public function test_another_organizer_cannot_mark_a_no_show(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, 4);
        $gamePlayer = $this->join($game, User::factory()->create());

        $this->actingAs(User::factory()->organizer()->create())
            ->patch(route('game-players.no-show', [$game, $gamePlayer]))
            ->assertForbidden();

        $this->assertFalse($gamePlayer->fresh()->no_show);
    }

    public function test_players_cannot_mark_a_no_show(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, 4);
        $gamePlayer = $this->join($game, User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->patch(route('game-players.no-show', [$game, $gamePlayer]))
            ->assertForbidden();
    }

    public function test_rejoining_a_cancelled_participation_starts_a_fresh_attendance_record(): void
    {
        $game = $this->createGame(User::factory()->create(), 4);
        $player = User::factory()->create();
        $gamePlayer = $this->join($game, $player, [
            'status' => GamePlayer::STATUS_CANCELLED,
            'checked_in_at' => now()->subDay(),
            'no_show' => true,
        ]);

        app(GamePlayerService::class)->join($game, $player);

        $gamePlayer->refresh();
        $this->assertNull($gamePlayer->checked_in_at);
        $this->assertFalse($gamePlayer->no_show);
    }
}
