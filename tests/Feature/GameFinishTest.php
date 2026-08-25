<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameFinishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every game here is placed hours before or after "now" but always
        // on today's date. Run near midnight, that arithmetic wraps to the
        // other side of the day and flips the eligibility rule, so the
        // clock is pinned to midday.
        $this->travelTo(today()->setTime(12, 0));
    }

    private function createGame(User $organizer, array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Amigos FC',
            'location' => 'Arena X',
            'city' => 'Teresina',
            'modality' => 'Society',
            'date' => now()->format('Y-m-d'),
            'start_time' => now()->subHours(3)->format('H:i'),
            'end_time' => now()->subHour()->format('H:i'),
            'max_players' => 10,
            'price' => '50.00',
            'positions' => ['Goleiro', 'Zagueiro'],
            'status' => 'open',
        ], $attributes));
    }

    public function test_organizer_can_finish_a_game_whose_end_time_has_passed(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $response = $this->actingAs($organizer)->patch(route('games.finish', $game));

        $response->assertRedirect(route('games.mine'));
        $this->assertSame('finished', $game->fresh()->status);
    }

    public function test_organizer_cannot_finish_a_game_that_has_not_ended_yet(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, [
            'start_time' => now()->addHour()->format('H:i'),
            'end_time' => now()->addHours(2)->format('H:i'),
        ]);

        $response = $this->actingAs($organizer)->patch(route('games.finish', $game));

        $response->assertForbidden();
        $this->assertSame('open', $game->fresh()->status);
    }

    public function test_organizer_can_finish_a_game_without_an_end_time_once_the_start_time_has_passed(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, [
            'start_time' => now()->subHour()->format('H:i'),
            'end_time' => null,
        ]);

        $response = $this->actingAs($organizer)->patch(route('games.finish', $game));

        $response->assertRedirect(route('games.mine'));
        $this->assertSame('finished', $game->fresh()->status);
    }

    public function test_organizer_cannot_finish_another_organizers_game(): void
    {
        $organizer = User::factory()->organizer()->create();
        $otherOrganizer = User::factory()->organizer()->create();
        $game = $this->createGame($otherOrganizer);

        $response = $this->actingAs($organizer)->patch(route('games.finish', $game));

        $response->assertForbidden();
        $this->assertSame('open', $game->fresh()->status);
    }

    public function test_organizer_cannot_finish_an_already_finished_game(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['status' => 'finished']);

        $response = $this->actingAs($organizer)->patch(route('games.finish', $game));

        $response->assertForbidden();
    }

    public function test_organizer_cannot_finish_a_cancelled_game(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['status' => 'cancelled']);

        $response = $this->actingAs($organizer)->patch(route('games.finish', $game));

        $response->assertForbidden();
    }

    public function test_player_cannot_finish_a_game(): void
    {
        $player = User::factory()->create();
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $response = $this->actingAs($player)->patch(route('games.finish', $game));

        $response->assertForbidden();
        $this->assertSame('open', $game->fresh()->status);
    }

    public function test_games_mine_shows_finish_button_when_eligible_and_rate_button_after_finished(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['location' => 'Quadra Elegível']);

        $response = $this->actingAs($organizer)->get('/games/mine');
        $response->assertOk();
        $response->assertSee('Finalizar Partida');
        $response->assertDontSee('Avaliar Jogadores');

        $this->actingAs($organizer)->patch(route('games.finish', $game));

        $response = $this->actingAs($organizer)->get('/games/mine');
        $response->assertOk();
        $response->assertSee('Avaliar Jogadores');
        $response->assertDontSee('Finalizar Partida');
    }
}
