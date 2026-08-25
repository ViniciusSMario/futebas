<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_organizer_can_update_contact_info_and_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->organizer()->create([
            'state' => 'SP',
            'city' => 'Campinas',
            'phone' => '11988888888',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Organizador Atualizado',
                'email' => $user->email,
                'state' => 'RJ',
                'city' => 'Rio de Janeiro',
                'phone' => '11977777777',
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Organizador Atualizado', $user->name);
        $this->assertSame('RJ', $user->state);
        $this->assertSame('Rio de Janeiro', $user->city);
        $this->assertSame('11977777777', $user->phone);
        $this->assertNotNull($user->photo_path);
        Storage::disk('public')->assertExists($user->photo_path);
    }

    public function test_organizer_state_city_and_phone_are_required_when_updating_profile(): void
    {
        $user = User::factory()->organizer()->create([
            'state' => 'SP',
            'city' => 'Campinas',
            'phone' => '11988888888',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Organizador Teste',
                'email' => $user->email,
            ]);

        $response->assertSessionHasErrors(['state', 'city', 'phone']);
    }

    public function test_player_profile_page_does_not_require_city_and_phone(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Jogador Teste',
                'email' => $user->email,
            ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
