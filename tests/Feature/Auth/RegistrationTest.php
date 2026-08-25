<?php

namespace Tests\Feature\Auth;

use App\Models\Availability;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_players_can_register_with_a_sports_profile_and_availability(): void
    {
        $response = $this->post('/register', [
            'role' => 'player',
            'name' => 'Jogador Teste',
            'email' => 'jogador@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'birth_date' => '1995-05-10',
            'state' => 'SP',
            'city' => 'São Paulo',
            'phone' => '11999999999',
            'position_primary' => 'Atacante',
            'positions_secondary' => ['Meia'],
            'modalities' => ['Society', 'Futsal'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
            'days' => [1, 3, 5],
            'start_time' => '18:00',
            'end_time' => '20:00',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'jogador@example.com')->firstOrFail();
        $this->assertSame('player', $user->role);
        $this->assertTrue($user->hasRole('player'));

        $profile = $user->playerProfile;
        $this->assertNotNull($profile);
        $this->assertSame(['Atacante', 'Meia'], $profile->positions);
        $this->assertSame(['Society', 'Futsal'], $profile->modalities);
        $this->assertSame('Avançado', $profile->level);
        $this->assertSame('SP', $profile->state);
        $this->assertSame('São Paulo', $profile->city);

        $this->assertSame(3, $user->availabilities()->count());
        $this->assertSame([1, 3, 5], $user->availabilities()->orderBy('day_of_week')->pluck('day_of_week')->all());
    }

    public function test_new_organizers_can_register_without_a_sports_profile(): void
    {
        $response = $this->post('/register', [
            'role' => 'organizer',
            'name' => 'Organizador Teste',
            'email' => 'organizador@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'phone' => '11988888888',
            'state' => 'SP',
            'city' => 'Campinas',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'organizador@example.com')->firstOrFail();
        $this->assertSame('organizer', $user->role);
        $this->assertSame('SP', $user->state);
        $this->assertSame('Campinas', $user->city);
        $this->assertSame('11988888888', $user->phone);

        $this->assertSame(0, PlayerProfile::count());
        $this->assertSame(0, Availability::count());
    }

    public function test_registration_requires_a_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'Sem Perfil',
            'email' => 'semperfil@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
    }

    public function test_player_registration_requires_sports_fields(): void
    {
        $response = $this->post('/register', [
            'role' => 'player',
            'name' => 'Jogador Incompleto',
            'email' => 'incompleto@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['birth_date', 'state', 'city', 'phone', 'position_primary', 'modalities', 'level', 'price_per_game', 'days', 'start_time', 'end_time']);
        $this->assertGuest();
    }
}
