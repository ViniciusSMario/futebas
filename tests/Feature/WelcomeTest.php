<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_is_displayed(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Futebas');
        $response->assertSee('Seu futebol. Sua região. Sua partida.');
        $response->assertSee('Faltou jogador?');
    }

    public function test_landing_page_links_to_the_existing_login_and_register_routes(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('login'), false);
        $response->assertSee(route('register'), false);
    }

    public function test_guests_see_login_and_register_calls_to_action(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Criar conta');
        $response->assertSee('Entrar');
        $response->assertDontSee('Ir para o Dashboard');
    }

    public function test_authenticated_users_see_a_dashboard_link_instead(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Ir para o Dashboard');
        $response->assertSee(route('dashboard'), false);
    }
}
