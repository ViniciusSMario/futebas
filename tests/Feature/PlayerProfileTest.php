<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_player_profile(): void
    {
        $response = $this->get('/player-profile');

        $response->assertRedirect('/login');
    }

    public function test_player_profile_edit_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/player-profile');

        $response->assertOk();
    }

    public function test_player_profile_can_be_created(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/player-profile', [
            'name' => 'Jogador Teste',
            'photo' => UploadedFile::fake()->image('photo.jpg'),
            'birth_date' => '1995-05-10',
            'state' => 'SP',
            'city' => 'São Paulo',
            'phone' => '11999999999',
            'position_primary' => 'Atacante',
            'positions_secondary' => ['Meia', 'Volante'],
            'modalities' => ['Society', 'Futsal'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
            'plays_outside_city' => '1',
            'price_per_game_outside' => '80.00',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/player-profile');

        $user->refresh();
        $this->assertSame('Jogador Teste', $user->name);

        $profile = $user->playerProfile;
        $this->assertNotNull($profile);
        $this->assertSame(['Atacante', 'Meia', 'Volante'], $profile->positions);
        $this->assertSame(['Society', 'Futsal'], $profile->modalities);
        $this->assertSame('SP', $profile->state);
        $this->assertSame('São Paulo', $profile->city);
        $this->assertSame('Avançado', $profile->level);
        $this->assertSame('50.00', $profile->price_per_game);
        $this->assertTrue($profile->plays_outside_city);
        $this->assertSame('80.00', $profile->price_per_game_outside);
        $this->assertNotNull($profile->photo_path);
        Storage::disk('public')->assertExists($profile->photo_path);
    }

    public function test_price_per_game_outside_is_required_when_plays_outside_city(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/player-profile', [
            'name' => 'Jogador Teste',
            'birth_date' => '1995-05-10',
            'state' => 'SP',
            'city' => 'São Paulo',
            'phone' => '11999999999',
            'position_primary' => 'Atacante',
            'modalities' => ['Society'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
            'plays_outside_city' => '1',
        ]);

        $response->assertSessionHasErrors('price_per_game_outside');
        $this->assertNull(PlayerProfile::first());
    }

    public function test_player_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/player-profile', [
            'name' => 'Jogador Original',
            'birth_date' => '1995-05-10',
            'state' => 'SP',
            'city' => 'São Paulo',
            'phone' => '11999999999',
            'position_primary' => 'Goleiro',
            'modalities' => ['Futsal'],
            'level' => 'Iniciante',
            'price_per_game' => '30.00',
        ]);

        $response = $this->actingAs($user)->put('/player-profile', [
            'name' => 'Jogador Atualizado',
            'birth_date' => '1995-05-10',
            'state' => 'RJ',
            'city' => 'Rio de Janeiro',
            'phone' => '21999999999',
            'position_primary' => 'Lateral',
            'modalities' => ['Campo'],
            'level' => 'Intermediário',
            'price_per_game' => '40.00',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame(1, PlayerProfile::count());

        $profile = $user->playerProfile()->first();
        $this->assertSame('RJ', $profile->state);
        $this->assertSame('Rio de Janeiro', $profile->city);
        $this->assertSame(['Lateral'], $profile->positions);
        $this->assertSame('Intermediário', $profile->level);
    }
}
