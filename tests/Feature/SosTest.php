<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\PlayerProfile;
use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Models\User;
use App\Notifications\SosRequestPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Publishing an SOS: creating (or reusing) the match, and fanning the call
 * out to the goalkeepers in its region.
 *
 * Choosing between the goalkeepers who answer lives in
 * {@see SosApplicationTest}.
 */
class SosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function newGamePayload(array $overrides = []): array
    {
        return array_merge([
            'source' => 'new',
            'team_name' => 'Pelada da quinta',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '19:00',
            'city' => 'Teresina',
            'location' => 'Arena Society Central',
            'modality' => 'Society',
            'offered_value' => '60.00',
        ], $overrides);
    }

    private function goalkeeper(array $attributes = []): PlayerProfile
    {
        $user = User::factory()->create([
            'name' => $attributes['name'] ?? 'Goleiro Teste',
            'state' => 'PI',
        ]);

        unset($attributes['name']);

        return PlayerProfile::create(array_merge([
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
    }

    private function organizer(): User
    {
        return User::factory()->organizer()->create(['city' => 'Teresina', 'state' => 'PI']);
    }

    private function openGame(User $organizer, array $overrides = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Pelada da quinta',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'modality' => 'Society',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '19:00',
            'max_players' => 10,
            'price' => '20.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
        ], $overrides));
    }

    public function test_guests_cannot_access_the_sos_area(): void
    {
        $this->get('/sos')->assertRedirect('/login');
    }

    public function test_organizer_can_publish_an_sos_creating_a_new_game(): void
    {
        Notification::fake();

        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)->post('/sos', $this->newGamePayload());

        $response->assertSessionHasNoErrors();

        $this->assertSame(1, Game::count());
        $this->assertSame(1, SosRequest::count());

        $game = Game::first();
        $this->assertSame($organizer->id, $game->user_id);
        $this->assertSame('Pelada da quinta', $game->team_name);
        $this->assertSame(['Goleiro'], $game->positions);
        $this->assertSame(1, $game->max_players);
        // The SOS player is paid by the organizer, never charged.
        $this->assertSame('0.00', $game->price);

        $sosRequest = SosRequest::first();
        $this->assertSame($game->id, $sosRequest->game_id);
        $this->assertSame($organizer->id, $sosRequest->organizer_id);
        $this->assertSame('Goleiro', $sosRequest->position);
        $this->assertSame('60.00', $sosRequest->offered_value);
        $this->assertSame(SosRequest::STATUS_OPEN, $sosRequest->status);

        $response->assertRedirect(route('sos.show', $sosRequest));
    }

    public function test_the_position_is_always_goalkeeper_even_if_another_is_posted(): void
    {
        Notification::fake();

        $organizer = $this->organizer();

        // "SOS Goleiro" is a goalkeeper feature: the position is not an
        // input, so a hand-crafted request cannot repurpose it.
        $this->actingAs($organizer)->post('/sos', $this->newGamePayload(['position' => 'Atacante']));

        $this->assertSame(SosRequest::POSITION, SosRequest::first()->position);
        $this->assertSame([SosRequest::POSITION], Game::first()->positions);
    }

    public function test_organizer_can_publish_an_sos_for_an_existing_game(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $game = $this->openGame($organizer);

        $response = $this->actingAs($organizer)->post('/sos', [
            'source' => 'existing',
            'game_id' => $game->id,
            'offered_value' => '80.00',
            'message' => 'Precisa levar luva.',
        ]);

        $response->assertSessionHasNoErrors();

        // No second match was created.
        $this->assertSame(1, Game::count());

        $sosRequest = SosRequest::first();
        $this->assertSame($game->id, $sosRequest->game_id);
        $this->assertSame('80.00', $sosRequest->offered_value);
        $this->assertSame('Precisa levar luva.', $sosRequest->message);
        // The deadline defaults to kickoff.
        $this->assertSame($game->startsAt()->format('Y-m-d H:i'), $sosRequest->expires_at->format('Y-m-d H:i'));
    }

    public function test_organizer_cannot_publish_an_sos_for_another_organizers_game(): void
    {
        $organizer = $this->organizer();
        $game = $this->openGame($this->organizer());

        $response = $this->actingAs($organizer)->post('/sos', [
            'source' => 'existing',
            'game_id' => $game->id,
            'offered_value' => '80.00',
        ]);

        $response->assertSessionHasErrors('game_id');
        $this->assertSame(0, SosRequest::count());
    }

    public function test_match_fields_are_only_required_when_creating_a_new_game(): void
    {
        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)->post('/sos', ['source' => 'new']);

        $response->assertSessionHasErrors(['team_name', 'date', 'start_time', 'location', 'city', 'modality', 'offered_value']);
        $this->assertSame(0, SosRequest::count());
    }

    public function test_player_cannot_publish_an_sos(): void
    {
        $player = User::factory()->create();

        $response = $this->actingAs($player)->post('/sos', $this->newGamePayload());

        $response->assertForbidden();
        $this->assertSame(0, SosRequest::count());
    }

    public function test_publishing_notifies_goalkeepers_in_the_region(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $nearby = $this->goalkeeper(['name' => 'Lucas']);

        $this->actingAs($organizer)->post('/sos', $this->newGamePayload());

        Notification::assertSentTo($nearby->user, SosRequestPublished::class);
        $this->assertSame(1, SosRequest::first()->notified_count);
    }

    public function test_publishing_does_not_notify_players_who_are_not_goalkeepers(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $striker = $this->goalkeeper(['name' => 'Atacante', 'positions' => ['Atacante']]);

        $this->actingAs($organizer)->post('/sos', $this->newGamePayload());

        Notification::assertNotSentTo($striker->user, SosRequestPublished::class);
        $this->assertSame(0, SosRequest::first()->notified_count);
    }

    public function test_publishing_does_not_notify_goalkeepers_from_another_city(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $faraway = $this->goalkeeper(['name' => 'De Fora', 'city' => 'Parnaíba']);

        $this->actingAs($organizer)->post('/sos', $this->newGamePayload());

        Notification::assertNotSentTo($faraway->user, SosRequestPublished::class);
    }

    public function test_publishing_notifies_travelling_goalkeepers_from_the_same_state(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $traveller = $this->goalkeeper([
            'name' => 'Viajante',
            'city' => 'Parnaíba',
            'plays_outside_city' => true,
        ]);

        $this->actingAs($organizer)->post('/sos', $this->newGamePayload());

        Notification::assertSentTo($traveller->user, SosRequestPublished::class);
    }

    public function test_publishing_does_not_notify_goalkeepers_of_another_modality(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $futsalOnly = $this->goalkeeper(['name' => 'Só Futsal', 'modalities' => ['Futsal']]);

        $this->actingAs($organizer)->post('/sos', $this->newGamePayload());

        Notification::assertNotSentTo($futsalOnly->user, SosRequestPublished::class);
    }

    public function test_organizer_sees_their_own_sos_list(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $this->actingAs($organizer)->post('/sos', $this->newGamePayload());

        $response = $this->actingAs($organizer)->get(route('sos.index'));

        $response->assertOk();
        $response->assertSee('Arena Society Central');
        $response->assertSee('60,00');
    }

    public function test_organizer_cannot_view_another_organizers_sos(): void
    {
        Notification::fake();

        $owner = $this->organizer();
        $this->actingAs($owner)->post('/sos', $this->newGamePayload());

        $response = $this->actingAs($this->organizer())->get(route('sos.show', SosRequest::first()));

        $response->assertForbidden();
    }

    public function test_a_game_with_a_live_sos_is_not_offered_again(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $game = $this->openGame($organizer);

        $this->actingAs($organizer)->post('/sos', [
            'source' => 'existing',
            'game_id' => $game->id,
            'offered_value' => '80.00',
        ]);

        $response = $this->actingAs($organizer)->get(route('sos.create'));

        $response->assertOk();
        $response->assertSee('Você não tem partidas abertas sem SOS ativo.');
    }

    public function test_organizer_can_cancel_an_sos_and_pending_candidates_are_dropped(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $goalkeeper = $this->goalkeeper();

        $this->actingAs($organizer)->post('/sos', $this->newGamePayload());
        $sosRequest = SosRequest::first();

        $application = SosApplication::create([
            'sos_request_id' => $sosRequest->id,
            'user_id' => $goalkeeper->user_id,
            'asking_price' => '60.00',
            'status' => SosApplication::STATUS_PENDING,
        ]);

        $response = $this->actingAs($organizer)->patch(route('sos.cancel', $sosRequest));

        $response->assertRedirect(route('sos.show', $sosRequest));
        $this->assertSame(SosRequest::STATUS_CANCELLED, $sosRequest->fresh()->status);
        $this->assertSame(SosApplication::STATUS_REJECTED, $application->fresh()->status);
    }
}
