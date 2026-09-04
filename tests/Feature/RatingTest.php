<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\Invitation;
use App\Models\PlayerProfile;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    private function createGame(User $organizer, array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Amigos FC',
            'location' => 'Arena X',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => now()->subDays(2)->format('Y-m-d'),
            'start_time' => '20:00',
            'end_time' => '21:00',
            'max_players' => 10,
            'price' => '50.00',
            'positions' => ['Goleiro', 'Zagueiro'],
            'status' => 'open',
        ], $attributes));
    }

    private function createPlayerProfile(User $player, array $attributes = []): PlayerProfile
    {
        return PlayerProfile::create(array_merge([
            'user_id' => $player->id,
            'birth_date' => '1998-01-01',
            'city' => 'Teresina',
            'state' => 'PI',
            'phone' => '86999999999',
            'positions' => ['Goleiro'],
            'modalities' => ['Society'],
            'level' => 'Intermediário',
            'price_per_game' => '40.00',
            'plays_outside_city' => false,
        ], $attributes));
    }

    private function acceptInvitation(Game $game, User $organizer, User $player): Invitation
    {
        return Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'team' => $game->team_name,
            'position' => 'Goleiro',
            'status' => Invitation::STATUS_ACCEPTED,
        ]);
    }

    private function ratingPayload(array $overrides = []): array
    {
        return array_merge([
            'overall_rating' => 5,
            'punctuality_rating' => 4,
            'behavior_rating' => 5,
            'performance_rating' => 4,
            'comment' => 'Excelente jogador e muito pontual.',
        ], $overrides);
    }

    public function test_organizer_can_rate_a_player_after_the_game_has_finished(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);
        $this->acceptInvitation($game, $organizer, $player);

        $response = $this->actingAs($organizer)->post(
            route('ratings.store', [$game, $player]),
            $this->ratingPayload()
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('ratings.index', $game));

        $this->assertSame(1, Rating::count());

        $rating = Rating::first();
        $this->assertSame($game->id, $rating->game_id);
        $this->assertSame($organizer->id, $rating->organizer_id);
        $this->assertSame($player->id, $rating->user_id);
        $this->assertSame(5, $rating->overall_rating);
        $this->assertSame('Excelente jogador e muito pontual.', $rating->comment);
    }

    public function test_rating_updates_the_players_average(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $playerProfile = $this->createPlayerProfile($player);

        $gameOne = $this->createGame($organizer, ['location' => 'Arena 1', 'status' => 'finished']);
        $this->acceptInvitation($gameOne, $organizer, $player);
        $this->actingAs($organizer)->post(route('ratings.store', [$gameOne, $player]), $this->ratingPayload([
            'overall_rating' => 5,
            'punctuality_rating' => 5,
            'behavior_rating' => 5,
            'performance_rating' => 5,
        ]));

        $gameTwo = $this->createGame($organizer, ['location' => 'Arena 2', 'status' => 'finished']);
        $this->acceptInvitation($gameTwo, $organizer, $player);
        $this->actingAs($organizer)->post(route('ratings.store', [$gameTwo, $player]), $this->ratingPayload([
            'overall_rating' => 3,
            'punctuality_rating' => 3,
            'behavior_rating' => 3,
            'performance_rating' => 3,
        ]));

        $playerProfile->refresh();

        $this->assertSame(2, $playerProfile->ratings_count);
        $this->assertSame('4.00', $playerProfile->average_rating);
    }

    public function test_organizer_cannot_rate_the_same_player_twice_for_the_same_game(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);
        $this->acceptInvitation($game, $organizer, $player);

        $this->actingAs($organizer)->post(route('ratings.store', [$game, $player]), $this->ratingPayload());

        $response = $this->actingAs($organizer)->post(route('ratings.store', [$game, $player]), $this->ratingPayload());

        $response->assertSessionHasErrors('rating');
        $this->assertSame(1, Rating::count());
    }

    public function test_organizer_cannot_rate_a_player_before_the_game_has_finished(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['date' => now()->addDays(3)->format('Y-m-d')]);
        $this->acceptInvitation($game, $organizer, $player);

        $response = $this->actingAs($organizer)->post(route('ratings.store', [$game, $player]), $this->ratingPayload());

        $response->assertForbidden();
        $this->assertSame(0, Rating::count());
    }

    public function test_organizer_cannot_rate_a_player_who_did_not_accept_the_invitation(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($organizer)->post(route('ratings.store', [$game, $player]), $this->ratingPayload());

        $response->assertNotFound();
        $this->assertSame(0, Rating::count());
    }

    public function test_organizer_cannot_rate_a_player_for_another_organizers_game(): void
    {
        $organizer = User::factory()->organizer()->create();
        $otherOrganizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $this->createPlayerProfile($player);
        $game = $this->createGame($otherOrganizer);
        $this->acceptInvitation($game, $otherOrganizer, $player);

        $response = $this->actingAs($organizer)->post(route('ratings.store', [$game, $player]), $this->ratingPayload());

        $response->assertForbidden();
        $this->assertSame(0, Rating::count());
    }

    public function test_player_cannot_rate_other_players(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $otherPlayer = User::factory()->create();
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer);
        $this->acceptInvitation($game, $organizer, $player);

        $response = $this->actingAs($otherPlayer)->post(route('ratings.store', [$game, $player]), $this->ratingPayload());

        $response->assertForbidden();
        $this->assertSame(0, Rating::count());
    }

    public function test_ratings_index_lists_confirmed_players_for_a_finished_game(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create(['name' => 'Lucas']);
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);
        $this->acceptInvitation($game, $organizer, $player);

        $response = $this->actingAs($organizer)->get(route('ratings.index', $game));

        $response->assertOk();
        $response->assertSee('Lucas');
        $response->assertSee('Avaliar');
    }

    /**
     * The list used to be built from accepted invitations alone, which hid
     * everyone who joined through the public link, the game search, a
     * weekly pelada, SOS, or the organizer adding them by hand.
     */
    public function test_ratings_index_lists_players_who_joined_without_an_invitation(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create(['name' => 'Joao do Link']);
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($organizer)->get(route('ratings.index', $game));

        $response->assertOk();
        $response->assertSee('Joao do Link');
        $response->assertSee(route('ratings.create', [$game, $player]));
    }

    public function test_ratings_index_does_not_list_a_player_twice(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create(['name' => 'Lucas']);
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);

        // Invited, accepted, and therefore also a participant.
        $this->acceptInvitation($game, $organizer, $player);
        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($organizer)->get(route('ratings.index', $game));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), route('ratings.create', [$game, $player])));
    }

    public function test_ratings_index_leaves_out_guests_and_the_organizer(): void
    {
        $organizer = User::factory()->organizer()->create(['name' => 'Dono da Bola']);
        $player = User::factory()->create(['name' => 'Lucas']);
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);
        $guest = GuestPlayer::create(['organizer_id' => $organizer->id, 'name' => 'Ze Convidado']);

        foreach ([['user_id' => $player->id], ['user_id' => $organizer->id], ['guest_player_id' => $guest->id]] as $identity) {
            GamePlayer::create([
                'game_id' => $game->id,
                ...$identity,
                'status' => GamePlayer::STATUS_CONFIRMED,
                'payment_status' => GamePlayer::PAYMENT_PENDING,
                'amount_due' => $game->price,
                'joined_at' => now(),
            ]);
        }

        $response = $this->actingAs($organizer)->get(route('ratings.index', $game));

        $response->assertSee('Lucas');
        $response->assertDontSee('Ze Convidado');
        $response->assertDontSee(route('ratings.create', [$game, $organizer]));
    }

    public function test_ratings_index_leaves_out_someone_who_pulled_out(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create(['name' => 'Desistente Silva']);
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CANCELLED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
            'cancelled_at' => now(),
        ]);

        $this->actingAs($organizer)
            ->get(route('ratings.index', $game))
            ->assertDontSee('Desistente Silva');
    }

    public function test_rating_form_renders_the_players_name_and_star_inputs(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create(['name' => 'Lucas']);
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);
        $this->acceptInvitation($game, $organizer, $player);

        $response = $this->actingAs($organizer)->get(route('ratings.create', [$game, $player]));

        $response->assertOk();
        $response->assertSee('Como foi jogar com Lucas?');
        $response->assertSee('Avaliação geral');
        $response->assertSee('Pontualidade');
        $response->assertSee('Comportamento');
        $response->assertSee('Desempenho');
    }

    public function test_rating_form_redirects_when_the_player_was_already_rated(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);
        $this->acceptInvitation($game, $organizer, $player);

        $this->actingAs($organizer)->post(route('ratings.store', [$game, $player]), $this->ratingPayload());

        $response = $this->actingAs($organizer)->get(route('ratings.create', [$game, $player]));

        $response->assertRedirect(route('ratings.index', $game));
    }

    public function test_ratings_index_is_forbidden_before_the_game_has_finished(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['date' => now()->addDays(3)->format('Y-m-d')]);

        $response = $this->actingAs($organizer)->get(route('ratings.index', $game));

        $response->assertForbidden();
    }

    public function test_organizer_can_rate_a_cancelled_player_immediately_without_waiting_for_the_match(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['date' => now()->addDays(3)->format('Y-m-d')]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CANCELLED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now()->subDay(),
            'cancelled_at' => now(),
            'cancellation_reason' => 'Imprevisto de última hora.',
        ]);

        $formResponse = $this->actingAs($organizer)->get(route('ratings.create', [$game, $player]));
        $formResponse->assertOk();

        $storeResponse = $this->actingAs($organizer)->post(
            route('ratings.store', [$game, $player]),
            $this->ratingPayload()
        );

        $storeResponse->assertSessionHasNoErrors();
        $storeResponse->assertRedirect(route('games.show', ['game' => $game, 'tab' => 'participantes']));
        $storeResponse->assertSessionHas('status', 'rating-saved');

        $this->assertSame(1, Rating::count());
        $this->assertSame($player->id, Rating::first()->user_id);
    }
}
