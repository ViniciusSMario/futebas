<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\PlayerProfile;
use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Models\User;
use App\Notifications\SosRequestPublished;
use App\Services\SosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Someone already in a match is not a candidate for it: not called out to
 * over SOS, not listed as someone to invite.
 */
class SosAlreadyInGameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function organizer(): User
    {
        return User::factory()->organizer()->create(['state' => 'PI', 'city' => 'Teresina']);
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
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
            'requires_approval' => false,
        ], $attributes));
    }

    private function createGoalkeeper(string $name = 'Goleiro Silva'): User
    {
        $user = User::factory()->create(['name' => $name, 'state' => 'PI', 'city' => 'Teresina']);

        PlayerProfile::create([
            'user_id' => $user->id,
            'birth_date' => '1995-05-10',
            'state' => 'PI',
            'city' => 'Teresina',
            'phone' => '86999999999',
            'positions' => ['Goleiro'],
            'modalities' => ['Society'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
            'plays_outside_city' => false,
        ]);

        return $user->refresh();
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

    public function test_publishing_does_not_call_out_to_someone_already_in_the_match(): void
    {
        $organizer = $this->organizer();
        $game = $this->createGame($organizer);
        $inGame = $this->createGoalkeeper('Goleiro Dentro');
        $available = $this->createGoalkeeper('Goleiro Livre');

        $this->join($game, $inGame);

        $sosRequest = app(SosService::class)->publish($game, $organizer, 60.0);

        $this->assertSame(1, $sosRequest->notified_count);
        Notification::assertNotSentTo($inGame, SosRequestPublished::class);
        Notification::assertSentTo($available, SosRequestPublished::class);
    }

    public function test_a_goalkeeper_added_after_the_call_stops_seeing_it_as_an_opportunity(): void
    {
        $organizer = $this->organizer();
        $game = $this->createGame($organizer);
        $keeper = $this->createGoalkeeper();

        $sosRequest = app(SosService::class)->publish($game, $organizer, 60.0);

        // Before being added, the call is an opportunity.
        $this->actingAs($keeper)
            ->get(route('sos-opportunities.index'))
            ->assertSee('Arena Society Central');

        $this->join($game, $keeper);

        $this->actingAs($keeper)
            ->get(route('sos-opportunities.index'))
            ->assertDontSee('Arena Society Central');

        $this->assertNotNull($sosRequest->fresh());
    }

    public function test_the_waiting_list_also_counts_as_being_in_the_match(): void
    {
        $organizer = $this->organizer();
        $game = $this->createGame($organizer);
        $keeper = $this->createGoalkeeper();

        app(SosService::class)->publish($game, $organizer, 60.0);
        $this->join($game, $keeper, GamePlayer::STATUS_WAITING_LIST);

        $this->actingAs($keeper)
            ->get(route('sos-opportunities.index'))
            ->assertDontSee('Arena Society Central');
    }

    public function test_someone_who_left_the_match_sees_the_call_again(): void
    {
        $organizer = $this->organizer();
        $game = $this->createGame($organizer);
        $keeper = $this->createGoalkeeper();

        app(SosService::class)->publish($game, $organizer, 60.0);
        $this->join($game, $keeper, GamePlayer::STATUS_CANCELLED);

        $this->actingAs($keeper)
            ->get(route('sos-opportunities.index'))
            ->assertSee('Arena Society Central');
    }

    public function test_applying_is_refused_for_a_match_the_goalkeeper_is_already_in(): void
    {
        $organizer = $this->organizer();
        $game = $this->createGame($organizer);
        $keeper = $this->createGoalkeeper();

        $sosRequest = app(SosService::class)->publish($game, $organizer, 60.0);
        $this->join($game, $keeper);

        $response = $this->actingAs($keeper)->post(route('sos-opportunities.apply', $sosRequest), [
            'asking_price' => '50',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('sos_applications', 0);
    }

    public function test_the_call_page_explains_instead_of_offering_the_form(): void
    {
        $organizer = $this->organizer();
        $game = $this->createGame($organizer);
        $keeper = $this->createGoalkeeper();

        $sosRequest = app(SosService::class)->publish($game, $organizer, 60.0);
        $this->join($game, $keeper);

        $response = $this->actingAs($keeper)->get(route('sos-opportunities.show', $sosRequest));

        $response->assertOk();
        $response->assertSee('Você já está nessa partida');
        $response->assertDontSee('Me candidatar');
    }

    public function test_the_winner_of_an_sos_still_sees_that_they_were_chosen(): void
    {
        $organizer = $this->organizer();
        $game = $this->createGame($organizer);
        $keeper = $this->createGoalkeeper();

        $sosRequest = app(SosService::class)->publish($game, $organizer, 60.0);

        $application = SosApplication::create([
            'sos_request_id' => $sosRequest->id,
            'user_id' => $keeper->id,
            'asking_price' => '50.00',
            'status' => SosApplication::STATUS_PENDING,
        ]);

        app(SosService::class)->accept($application);

        // Accepting puts them in the match, so the "already in" branch must
        // not steal the message meant for the winner.
        $response = $this->actingAs($keeper)->get(route('sos-opportunities.show', $sosRequest->fresh()));

        $response->assertSee('Você foi escolhido!');
        $this->assertSame(SosRequest::STATUS_FILLED, $sosRequest->fresh()->status);
    }

    public function test_the_invite_search_hides_players_already_in_the_match(): void
    {
        $organizer = $this->organizer();
        $game = $this->createGame($organizer);
        $inGame = $this->createGoalkeeper('Goleiro Dentro');
        $this->createGoalkeeper('Goleiro Livre');

        $this->join($game, $inGame);

        $response = $this->actingAs($organizer)->get(route('games.invitations.search', $game));

        $response->assertOk();
        $response->assertDontSee('Goleiro Dentro');
        $response->assertSee('Goleiro Livre');
    }

    public function test_a_guest_in_the_match_does_not_empty_the_invite_search(): void
    {
        $organizer = $this->organizer();
        $game = $this->createGame($organizer);
        $this->createGoalkeeper('Goleiro Livre');

        $guest = GuestPlayer::create(['organizer_id' => $organizer->id, 'name' => 'Zé Convidado']);
        GamePlayer::create([
            'game_id' => $game->id,
            'guest_player_id' => $guest->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($organizer)->get(route('games.invitations.search', $game));

        $response->assertOk();
        $response->assertSee('Goleiro Livre');
    }

    public function test_the_add_player_search_also_hides_who_is_already_in(): void
    {
        $organizer = $this->organizer();
        $game = $this->createGame($organizer);
        $inGame = $this->createGoalkeeper('Goleiro Dentro');
        $this->createGoalkeeper('Goleiro Livre');

        $this->join($game, $inGame);

        $response = $this->actingAs($organizer)->get(route('game-players.create', $game).'?q=Goleiro');

        $response->assertOk();
        $response->assertDontSee('Goleiro Dentro');
        $response->assertSee('Goleiro Livre');
    }
}
