<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\Invitation;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Notifications\GameCancelled;
use App\Notifications\GamePlayerConfirmed;
use App\Notifications\GamePlayerJoined;
use App\Notifications\GameUpdated;
use App\Notifications\InvitationAnswered;
use App\Notifications\InvitationReceived;
use App\Notifications\PlayerRated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Which app events reach which person, over the database + web push
 * channels that already existed for SOS.
 */
class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function createGame(User $organizer, array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Furacão FC',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'max_players' => 10,
            'price' => '25.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
            'requires_approval' => false,
        ], $attributes));
    }

    private function createPlayer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        PlayerProfile::create([
            'user_id' => $user->id,
            'birth_date' => '1995-05-10',
            'state' => 'PI',
            'city' => 'Teresina',
            'phone' => '86999999999',
            'positions' => ['Atacante'],
            'modalities' => ['Society'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
            'plays_outside_city' => false,
        ]);

        return $user;
    }

    /** Payload the game edit form posts, with the given fields overridden. */
    private function updatePayload(Game $game, array $overrides = []): array
    {
        return array_merge([
            'team_name' => $game->team_name,
            'modality' => $game->modality,
            'date' => $game->date->format('Y-m-d'),
            'start_time' => $game->start_time->format('H:i'),
            'end_time' => $game->end_time?->format('H:i'),
            'location' => $game->location,
            'city' => $game->city,
            'state' => $game->state,
            'description' => $game->description,
            'max_players' => $game->max_players,
            'price' => (string) $game->price,
        ], $overrides);
    }

    private function join(Game $game, User $user, string $status = GamePlayer::STATUS_CONFIRMED): GamePlayer
    {
        return GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'status' => $status,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);
    }

    public function test_inviting_a_player_notifies_them(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = $this->createPlayer();
        $game = $this->createGame($organizer);

        $this->actingAs($organizer)->post(route('games.invitations.store', [$game, $player->playerProfile]));

        Notification::assertSentTo($player, InvitationReceived::class);
        Notification::assertNotSentTo($organizer, InvitationReceived::class);
    }

    public function test_inviting_from_the_player_profile_also_notifies(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = $this->createPlayer();
        $game = $this->createGame($organizer);

        $this->actingAs($organizer)->post(route('invitations.store', $player->playerProfile), ['game_id' => $game->id]);

        Notification::assertSentTo($player, InvitationReceived::class);
    }

    public function test_accepting_an_invitation_notifies_the_organizer(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $this->actingAs($player)->patch(route('invitations.accept', $invitation));

        Notification::assertSentTo(
            $organizer,
            fn (InvitationAnswered $notification) => $notification->invitation->status === Invitation::STATUS_ACCEPTED
        );

        // The answer covers it — no second "joined the game" notice.
        Notification::assertNotSentTo($organizer, GamePlayerJoined::class);
    }

    public function test_declining_an_invitation_notifies_the_organizer(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $this->actingAs($player)->patch(route('invitations.decline', $invitation));

        Notification::assertSentTo(
            $organizer,
            fn (InvitationAnswered $notification) => $notification->invitation->status === Invitation::STATUS_DECLINED
        );
    }

    public function test_answering_an_already_answered_invitation_does_not_notify_again(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_DECLINED,
        ]);

        $this->actingAs($player)->patch(route('invitations.accept', $invitation));

        Notification::assertNothingSent();
    }

    public function test_cancelling_a_game_notifies_participants_and_pending_invitees(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $confirmed = User::factory()->create();
        $waiting = User::factory()->create();
        $invited = User::factory()->create();
        $game = $this->createGame($organizer);

        $this->join($game, $confirmed);
        $this->join($game, $waiting, GamePlayer::STATUS_WAITING_LIST);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $invited->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $this->actingAs($organizer)->patch(route('games.cancel', $game));

        Notification::assertSentTo([$confirmed, $waiting, $invited], GameCancelled::class);
        Notification::assertNotSentTo($organizer, GameCancelled::class);
    }

    public function test_cancelling_does_not_notify_someone_who_already_left(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $gone = User::factory()->create();
        $game = $this->createGame($organizer);

        $this->join($game, $gone, GamePlayer::STATUS_CANCELLED);

        $this->actingAs($organizer)->patch(route('games.cancel', $game));

        Notification::assertNotSentTo($gone, GameCancelled::class);
    }

    public function test_moving_the_match_notifies_participants_with_what_changed(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);
        $this->join($game, $player);

        $this->actingAs($organizer)->patch(
            route('games.update', $game),
            // Kept before the 20:00 end time, which validation enforces.
            $this->updatePayload($game, ['start_time' => '17:00', 'location' => 'Quadra Nova'])
        )->assertRedirect();

        Notification::assertSentTo($player, function (GameUpdated $notification) {
            return collect($notification->changes)->contains(fn (string $change) => str_contains($change, '19:00'))
                && collect($notification->changes)->contains(fn (string $change) => str_contains($change, 'Quadra Nova'));
        });
    }

    public function test_a_price_change_is_announced_since_it_silently_changes_what_is_owed(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);
        $gamePlayer = $this->join($game, $player);

        $this->actingAs($organizer)->patch(route('games.update', $game), $this->updatePayload($game, ['price' => '40.00']));

        $this->assertSame('40.00', $gamePlayer->fresh()->amount_due);

        Notification::assertSentTo($player, fn (GameUpdated $notification) => collect($notification->changes)
            ->contains(fn (string $change) => str_contains($change, '40,00')));
    }

    public function test_editing_only_the_description_does_not_interrupt_anyone(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);
        $this->join($game, $player);

        $this->actingAs($organizer)->patch(
            route('games.update', $game),
            $this->updatePayload($game, ['description' => 'Levem colete branco.'])
        );

        Notification::assertNothingSent();
    }

    public function test_joining_through_the_public_link_notifies_the_organizer(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, ['requires_approval' => true]);

        $this->actingAs($player)->get(route('public-games.join', $game));

        Notification::assertSentTo(
            $organizer,
            fn (GamePlayerJoined $notification) => $notification->gamePlayer->status === GamePlayer::STATUS_PENDING
        );
    }

    public function test_a_guest_joining_through_the_public_link_notifies_the_organizer(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $this->post(route('public-games.join-guest', $game), ['name' => 'Zé do Bairro']);

        Notification::assertSentTo($organizer, GamePlayerJoined::class);
    }

    public function test_the_organizer_adding_someone_by_hand_notifies_nobody(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $this->actingAs($organizer)->post(route('game-players.store', $game), ['user_id' => $player->id]);

        Notification::assertNothingSent();
    }

    public function test_confirming_a_waiting_list_player_tells_them_they_got_the_spot(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);
        $gamePlayer = $this->join($game, $player, GamePlayer::STATUS_WAITING_LIST);

        $this->actingAs($organizer)->patch(route('game-players.confirm', [$game, $gamePlayer]));

        Notification::assertSentTo($player, GamePlayerConfirmed::class);
    }

    public function test_confirming_a_guest_notifies_nobody_since_they_have_no_account(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);
        $guest = GuestPlayer::create(['organizer_id' => $organizer->id, 'name' => 'Convidado Zé']);

        $gamePlayer = GamePlayer::create([
            'game_id' => $game->id,
            'guest_player_id' => $guest->id,
            'status' => GamePlayer::STATUS_WAITING_LIST,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($organizer)->patch(route('game-players.confirm', [$game, $gamePlayer]))->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_rating_a_player_notifies_them(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $player = $this->createPlayer();
        $game = $this->createGame($organizer, [
            'date' => now()->subDay()->format('Y-m-d'),
            'status' => Game::STATUS_FINISHED,
        ]);
        $this->join($game, $player);

        $this->actingAs($organizer)->post(route('ratings.store', [$game, $player]), [
            'overall_rating' => 5,
            'punctuality_rating' => 4,
            'behavior_rating' => 5,
            'performance_rating' => 4,
        ]);

        $this->assertDatabaseCount('ratings', 1);
        Notification::assertSentTo($player, PlayerRated::class);
    }

    /**
     * The database channel is the reliable record behind every push, so at
     * least one path is checked end to end without faking.
     */
    public function test_notifications_are_stored_in_the_database_inbox(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = $this->createPlayer();
        $game = $this->createGame($organizer);

        $this->actingAs($organizer)->post(route('games.invitations.store', [$game, $player->playerProfile]));

        $notification = $player->notifications()->firstOrFail();

        $this->assertSame('invitation_received', $notification->data['type']);
        $this->assertSame(route('invitations.index'), $notification->data['url']);

        $this->actingAs($player)->get('/notificacoes')->assertSee('Convite para jogar');
    }
}
