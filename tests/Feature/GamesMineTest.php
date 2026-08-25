<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamesMineTest extends TestCase
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
            'max_players' => 10,
            'price' => '25.00',
            'positions' => ['Goleiro', 'Zagueiro'],
            'status' => 'open',
        ], $attributes));
    }

    public function test_guests_cannot_access_my_games(): void
    {
        $response = $this->get('/games/mine');

        $response->assertRedirect('/login');
    }

    public function test_organizer_sees_their_own_upcoming_game_as_confirmed(): void
    {
        $organizer = User::factory()->create();
        $game = $this->createGame($organizer, ['location' => 'Quadra do Organizador']);

        $response = $this->actingAs($organizer)->get('/games/mine');

        $response->assertOk();
        $response->assertSeeInOrder(['Confirmadas', 'Quadra do Organizador']);
        $response->assertSee('Organizador');
    }

    public function test_player_who_accepted_invitation_sees_game_as_confirmed_with_team_and_position(): void
    {
        $organizer = User::factory()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, ['location' => 'Quadra Confirmada']);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'team' => 'Time A',
            'position' => 'Goleiro',
            'status' => 'accepted',
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($player)->get('/games/mine');

        $response->assertOk();
        $response->assertSeeInOrder(['Confirmadas', 'Quadra Confirmada']);
        $response->assertSee('Time A');
        $response->assertSee('Goleiro');
        $response->assertSee('Convidado');
    }

    public function test_player_with_pending_invitation_sees_game_as_pending(): void
    {
        $organizer = User::factory()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, ['location' => 'Quadra Pendente']);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'team' => 'Time B',
            'position' => 'Atacante',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($player)->get('/games/mine');

        $response->assertOk();
        $response->assertSeeInOrder(['Pendentes', 'Quadra Pendente']);
    }

    public function test_player_who_declined_invitation_does_not_see_the_game(): void
    {
        $organizer = User::factory()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, ['location' => 'Quadra Recusada']);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => 'declined',
        ]);

        $response = $this->actingAs($player)->get('/games/mine');

        $response->assertOk();
        $response->assertDontSee('Quadra Recusada');
    }

    public function test_past_game_appears_as_finished_for_organizer_and_player(): void
    {
        $organizer = User::factory()->create();
        $player = User::factory()->create();
        $game = $this->createGame($organizer, [
            'location' => 'Quadra Antiga',
            'date' => now()->subDays(2)->format('Y-m-d'),
        ]);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => 'accepted',
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $organizerResponse = $this->actingAs($organizer)->get('/games/mine');
        $organizerResponse->assertSeeInOrder(['Finalizadas', 'Quadra Antiga']);

        $playerResponse = $this->actingAs($player)->get('/games/mine');
        $playerResponse->assertSeeInOrder(['Finalizadas', 'Quadra Antiga']);
    }

    public function test_cancelled_game_is_shown_as_finalized_with_cancelled_label(): void
    {
        $organizer = User::factory()->create();
        $game = $this->createGame($organizer, [
            'location' => 'Quadra Cancelada',
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($organizer)->get('/games/mine');

        $response->assertSeeInOrder(['Finalizadas', 'Cancelada', 'Quadra Cancelada']);
    }

    public function test_user_only_sees_games_they_participate_in(): void
    {
        $organizer = User::factory()->create();
        $player = User::factory()->create();
        $outsider = User::factory()->create();

        $game = $this->createGame($organizer, ['location' => 'Quadra Privada']);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($outsider)->get('/games/mine');

        $response->assertOk();
        $response->assertDontSee('Quadra Privada');
    }
}
