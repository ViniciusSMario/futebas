<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As rotas que qualquer pessoa alcança sem estar logada.
 *
 * O link público de uma partida nasce para circular em grupo de WhatsApp, o
 * que faz dele a única porta do app aberta para quem nunca se cadastrou — e
 * `participar-sem-cadastro` é a única dessas rotas que grava alguma coisa.
 * Os limites existem por causa dela.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function createGame(array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => User::factory()->organizer()->create()->id,
            'team_name' => 'Futebol de Quarta',
            'location' => 'Arena X',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '20:00',
            'end_time' => '21:00',
            'max_players' => 20,
            'price' => '10.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
            'requires_approval' => false,
        ], $attributes));
    }

    public function test_guest_join_stops_after_ten_attempts_from_the_same_address(): void
    {
        $game = $this->createGame();
        $url = route('public-games.join-guest', $game);

        for ($i = 0; $i < 10; $i++) {
            $this->post($url, ['name' => "Ze {$i}", 'phone' => '86999999999'])
                ->assertStatus(302);
        }

        $this->post($url, ['name' => 'Ze 11', 'phone' => '86999999999'])
            ->assertStatus(429);
    }

    public function test_the_limit_is_per_match_so_one_flooded_game_does_not_close_another(): void
    {
        $alvo = $this->createGame(['team_name' => 'Pelada Alvo']);
        $outra = $this->createGame(['team_name' => 'Pelada Vizinha']);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('public-games.join-guest', $alvo), ['name' => "Ze {$i}", 'phone' => '86999999999']);
        }

        $this->post(route('public-games.join-guest', $alvo), ['name' => 'Mais um', 'phone' => '86999999999'])
            ->assertStatus(429);

        // Mesmo IP, outra partida: o pessoal do mesmo wi-fi entrando em
        // peladas diferentes não pode pagar pelo engraçadinho da outra.
        $this->post(route('public-games.join-guest', $outra), ['name' => 'Vizinho', 'phone' => '86999999999'])
            ->assertStatus(302);
    }

    public function test_reading_the_public_page_is_not_limited_like_writing(): void
    {
        $game = $this->createGame();

        // Um grupo inteiro abre o link ao mesmo tempo, quase sempre pela
        // mesma operadora. Ler é o uso normal e tem folga.
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('public-games.show', $game))->assertOk();
        }
    }

    public function test_account_creation_stops_after_ten_attempts(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }

        $this->post('/register', [])->assertStatus(429);
    }

    public function test_login_stops_a_sweep_across_many_addresses(): void
    {
        // O limitador do LoginRequest conta por e-mail + IP, então quem
        // varre uma lista de e-mails a partir de um IP só nunca esbarra
        // nele. Este limite é o que vê essa varredura.
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', ['email' => "alvo{$i}@exemplo.com", 'password' => 'errada']);
        }

        $this->post('/login', ['email' => 'alvo999@exemplo.com', 'password' => 'errada'])
            ->assertStatus(429);
    }
}
