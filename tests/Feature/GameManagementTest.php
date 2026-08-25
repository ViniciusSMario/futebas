<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTeam;
use App\Models\GuestPlayer;
use App\Models\Invitation;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createGame(User $organizer, array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Futebol de Quarta',
            'location' => 'Arena X',
            'city' => 'Teresina',
            'modality' => 'Society',
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '20:00',
            'end_time' => '21:00',
            'max_players' => 2,
            'price' => '10.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
            'requires_approval' => true,
        ], $attributes));
    }

    private function createPlayerProfile(User $player, array $attributes = []): PlayerProfile
    {
        return PlayerProfile::create(array_merge([
            'user_id' => $player->id,
            'birth_date' => '1998-01-01',
            'city' => 'Teresina',
            'phone' => '86999999999',
            'positions' => ['Goleiro'],
            'modalities' => ['Society'],
            'level' => 'Intermediário',
            'price_per_game' => '10.00',
            'plays_outside_city' => false,
        ], $attributes));
    }

    public function test_organizer_can_view_the_management_panel_with_a_public_link(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $response = $this->actingAs($organizer)->get(route('games.show', $game));

        $response->assertOk();
        $response->assertSee($game->team_name);
        $response->assertSee(route('public-games.show', $game));
    }

    public function test_all_tabs_of_the_management_panel_render_without_errors(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create(['name' => 'Lucas Silva']);
        $game = $this->createGame($organizer, ['max_players' => 4]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $waitingPlayer = User::factory()->create();
        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $waitingPlayer->id,
            'status' => GamePlayer::STATUS_WAITING_LIST,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $invitedPlayer = User::factory()->create();
        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $invitedPlayer->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $this->actingAs($organizer)->post(route('game-teams.draw', $game), ['teams_count' => 2]);

        foreach (['informacoes', 'participantes', 'convites', 'pagamentos', 'times'] as $tab) {
            $response = $this->actingAs($organizer)->get(route('games.show', ['game' => $game, 'tab' => $tab]));
            $response->assertOk();

            if (in_array($tab, ['participantes', 'pagamentos', 'times'], true)) {
                $response->assertSee('Lucas Silva');
            }
        }
    }

    public function test_a_different_organizer_cannot_view_the_management_panel(): void
    {
        $organizer = User::factory()->organizer()->create();
        $otherOrganizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $response = $this->actingAs($otherOrganizer)->get(route('games.show', $game));

        $response->assertForbidden();
    }

    public function test_organizer_can_add_an_existing_user_manually_and_it_is_confirmed_immediately(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create(['name' => 'Lucas Silva']);
        $game = $this->createGame($organizer);

        $response = $this->actingAs($organizer)->post(route('game-players.store', $game), [
            'user_id' => $player->id,
        ]);

        $response->assertRedirect();
        $gamePlayer = GamePlayer::where('game_id', $game->id)->where('user_id', $player->id)->firstOrFail();
        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $gamePlayer->status);
        $this->assertSame('10.00', $gamePlayer->amount_due);
    }

    public function test_manual_add_goes_to_waiting_list_once_the_game_is_full(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['max_players' => 1]);

        $firstPlayer = User::factory()->create();
        $secondPlayer = User::factory()->create();

        $this->actingAs($organizer)->post(route('game-players.store', $game), ['user_id' => $firstPlayer->id]);
        $this->actingAs($organizer)->post(route('game-players.store', $game), ['user_id' => $secondPlayer->id]);

        $this->assertSame(GamePlayer::STATUS_CONFIRMED, GamePlayer::where('user_id', $firstPlayer->id)->first()->status);
        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, GamePlayer::where('user_id', $secondPlayer->id)->first()->status);
    }

    public function test_organizer_can_add_a_brand_new_guest_contact_and_it_is_confirmed_immediately(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $response = $this->actingAs($organizer)->post(route('game-players.store', $game), [
            'new_guest_name' => 'Fabrício Lemos',
            'new_guest_phone' => '86999998888',
        ]);

        $response->assertRedirect();

        $guestPlayer = GuestPlayer::where('organizer_id', $organizer->id)->where('name', 'Fabrício Lemos')->first();
        $this->assertNotNull($guestPlayer);
        $this->assertSame('86999998888', $guestPlayer->phone);

        $gamePlayer = GamePlayer::where('game_id', $game->id)->where('guest_player_id', $guestPlayer->id)->first();
        $this->assertNotNull($gamePlayer);
        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $gamePlayer->status);
        $this->assertNull($gamePlayer->user_id);
    }

    public function test_a_guest_contact_can_be_reused_across_different_games(): void
    {
        $organizer = User::factory()->organizer()->create();
        $firstGame = $this->createGame($organizer, ['team_name' => 'Jogo 1']);
        $secondGame = $this->createGame($organizer, ['team_name' => 'Jogo 2']);

        $this->actingAs($organizer)->post(route('game-players.store', $firstGame), [
            'new_guest_name' => 'Fabrício Lemos',
        ]);

        $this->assertSame(1, GuestPlayer::count());
        $guestPlayer = GuestPlayer::first();

        $this->actingAs($organizer)->post(route('game-players.store', $secondGame), [
            'guest_player_id' => $guestPlayer->id,
        ]);

        $this->assertSame(1, GuestPlayer::count());
        $this->assertSame(2, GamePlayer::where('guest_player_id', $guestPlayer->id)->count());
    }

    public function test_a_guest_contact_goes_to_waiting_list_once_the_game_is_full(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['max_players' => 1]);
        $confirmedPlayer = User::factory()->create();

        $this->actingAs($organizer)->post(route('game-players.store', $game), ['user_id' => $confirmedPlayer->id]);
        $this->actingAs($organizer)->post(route('game-players.store', $game), ['new_guest_name' => 'Fabrício Lemos']);

        $guestPlayer = GuestPlayer::where('name', 'Fabrício Lemos')->first();
        $gamePlayer = GamePlayer::where('guest_player_id', $guestPlayer->id)->first();
        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, $gamePlayer->status);
    }

    public function test_organizer_cannot_reuse_another_organizers_guest_contact(): void
    {
        $organizer = User::factory()->organizer()->create();
        $otherOrganizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $guestPlayer = GuestPlayer::create([
            'organizer_id' => $otherOrganizer->id,
            'name' => 'Contato Alheio',
        ]);

        $response = $this->actingAs($organizer)->post(route('game-players.store', $game), [
            'guest_player_id' => $guestPlayer->id,
        ]);

        $response->assertSessionHasErrors('guest_player_id');
        $this->assertSame(0, GamePlayer::where('game_id', $game->id)->count());
    }

    public function test_a_removed_guest_contact_row_stays_in_the_registry_and_out_of_the_search(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $this->actingAs($organizer)->post(route('game-players.store', $game), ['new_guest_name' => 'Fabrício Lemos']);
        $guestPlayer = GuestPlayer::where('name', 'Fabrício Lemos')->first();
        $gamePlayer = GamePlayer::where('guest_player_id', $guestPlayer->id)->first();

        $this->actingAs($organizer)->delete(route('game-players.destroy', [$game, $gamePlayer]));

        $this->assertSame(1, GuestPlayer::count());

        $response = $this->actingAs($organizer)->get(route('game-players.create', ['game' => $game, 'q' => 'Fabrício']));
        $response->assertOk();
        $response->assertSee('Fabrício Lemos');
    }

    public function test_organizer_can_search_and_invite_a_player_from_within_the_game(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $playerProfile = $this->createPlayerProfile($player);
        $game = $this->createGame($organizer);

        $response = $this->actingAs($organizer)->post(route('games.invitations.store', [$game, $playerProfile]), [
            'position' => 'Goleiro',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Invitation::where('game_id', $game->id)->where('user_id', $player->id)->count());
    }

    public function test_accepting_an_invitation_creates_a_pending_participation_when_the_game_requires_approval(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, ['requires_approval' => true]);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $this->actingAs($player)->patch(route('invitations.accept', $invitation));

        $gamePlayer = GamePlayer::where('game_id', $game->id)->where('user_id', $player->id)->firstOrFail();
        $this->assertSame(GamePlayer::STATUS_PENDING, $gamePlayer->status);
    }

    public function test_accepting_an_invitation_confirms_directly_when_the_game_does_not_require_approval(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, ['requires_approval' => false]);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $this->actingAs($player)->patch(route('invitations.accept', $invitation));

        $gamePlayer = GamePlayer::where('game_id', $game->id)->where('user_id', $player->id)->firstOrFail();
        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $gamePlayer->status);
    }

    public function test_accepting_an_invitation_goes_to_waiting_list_once_the_game_is_full_regardless_of_approval(): void
    {
        $organizer = User::factory()->organizer()->create();
        $confirmedPlayer = User::factory()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, ['max_players' => 1, 'requires_approval' => false]);

        $this->actingAs($organizer)->post(route('game-players.store', $game), ['user_id' => $confirmedPlayer->id]);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $this->actingAs($player)->patch(route('invitations.accept', $invitation));

        $gamePlayer = GamePlayer::where('game_id', $game->id)->where('user_id', $player->id)->firstOrFail();
        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, $gamePlayer->status);
    }

    public function test_organizer_can_confirm_a_waiting_list_participant(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $gamePlayer = GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_WAITING_LIST,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($organizer)->patch(route('game-players.confirm', [$game, $gamePlayer]));

        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $gamePlayer->fresh()->status);
    }

    public function test_organizer_can_toggle_a_participants_payment_status(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $gamePlayer = GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($organizer)->patch(route('game-players.payment', [$game, $gamePlayer]));
        $this->assertSame(GamePlayer::PAYMENT_PAID, $gamePlayer->fresh()->payment_status);

        $this->actingAs($organizer)->patch(route('game-players.payment', [$game, $gamePlayer]));
        $this->assertSame(GamePlayer::PAYMENT_PENDING, $gamePlayer->fresh()->payment_status);
    }

    public function test_organizer_can_remove_a_participant_without_deleting_the_row(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $gamePlayer = GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($organizer)->delete(route('game-players.destroy', [$game, $gamePlayer]));

        $this->assertSame(GamePlayer::STATUS_CANCELLED, $gamePlayer->fresh()->status);
        $this->assertSame(1, GamePlayer::count());
    }

    public function test_player_can_leave_a_game_they_joined(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($player)->delete(route('games.leave', $game));

        $response->assertRedirect(route('games.mine'));
        $this->assertSame(GamePlayer::STATUS_CANCELLED, GamePlayer::where('game_id', $game->id)->where('user_id', $player->id)->first()->status);
    }

    public function test_organizer_can_draw_teams_from_confirmed_players(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['max_players' => 4]);

        collect(range(1, 4))->each(function (int $i) use ($game, $organizer) {
            $player = User::factory()->create();
            $this->actingAs($organizer)->post(route('game-players.store', $game), ['user_id' => $player->id]);
        });

        $response = $this->actingAs($organizer)->post(route('game-teams.draw', $game), ['teams_count' => 2]);

        $response->assertRedirect();
        $this->assertSame(2, GameTeam::where('game_id', $game->id)->count());
        $this->assertSame(4, GamePlayer::where('game_id', $game->id)->whereNotNull('game_team_id')->count());
    }

    public function test_redrawing_teams_fully_replaces_the_previous_draw(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['max_players' => 4]);

        collect(range(1, 4))->each(function (int $i) use ($game, $organizer) {
            $player = User::factory()->create();
            $this->actingAs($organizer)->post(route('game-players.store', $game), ['user_id' => $player->id]);
        });

        $this->actingAs($organizer)->post(route('game-teams.draw', $game), ['teams_count' => 2]);
        $firstDrawTeamIds = GameTeam::where('game_id', $game->id)->pluck('id');

        $this->actingAs($organizer)->post(route('game-teams.draw', $game), ['teams_count' => 4]);

        $this->assertSame(4, GameTeam::where('game_id', $game->id)->count());
        $this->assertSame(0, GameTeam::whereIn('id', $firstDrawTeamIds)->count());
    }

    public function test_non_owner_organizer_cannot_manage_participants_or_teams(): void
    {
        $organizer = User::factory()->organizer()->create();
        $intruder = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $gamePlayer = GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($intruder)->post(route('game-players.store', $game), ['user_id' => $player->id])->assertForbidden();
        $this->actingAs($intruder)->patch(route('game-players.payment', [$game, $gamePlayer]))->assertForbidden();
        $this->actingAs($intruder)->delete(route('game-players.destroy', [$game, $gamePlayer]))->assertForbidden();
        $this->actingAs($intruder)->post(route('game-teams.draw', $game), ['teams_count' => 2])->assertForbidden();
        $this->actingAs($intruder)->patch(route('games.cancel', $game))->assertForbidden();
    }
}
