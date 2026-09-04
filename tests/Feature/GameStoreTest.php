<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'team_name' => 'Furacão FC',
            'modality' => 'Society',
            'date' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'price' => '25.00',
            'max_players' => 20,
            'positions' => ['Goleiro', 'Zagueiro'],
        ], $overrides);
    }

    public function test_guests_cannot_access_the_create_game_page(): void
    {
        $response = $this->get('/games/create');

        $response->assertRedirect('/login');
    }

    public function test_organizer_can_view_the_create_game_page(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->get('/games/create');

        $response->assertOk();
    }

    public function test_player_cannot_access_the_create_game_page(): void
    {
        $player = User::factory()->create();

        $response = $this->actingAs($player)->get('/games/create');

        $response->assertForbidden();
    }

    public function test_organizer_can_create_a_game_request(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->post('/games', $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/games/mine');

        $this->assertSame(1, Game::count());

        $game = Game::first();
        $this->assertSame($organizer->id, $game->user_id);
        $this->assertSame('Furacão FC', $game->team_name);
        $this->assertSame('Society', $game->modality);
        $this->assertSame('Arena Society Central', $game->location);
        $this->assertSame('Teresina', $game->city);
        $this->assertSame('25.00', $game->price);
        $this->assertSame(['Goleiro', 'Zagueiro'], $game->positions);
        $this->assertSame(20, $game->max_players);
        $this->assertSame('open', $game->status);
        $this->assertNotEmpty($game->slug);
    }

    public function test_player_cannot_create_a_game_request(): void
    {
        $player = User::factory()->create();

        $response = $this->actingAs($player)->post('/games', $this->validPayload());

        $response->assertForbidden();
        $this->assertSame(0, Game::count());
    }

    public function test_required_fields_are_validated(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->post('/games', []);

        $response->assertSessionHasErrors([
            'team_name', 'modality', 'date', 'start_time', 'location', 'city', 'price', 'max_players',
        ]);
        $this->assertSame(0, Game::count());
    }

    public function test_positions_are_optional(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->post('/games', $this->validPayload(['positions' => []]));

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, Game::count());
        $this->assertSame([], Game::first()->positions);
    }

    public function test_an_invalid_position_is_rejected(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->post('/games', $this->validPayload(['positions' => ['Piloto']]));

        $response->assertSessionHasErrors('positions.0');
        $this->assertSame(0, Game::count());
    }

    public function test_date_cannot_be_in_the_past(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->post('/games', $this->validPayload([
            'date' => now()->subDay()->format('Y-m-d'),
        ]));

        $response->assertSessionHasErrors('date');
        $this->assertSame(0, Game::count());
    }
}
