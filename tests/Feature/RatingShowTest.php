<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Invitation;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingShowTest extends TestCase
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
            'phone' => '86999999999',
            'positions' => ['Goleiro'],
            'modalities' => ['Society'],
            'level' => 'Intermediário',
            'price_per_game' => '40.00',
            'plays_outside_city' => false,
        ], $attributes));
    }

    public function test_guests_cannot_view_a_players_ratings(): void
    {
        $player = User::factory()->create();

        $response = $this->get("/ratings/{$player->id}");

        $response->assertRedirect('/login');
    }

    public function test_player_sees_an_empty_state_when_they_have_no_ratings(): void
    {
        $player = User::factory()->create();
        $this->createPlayerProfile($player);

        $response = $this->actingAs($player)->get(route('ratings.show', $player));

        $response->assertOk();
        $response->assertSee('Nenhuma avaliação ainda');
    }

    public function test_player_sees_their_own_ratings_with_organizer_and_comment(): void
    {
        $organizer = User::factory()->organizer()->create(['name' => 'Organizador Teste']);
        $player = User::factory()->create();
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'team' => $game->team_name,
            'position' => 'Goleiro',
            'status' => Invitation::STATUS_ACCEPTED,
        ]);

        $this->actingAs($organizer)->post(route('ratings.store', [$game, $player]), [
            'overall_rating' => 5,
            'punctuality_rating' => 4,
            'behavior_rating' => 5,
            'performance_rating' => 4,
            'comment' => 'Excelente goleiro.',
        ]);

        $response = $this->actingAs($player)->get(route('ratings.show', $player));

        $response->assertOk();
        $response->assertSee('Organizador Teste');
        $response->assertSee('Amigos FC');
        $response->assertSee('Excelente goleiro.');
        $response->assertDontSee('Nenhuma avaliação ainda');
    }

    public function test_player_sees_their_average_scores(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $this->createPlayerProfile($player);
        $game = $this->createGame($organizer, ['status' => 'finished']);

        Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'user_id' => $player->id,
            'status' => Invitation::STATUS_ACCEPTED,
        ]);

        $this->actingAs($organizer)->post(route('ratings.store', [$game, $player]), [
            'overall_rating' => 5,
            'punctuality_rating' => 5,
            'behavior_rating' => 5,
            'performance_rating' => 5,
        ]);

        $response = $this->actingAs($player)->get(route('ratings.show', $player));

        $response->assertOk();
        $response->assertSee('5,0');
    }

    public function test_a_player_cannot_view_another_players_ratings(): void
    {
        $player = User::factory()->create();
        $otherPlayer = User::factory()->create();
        $this->createPlayerProfile($otherPlayer);

        $response = $this->actingAs($player)->get(route('ratings.show', $otherPlayer));

        $response->assertForbidden();
    }

    public function test_organizer_cannot_view_ratings_via_the_player_route(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $this->createPlayerProfile($player);

        $response = $this->actingAs($organizer)->get(route('ratings.show', $player));

        $response->assertForbidden();
    }

    public function test_dashboard_sidebar_links_to_the_players_own_ratings(): void
    {
        $player = User::factory()->create();
        $this->createPlayerProfile($player);

        $response = $this->actingAs($player)->get('/dashboard');

        $response->assertOk();
        $response->assertSee(route('ratings.show', $player));
    }
}
