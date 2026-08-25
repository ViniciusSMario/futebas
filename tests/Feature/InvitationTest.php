<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Invitation;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function createGame(User $organizer, array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Amigos FC',
            'location' => 'Arena X',
            'city' => 'Teresina',
            'modality' => 'Society',
            'date' => now()->addDays(3)->format('Y-m-d'),
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
            'phone' => '86999999999',
            'positions' => ['Goleiro'],
            'modalities' => ['Society'],
            'level' => 'Intermediário',
            'price_per_game' => '40.00',
            'plays_outside_city' => false,
        ], $attributes));
    }

    public function test_organizer_sees_only_their_own_open_games_when_inviting(): void
    {
        $organizer = User::factory()->organizer()->create();
        $otherOrganizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $playerProfile = $this->createPlayerProfile($player);

        $this->createGame($organizer, ['location' => 'Arena X']);
        $this->createGame($otherOrganizer, ['location' => 'Arena Alheia']);

        $response = $this->actingAs($organizer)->get("/invitations/create/{$playerProfile->id}");

        $response->assertOk();
        $response->assertSee('Arena X');
        $response->assertDontSee('Arena Alheia');
    }

    public function test_organizer_can_invite_a_player_to_an_existing_game(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $playerProfile = $this->createPlayerProfile($player);
        $game = $this->createGame($organizer);

        $response = $this->actingAs($organizer)->post("/invitations/{$playerProfile->id}", [
            'game_id' => $game->id,
            'position' => 'Goleiro',
            'value' => '50.00',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('players.show', $playerProfile));

        $this->assertSame(1, Invitation::count());

        $invitation = Invitation::first();
        $this->assertSame($game->id, $invitation->game_id);
        $this->assertSame($organizer->id, $invitation->organizer_id);
        $this->assertSame($player->id, $invitation->user_id);
        $this->assertSame('Goleiro', $invitation->position);
        $this->assertSame('50.00', $invitation->value);
        $this->assertSame(Invitation::STATUS_PENDING, $invitation->status);
    }

    public function test_organizer_cannot_invite_the_same_player_to_the_same_game_twice(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $playerProfile = $this->createPlayerProfile($player);
        $game = $this->createGame($organizer);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($organizer)->post("/invitations/{$playerProfile->id}", [
            'game_id' => $game->id,
        ]);

        $response->assertSessionHasErrors('game_id');
        $this->assertSame(1, Invitation::count());
    }

    public function test_organizer_cannot_invite_a_player_to_another_organizers_game(): void
    {
        $organizer = User::factory()->organizer()->create();
        $otherOrganizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $playerProfile = $this->createPlayerProfile($player);
        $game = $this->createGame($otherOrganizer);

        $response = $this->actingAs($organizer)->post("/invitations/{$playerProfile->id}", [
            'game_id' => $game->id,
        ]);

        $response->assertSessionHasErrors('game_id');
        $this->assertSame(0, Invitation::count());
    }

    public function test_player_cannot_send_invitations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $playerProfile = $this->createPlayerProfile($player);
        $game = $this->createGame($organizer);

        $response = $this->actingAs($player)->post("/invitations/{$playerProfile->id}", [
            'game_id' => $game->id,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Invitation::count());
    }

    public function test_player_sees_pending_invitation_with_match_details(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'team' => 'Amigos FC',
            'position' => 'Goleiro',
            'value' => '50.00',
            'status' => Invitation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($player)->get('/invitations');

        $response->assertOk();
        $response->assertSee('Você está disponível para jogar?');
        $response->assertSee('Amigos FC');
        $response->assertSee('Arena X');
        $response->assertSee('Society');
        $response->assertSee('Goleiro');
        $response->assertSee('50,00');
    }

    public function test_invited_player_can_accept_the_invitation(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($player)->patch("/invitations/{$invitation->id}/accept");

        $response->assertRedirect(route('invitations.index'));
        $this->assertSame(Invitation::STATUS_ACCEPTED, $invitation->fresh()->status);
    }

    public function test_invited_player_can_decline_the_invitation(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($player)->patch("/invitations/{$invitation->id}/decline");

        $response->assertRedirect(route('invitations.index'));
        $this->assertSame(Invitation::STATUS_DECLINED, $invitation->fresh()->status);
    }

    public function test_a_player_who_was_not_invited_cannot_accept_the_invitation(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $intruder = User::factory()->create();
        $game = $this->createGame($organizer);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($intruder)->patch("/invitations/{$invitation->id}/accept");

        $response->assertForbidden();
        $this->assertSame(Invitation::STATUS_PENDING, $invitation->fresh()->status);
    }

    public function test_a_player_who_was_not_invited_cannot_decline_the_invitation(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $intruder = User::factory()->create();
        $game = $this->createGame($organizer);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($intruder)->patch("/invitations/{$invitation->id}/decline");

        $response->assertForbidden();
        $this->assertSame(Invitation::STATUS_PENDING, $invitation->fresh()->status);
    }

    public function test_organizer_cannot_accept_or_decline_an_invitation(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer);

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $this->actingAs($organizer)->patch("/invitations/{$invitation->id}/accept")->assertForbidden();
        $this->assertSame(Invitation::STATUS_PENDING, $invitation->fresh()->status);
    }
}
