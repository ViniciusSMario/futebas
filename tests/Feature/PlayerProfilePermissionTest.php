<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerProfilePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function createPlayer(array $attributes = []): PlayerProfile
    {
        $user = User::factory()->create([
            'name' => $attributes['name'] ?? 'Jogador Teste',
        ]);

        return PlayerProfile::create(array_merge([
            'user_id' => $user->id,
            'birth_date' => '1995-05-10',
            'state' => 'SP',
            'city' => 'São Paulo',
            'phone' => '11999999999',
            'positions' => ['Atacante'],
            'modalities' => ['Society'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
            'plays_outside_city' => false,
        ], $attributes));
    }

    public function test_organizer_can_view_a_players_public_profile_and_invite_them(): void
    {
        $viewer = User::factory()->organizer()->create();
        $player = $this->createPlayer(['name' => 'Fernanda Lima']);

        $response = $this->actingAs($viewer)->get(route('players.show', $player));

        $response->assertOk();
        $response->assertSee('Fernanda Lima');
        $response->assertSee('Convidar para Partida');
    }

    public function test_another_player_can_view_the_profile_but_not_invite(): void
    {
        $viewer = User::factory()->create();
        $player = $this->createPlayer(['name' => 'Fernanda Lima']);

        $response = $this->actingAs($viewer)->get(route('players.show', $player));

        $response->assertOk();
        $response->assertSee('Fernanda Lima');
        $response->assertDontSee('Convidar para Partida');
    }

    public function test_players_show_page_does_not_expose_an_edit_form_for_other_players(): void
    {
        $viewer = User::factory()->create();
        $player = $this->createPlayer();

        $response = $this->actingAs($viewer)->get(route('players.show', $player));

        $response->assertOk();
        $response->assertDontSee('name="price_per_game"', false);
        $response->assertDontSee('name="city"', false);
        $response->assertDontSee('name="level"', false);
    }

    public function test_owner_viewing_their_own_profile_sees_no_invite_button(): void
    {
        $player = $this->createPlayer(['name' => 'Dono do Perfil']);

        $response = $this->actingAs($player->user)->get(route('players.show', $player));

        $response->assertOk();
        $response->assertSee('Este é o seu perfil.');
        $response->assertDontSee('Convidar para Partida');
    }

    public function test_a_user_cannot_edit_another_users_player_profile_via_the_update_route(): void
    {
        $owner = $this->createPlayer([
            'name' => 'Jogador Original',
            'city' => 'São Paulo',
            'level' => 'Iniciante',
        ]);

        $attacker = User::factory()->create(['name' => 'Invasor']);

        $response = $this->actingAs($attacker)->put('/player-profile', [
            'name' => 'Nome Trocado',
            'birth_date' => '1990-01-01',
            'state' => 'RJ',
            'city' => 'Rio de Janeiro',
            'phone' => '21999999999',
            'position_primary' => 'Goleiro',
            'modalities' => ['Futsal'],
            'level' => 'Avançado',
            'price_per_game' => '999.00',
        ]);

        $response->assertSessionHasNoErrors();

        $owner->refresh();
        $this->assertSame('São Paulo', $owner->city);
        $this->assertSame('Iniciante', $owner->level);
        $this->assertSame('Jogador Original', $owner->user->fresh()->name);

        $attacker->refresh();
        $this->assertSame('Nome Trocado', $attacker->name);
        $this->assertSame('Rio de Janeiro', $attacker->playerProfile->city);

        $this->assertSame(2, PlayerProfile::count());
    }

    public function test_a_user_cannot_edit_another_users_availability_via_the_update_route(): void
    {
        $owner = User::factory()->create();
        Availability::create([
            'user_id' => $owner->id,
            'day_of_week' => 2,
            'start_time' => '18:00',
            'end_time' => '20:00',
        ]);

        $attacker = User::factory()->create();

        $response = $this->actingAs($attacker)->put('/availability', [
            'days' => [5],
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame(1, $owner->availabilities()->count());
        $this->assertSame(2, $owner->availabilities()->first()->day_of_week);

        $this->assertSame(1, $attacker->availabilities()->count());
        $this->assertSame(5, $attacker->availabilities()->first()->day_of_week);
    }

    public function test_invite_button_links_to_the_invitation_creation_route(): void
    {
        $viewer = User::factory()->organizer()->create();
        $player = $this->createPlayer();

        $response = $this->actingAs($viewer)->get(route('players.show', $player));

        $response->assertSee(route('invitations.create', $player), false);

        $inviteResponse = $this->actingAs($viewer)->get(route('invitations.create', $player));
        $inviteResponse->assertOk();
    }

    public function test_guest_cannot_view_a_players_public_profile(): void
    {
        $player = $this->createPlayer();

        $response = $this->get(route('players.show', $player));

        $response->assertRedirect('/login');
    }
}
