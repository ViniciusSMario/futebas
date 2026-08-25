<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function organizer(): User
    {
        return User::factory()->organizer()->create();
    }

    private function playerWithProfile(): PlayerProfile
    {
        $user = User::factory()->create();

        return PlayerProfile::create([
            'user_id' => $user->id,
            'birth_date' => '1995-05-10',
            'city' => 'São Paulo',
            'phone' => '11999999999',
            'positions' => ['Atacante'],
            'modalities' => ['Society'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function playerOnlyRoutes(): array
    {
        return [
            'player-profile.edit' => ['player-profile.edit'],
            'availability.edit' => ['availability.edit'],
            'invitations.index' => ['invitations.index'],
            'sos-opportunities.index' => ['sos-opportunities.index'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function organizerOnlyRoutes(): array
    {
        return [
            'players.search' => ['players.search'],
            'games.create' => ['games.create'],
            'game-series.index' => ['game-series.index'],
            'game-series.create' => ['game-series.create'],
            'sos.index' => ['sos.index'],
            'sos.create' => ['sos.create'],
        ];
    }

    #[DataProvider('playerOnlyRoutes')]
    public function test_organizer_is_forbidden_from_player_only_routes(string $routeName): void
    {
        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)->get(route($routeName));

        $response->assertForbidden();
    }

    #[DataProvider('playerOnlyRoutes')]
    public function test_player_can_access_player_only_routes(string $routeName): void
    {
        $player = User::factory()->create();

        $response = $this->actingAs($player)->get(route($routeName));

        $response->assertOk();
    }

    #[DataProvider('organizerOnlyRoutes')]
    public function test_player_is_forbidden_from_organizer_only_routes(string $routeName): void
    {
        $player = User::factory()->create();

        $response = $this->actingAs($player)->get(route($routeName));

        $response->assertForbidden();
    }

    #[DataProvider('organizerOnlyRoutes')]
    public function test_organizer_can_access_organizer_only_routes(string $routeName): void
    {
        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)->get(route($routeName));

        $response->assertOk();
    }

    public function test_player_is_forbidden_from_sending_invitations(): void
    {
        $player = User::factory()->create();
        $target = $this->playerWithProfile();

        $response = $this->actingAs($player)->get(route('invitations.create', $target));

        $response->assertForbidden();
    }

    public function test_organizer_can_open_the_invitation_creation_page(): void
    {
        $organizer = $this->organizer();
        $target = $this->playerWithProfile();

        $response = $this->actingAs($organizer)->get(route('invitations.create', $target));

        $response->assertOk();
    }

    public function test_both_roles_can_access_shared_routes(): void
    {
        $player = User::factory()->create();
        $organizer = $this->organizer();

        foreach (['games.mine', 'games.search', 'dashboard'] as $routeName) {
            $this->actingAs($player)->get(route($routeName))->assertOk();
            $this->actingAs($organizer)->get(route($routeName))->assertOk();
        }
    }

    public function test_organizer_is_forbidden_from_updating_availability(): void
    {
        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)->put('/availability', [
            'days' => [1],
            'start_time' => '18:00',
            'end_time' => '20:00',
        ]);

        $response->assertForbidden();
    }

    public function test_organizer_cannot_update_a_player_profile(): void
    {
        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)->put('/player-profile', [
            'name' => 'Tentativa Organizador',
            'birth_date' => '1990-01-01',
            'city' => 'São Paulo',
            'phone' => '11999999999',
            'position_primary' => 'Goleiro',
            'modalities' => ['Futsal'],
            'level' => 'Avançado',
            'price_per_game' => '30.00',
        ]);

        $response->assertForbidden();
    }
}
