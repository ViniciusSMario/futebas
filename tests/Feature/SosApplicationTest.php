<?php

namespace Tests\Feature;

use App\Exceptions\SosRequestUnavailableException;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\PlayerProfile;
use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Models\User;
use App\Notifications\SosApplicationAccepted;
use App\Notifications\SosApplicationReceived;
use App\Notifications\SosApplicationRejected;
use App\Services\SosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The competition side of SOS: goalkeepers apply, and exactly one of them
 * wins — even when the organizer decides twice at once.
 */
class SosApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Game $game;

    private SosRequest $sosRequest;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->organizer = User::factory()->organizer()->create(['city' => 'Teresina', 'state' => 'PI']);

        $this->game = Game::create([
            'user_id' => $this->organizer->id,
            'team_name' => 'Pelada da quinta',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '19:00',
            'max_players' => 10,
            'price' => '20.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
        ]);

        $this->sosRequest = SosRequest::create([
            'game_id' => $this->game->id,
            'organizer_id' => $this->organizer->id,
            'position' => 'Goleiro',
            'offered_value' => '60.00',
            'status' => SosRequest::STATUS_OPEN,
            'expires_at' => $this->game->startsAt(),
        ]);
    }

    private function goalkeeper(string $name, array $attributes = []): User
    {
        $user = User::factory()->create(['name' => $name, 'state' => 'PI']);

        PlayerProfile::create(array_merge([
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
        ], $attributes));

        return $user;
    }

    private function applicationFor(User $user, string $price = '60.00'): SosApplication
    {
        return SosApplication::create([
            'sos_request_id' => $this->sosRequest->id,
            'user_id' => $user->id,
            'asking_price' => $price,
            'status' => SosApplication::STATUS_PENDING,
        ]);
    }

    public function test_goalkeeper_sees_a_matching_open_call(): void
    {
        $goalkeeper = $this->goalkeeper('Lucas');

        $response = $this->actingAs($goalkeeper)->get(route('sos-opportunities.index'));

        $response->assertOk();
        $response->assertSee('Arena Society Central');
        $response->assertSee('60,00');
    }

    public function test_goalkeeper_from_another_city_does_not_see_the_call(): void
    {
        $goalkeeper = $this->goalkeeper('De Fora', ['city' => 'Parnaíba']);

        $response = $this->actingAs($goalkeeper)->get(route('sos-opportunities.index'));

        $response->assertOk();
        $response->assertDontSee('Arena Society Central');
    }

    public function test_applying_always_lands_as_pending_and_never_joins_the_game(): void
    {
        $goalkeeper = $this->goalkeeper('Lucas');

        $response = $this->actingAs($goalkeeper)->post(route('sos-opportunities.apply', $this->sosRequest), [
            'asking_price' => '55.00',
            'message' => 'Chego 15 minutos antes.',
        ]);

        $response->assertRedirect(route('sos-opportunities.show', $this->sosRequest));

        $application = SosApplication::sole();
        $this->assertSame(SosApplication::STATUS_PENDING, $application->status);
        $this->assertSame('55.00', $application->asking_price);
        $this->assertSame('Chego 15 minutos antes.', $application->message);

        // Nobody is added to the match by applying.
        $this->assertSame(0, GamePlayer::count());

        Notification::assertSentTo($this->organizer, SosApplicationReceived::class);
    }

    public function test_a_player_who_is_not_a_goalkeeper_cannot_apply(): void
    {
        $striker = $this->goalkeeper('Atacante', ['positions' => ['Atacante']]);

        $response = $this->actingAs($striker)->post(route('sos-opportunities.apply', $this->sosRequest), [
            'asking_price' => '40.00',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, SosApplication::count());
    }

    public function test_a_player_who_is_not_a_goalkeeper_cannot_open_a_call(): void
    {
        $striker = $this->goalkeeper('Atacante', ['positions' => ['Atacante']]);

        $this->actingAs($striker)
            ->get(route('sos-opportunities.show', $this->sosRequest))
            ->assertForbidden();
    }

    public function test_a_player_without_a_profile_cannot_open_a_call(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('sos-opportunities.show', $this->sosRequest))
            ->assertForbidden();
    }

    public function test_the_opportunities_page_explains_itself_to_a_non_goalkeeper(): void
    {
        $striker = $this->goalkeeper('Atacante', ['positions' => ['Atacante']]);

        $response = $this->actingAs($striker)->get(route('sos-opportunities.index'));

        $response->assertOk();
        $response->assertSee('O SOS é só para goleiros');
        $response->assertDontSee('Arena Society Central');
    }

    public function test_a_goalkeeper_who_also_plays_outfield_still_sees_the_call(): void
    {
        $versatile = $this->goalkeeper('Coringa', ['positions' => ['Atacante', 'Goleiro']]);

        $response = $this->actingAs($versatile)->get(route('sos-opportunities.index'));

        $response->assertOk();
        $response->assertSee('Arena Society Central');
    }

    public function test_applying_twice_updates_the_same_candidacy(): void
    {
        $goalkeeper = $this->goalkeeper('Lucas');

        $this->actingAs($goalkeeper)->post(route('sos-opportunities.apply', $this->sosRequest), ['asking_price' => '70.00']);
        $this->actingAs($goalkeeper)->post(route('sos-opportunities.apply', $this->sosRequest), ['asking_price' => '55.00']);

        $this->assertSame(1, SosApplication::count());
        $this->assertSame('55.00', SosApplication::sole()->asking_price);
    }

    public function test_organizer_cannot_apply_to_an_sos(): void
    {
        $response = $this->actingAs($this->organizer)->post(route('sos-opportunities.apply', $this->sosRequest), [
            'asking_price' => '55.00',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, SosApplication::count());
    }

    public function test_goalkeeper_can_withdraw_before_a_decision(): void
    {
        $goalkeeper = $this->goalkeeper('Lucas');
        $application = $this->applicationFor($goalkeeper);

        $response = $this->actingAs($goalkeeper)->delete(route('sos-opportunities.withdraw', $this->sosRequest));

        $response->assertRedirect(route('sos-opportunities.index'));
        $this->assertSame(SosApplication::STATUS_WITHDRAWN, $application->fresh()->status);
    }

    public function test_accepting_confirms_the_winner_and_rejects_everyone_else(): void
    {
        $winner = $this->goalkeeper('Lucas');
        $loser = $this->goalkeeper('Pedro');

        $winning = $this->applicationFor($winner, '55.00');
        $losing = $this->applicationFor($loser, '70.00');

        $response = $this->actingAs($this->organizer)
            ->patch(route('sos.accept', [$this->sosRequest, $winning]));

        $response->assertRedirect(route('sos.show', $this->sosRequest));

        $this->assertSame(SosApplication::STATUS_ACCEPTED, $winning->fresh()->status);
        $this->assertSame(SosApplication::STATUS_REJECTED, $losing->fresh()->status);

        $this->sosRequest->refresh();
        $this->assertSame(SosRequest::STATUS_FILLED, $this->sosRequest->status);
        $this->assertSame($winning->id, $this->sosRequest->accepted_application_id);

        $gamePlayer = GamePlayer::sole();
        $this->assertSame($winner->id, $gamePlayer->user_id);
        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $gamePlayer->status);
        // The SOS goalkeeper is paid, not charged the match fee.
        $this->assertSame('0.00', $gamePlayer->amount_due);

        Notification::assertSentTo($winner, SosApplicationAccepted::class);
        Notification::assertSentTo($loser, SosApplicationRejected::class);
    }

    public function test_a_second_accept_on_a_filled_request_is_refused(): void
    {
        $first = $this->goalkeeper('Lucas');
        $second = $this->goalkeeper('Pedro');

        $firstApplication = $this->applicationFor($first);
        $secondApplication = $this->applicationFor($second);

        $this->actingAs($this->organizer)->patch(route('sos.accept', [$this->sosRequest, $firstApplication]));

        // A second tab, still showing the request as open, tries to accept
        // the other candidate.
        $response = $this->actingAs($this->organizer)
            ->patch(route('sos.accept', [$this->sosRequest, $secondApplication]));

        $response->assertSessionHas('error');

        // The loser stays rejected and never reaches the match.
        $this->assertSame(SosApplication::STATUS_REJECTED, $secondApplication->fresh()->status);
        $this->assertSame(1, GamePlayer::count());
        $this->assertSame($first->id, GamePlayer::sole()->user_id);
    }

    public function test_accepting_a_candidacy_that_was_already_answered_is_refused(): void
    {
        $goalkeeper = $this->goalkeeper('Lucas');
        $application = $this->applicationFor($goalkeeper);

        $this->actingAs($this->organizer)->patch(route('sos.reject', [$this->sosRequest, $application]));

        $this->expectException(SosRequestUnavailableException::class);

        app(SosService::class)->accept($application->fresh());
    }

    public function test_rejecting_one_candidate_keeps_the_call_open(): void
    {
        $rejected = $this->goalkeeper('Pedro');
        $application = $this->applicationFor($rejected);

        $response = $this->actingAs($this->organizer)
            ->patch(route('sos.reject', [$this->sosRequest, $application]));

        $response->assertRedirect(route('sos.show', $this->sosRequest));
        $this->assertSame(SosApplication::STATUS_REJECTED, $application->fresh()->status);
        $this->assertSame(SosRequest::STATUS_OPEN, $this->sosRequest->fresh()->status);

        Notification::assertSentTo($rejected, SosApplicationRejected::class);
    }

    public function test_another_organizer_cannot_decide_on_the_call(): void
    {
        $goalkeeper = $this->goalkeeper('Lucas');
        $application = $this->applicationFor($goalkeeper);

        $intruder = User::factory()->organizer()->create();

        $this->actingAs($intruder)
            ->patch(route('sos.accept', [$this->sosRequest, $application]))
            ->assertForbidden();

        $this->assertSame(SosApplication::STATUS_PENDING, $application->fresh()->status);
    }

    public function test_a_candidacy_from_another_call_cannot_be_accepted_here(): void
    {
        $goalkeeper = $this->goalkeeper('Lucas');

        $otherRequest = SosRequest::create([
            'game_id' => $this->game->id,
            'organizer_id' => $this->organizer->id,
            'position' => 'Goleiro',
            'offered_value' => '30.00',
            'status' => SosRequest::STATUS_OPEN,
        ]);

        $foreign = SosApplication::create([
            'sos_request_id' => $otherRequest->id,
            'user_id' => $goalkeeper->id,
            'asking_price' => '30.00',
            'status' => SosApplication::STATUS_PENDING,
        ]);

        $this->actingAs($this->organizer)
            ->patch(route('sos.accept', [$this->sosRequest, $foreign]))
            ->assertNotFound();
    }

    public function test_applying_to_a_filled_call_is_refused(): void
    {
        $winner = $this->goalkeeper('Lucas');
        $latecomer = $this->goalkeeper('Pedro');

        $this->actingAs($this->organizer)
            ->patch(route('sos.accept', [$this->sosRequest, $this->applicationFor($winner)]));

        $response = $this->actingAs($latecomer)->post(route('sos-opportunities.apply', $this->sosRequest), [
            'asking_price' => '40.00',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, SosApplication::count());
    }

    public function test_applying_after_the_deadline_is_refused(): void
    {
        $goalkeeper = $this->goalkeeper('Lucas');

        $this->sosRequest->forceFill(['expires_at' => now()->subMinute()])->save();

        $response = $this->actingAs($goalkeeper)->post(route('sos-opportunities.apply', $this->sosRequest), [
            'asking_price' => '60.00',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, SosApplication::count());
    }

    public function test_organizer_compares_candidates_on_price_city_and_ratings(): void
    {
        $cheap = $this->goalkeeper('Lucas');
        $cheap->playerProfile->update([
            'average_rating' => '4.75',
            'ratings_count' => 8,
        ]);

        $this->applicationFor($cheap, '45.00');

        $response = $this->actingAs($this->organizer)->get(route('sos.show', $this->sosRequest));

        $response->assertOk();
        $response->assertSee('Lucas');
        $response->assertSee('45,00');
        $response->assertSee('Teresina');
        $response->assertSee('4,8');
    }
}
