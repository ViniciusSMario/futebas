<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\User;
use App\Notifications\GamePlayerConfirmed;
use App\Notifications\WaitingListSpotOpened;
use App\Services\GamePlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Spots freed on a game are handed to whoever has been waiting longest,
 * instead of sitting there unnoticed.
 */
class WaitingListPromotionTest extends TestCase
{
    use RefreshDatabase;

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
            'max_players' => 1,
            'price' => '25.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
            'requires_approval' => false,
        ], $attributes));
    }

    private function join(Game $game, User $user, string $status, ?string $joinedAt = null): GamePlayer
    {
        return GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'status' => $status,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => $joinedAt ? now()->parse($joinedAt) : now(),
        ]);
    }

    public function test_a_player_leaving_hands_their_spot_to_the_first_in_line(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $leaving = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->join($game, $leaving, GamePlayer::STATUS_CONFIRMED);
        $firstInLine = $this->join($game, $first, GamePlayer::STATUS_WAITING_LIST, '-2 hours');
        $secondInLine = $this->join($game, $second, GamePlayer::STATUS_WAITING_LIST, '-1 hour');

        $this->actingAs($leaving)->delete(route('games.leave', $game));

        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $firstInLine->fresh()->status);
        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, $secondInLine->fresh()->status);

        Notification::assertSentTo(
            $first,
            fn (GamePlayerConfirmed $notification) => $notification->promoted === true
        );
        Notification::assertNotSentTo($second, GamePlayerConfirmed::class);
    }

    public function test_the_organizer_removing_a_confirmed_player_promotes_the_next_one(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $removed = $this->join($game, User::factory()->create(), GamePlayer::STATUS_CONFIRMED);
        $waiting = $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST);

        $this->actingAs($organizer)->delete(route('game-players.destroy', [$game, $removed]));

        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $waiting->fresh()->status);
    }

    public function test_removing_someone_from_the_waiting_list_promotes_nobody(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $this->join($game, User::factory()->create(), GamePlayer::STATUS_CONFIRMED);
        $removed = $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST);
        $stillWaiting = $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST);

        $this->actingAs($organizer)->delete(route('game-players.destroy', [$game, $removed]));

        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, $stillWaiting->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_raising_the_player_limit_fills_every_spot_it_opened(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $this->join($game, User::factory()->create(), GamePlayer::STATUS_CONFIRMED);
        $first = $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST, '-3 hours');
        $second = $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST, '-2 hours');
        $third = $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST, '-1 hour');

        $this->actingAs($organizer)->patch(route('games.update', $game), [
            'team_name' => $game->team_name,
            'modality' => $game->modality,
            'date' => $game->date->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'location' => $game->location,
            'city' => $game->city,
            'max_players' => 3,
            'price' => (string) $game->price,
        ])->assertRedirect();

        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $first->fresh()->status);
        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $second->fresh()->status);
        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, $third->fresh()->status);
    }

    public function test_an_approval_gated_game_tells_the_organizer_instead_of_promoting(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['requires_approval' => true]);

        $leaving = User::factory()->create();
        $waitingUser = User::factory()->create();
        $this->join($game, $leaving, GamePlayer::STATUS_CONFIRMED);
        $waiting = $this->join($game, $waitingUser, GamePlayer::STATUS_WAITING_LIST);

        $this->actingAs($leaving)->delete(route('games.leave', $game));

        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, $waiting->fresh()->status);

        Notification::assertSentTo(
            $organizer,
            fn (WaitingListSpotOpened $notification) => $notification->waitingCount === 1
        );
        Notification::assertNotSentTo($waitingUser, GamePlayerConfirmed::class);
    }

    public function test_nothing_happens_when_the_waiting_list_is_empty(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);
        $leaving = User::factory()->create();
        $this->join($game, $leaving, GamePlayer::STATUS_CONFIRMED);

        $this->actingAs($leaving)->delete(route('games.leave', $game));

        Notification::assertNothingSent();
    }

    public function test_a_cancelled_game_never_promotes(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['status' => Game::STATUS_CANCELLED]);
        $waiting = $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST);

        app(GamePlayerService::class)->promoteFromWaitingList($game);

        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, $waiting->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_a_still_full_game_promotes_nobody(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);
        $this->join($game, User::factory()->create(), GamePlayer::STATUS_CONFIRMED);
        $waiting = $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST);

        app(GamePlayerService::class)->promoteFromWaitingList($game);

        $this->assertSame(GamePlayer::STATUS_WAITING_LIST, $waiting->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_a_guest_is_promoted_too_but_gets_no_notification(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);
        $guest = GuestPlayer::create(['organizer_id' => $organizer->id, 'name' => 'Convidado Zé']);

        $leaving = User::factory()->create();
        $this->join($game, $leaving, GamePlayer::STATUS_CONFIRMED);

        $waiting = GamePlayer::create([
            'game_id' => $game->id,
            'guest_player_id' => $guest->id,
            'status' => GamePlayer::STATUS_WAITING_LIST,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($leaving)->delete(route('games.leave', $game));

        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $waiting->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_promotion_never_hands_out_more_spots_than_exist(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['max_players' => 2]);

        $leaving = User::factory()->create();
        $this->join($game, $leaving, GamePlayer::STATUS_CONFIRMED);
        $this->join($game, User::factory()->create(), GamePlayer::STATUS_CONFIRMED);

        $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST, '-3 hours');
        $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST, '-2 hours');
        $this->join($game, User::factory()->create(), GamePlayer::STATUS_WAITING_LIST, '-1 hour');

        $this->actingAs($leaving)->delete(route('games.leave', $game));

        $this->assertSame(2, $game->fresh()->confirmedPlayersCount());
        $this->assertSame(2, GamePlayer::where('game_id', $game->id)
            ->where('status', GamePlayer::STATUS_WAITING_LIST)
            ->count());
    }
}
