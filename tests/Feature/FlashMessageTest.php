<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Support\Flash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * O retorno de uma ação: um mapa só, um lugar só na tela.
 *
 * Isto era uma escada de `@if (session('status') === '...')` repetida em
 * dezessete views — quarenta e uma comparações de string escritas à mão,
 * cada mensagem nova exigindo mexer em dois arquivos que não se conhecem.
 * Duas já tinham escorregado pela fresta: `notifications-read`, emitido
 * por um controller e mostrado por nenhuma view, e `sos-invite-sent`, um
 * ramo que rota nenhuma alcançava mais.
 *
 * Os dois primeiros testes existem para fechar essa fresta dos dois lados;
 * os de baixo cobrem o que a pessoa vê.
 */
class FlashMessageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Emitido sem texto = mensagem que some. Foi assim que
     * `notifications-read` passou.
     */
    public function test_every_status_a_controller_emits_has_a_message(): void
    {
        $emitted = $this->literalStatusesEmitted();

        $this->assertNotEmpty($emitted, 'A varredura não achou status nenhum — o padrão deve ter mudado.');

        $orphans = array_values(array_diff($emitted, Flash::keys(), Flash::HANDLED_IN_PLACE));

        $this->assertSame([], $orphans, 'Status sem texto em App\Support\Flash.');
    }

    /**
     * E o outro lado: texto sem ninguém que o emita é uma mensagem que
     * nunca aparece, e que ninguém sabe que pode apagar.
     */
    public function test_every_message_in_the_map_is_actually_used(): void
    {
        $source = $this->controllerSource();

        $unused = array_values(array_filter(
            Flash::keys(),
            fn (string $key) => ! str_contains($source, "'".$key."'"),
        ));

        $this->assertSame([], $unused, 'Mensagem em App\Support\Flash que controller nenhum emite.');
    }

    public function test_no_view_compares_status_strings_by_hand_any_more(): void
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->filter(fn ($file) => str_contains($file->getContents(), "session('status') ==="))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        // A tela de verificação de e-mail é a exceção declarada: o aviso
        // vive colado ao campo de e-mail, não é retorno de página.
        $this->assertSame(
            ['profile'.DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR.'update-profile-information-form.blade.php'],
            $offenders,
        );
    }

    public function test_the_toast_shows_up_after_the_action(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->game($organizer);

        $this->actingAs($organizer)
            ->from(route('games.show', $game))
            ->patch(route('games.cancel', $game))
            ->assertRedirect();

        $this->actingAs($organizer)
            ->get(route('games.show', $game))
            ->assertOk()
            ->assertSee('Partida cancelada.')
            ->assertSee('role="status"', false);
    }

    /**
     * Um erro fica até alguém fechar, e se anuncia como alerta: ele
     * explica por que algo não aconteceu, e sumir antes de ser lido é pior
     * do que não ter aparecido.
     */
    public function test_a_refused_action_reaches_the_person_who_tried_it(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->game($organizer);
        $target = $this->playerProfile();

        $invite = fn () => $this->actingAs($organizer)
            ->from(route('players.search'))
            ->post(route('games.invitations.store', [$game, $target]));

        $invite();

        // O segundo convite é recusado, e a recusa volta para a busca —
        // que não tem campo `user_id` nenhum onde a mensagem pudesse ter
        // ficado pendurada.
        $invite()->assertRedirect(route('players.search'));

        $this->actingAs($organizer)
            ->get(route('players.search'))
            ->assertOk()
            ->assertSee('Este jogador já foi convidado para essa partida.')
            ->assertSee('role="alert"', false);
    }

    /**
     * Sem nada a dizer, nada no DOM: o toast não é um lugar que fica vazio
     * esperando algo aparecer.
     */
    public function test_a_page_without_feedback_renders_no_toast(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->get(route('games.mine'))
            ->assertOk()
            ->assertDontSee('flash-top', false);
    }

    /**
     * As frases já traduzidas do fluxo de senha passam pela mesma sessão e
     * não são chaves nossas. Virar toast com o texto cru seria pior do que
     * não aparecer.
     */
    public function test_an_unknown_status_is_not_turned_into_a_toast(): void
    {
        $this->assertNull(Flash::resolve('passwords.sent'));
        $this->assertNull(Flash::resolve(null));
    }

    // ==================== APOIO ====================

    /** @return array<int, string> */
    private function literalStatusesEmitted(): array
    {
        preg_match_all(
            "/with\('status', '([a-z0-9-]+)'\)/",
            $this->controllerSource(),
            $matches,
        );

        return array_values(array_unique($matches[1]));
    }

    private function controllerSource(): string
    {
        return collect(File::allFiles(app_path('Http/Controllers')))
            ->map(fn ($file) => $file->getContents())
            ->implode("\n");
    }

    private function game(User $organizer): Game
    {
        return Game::create([
            'user_id' => $organizer->id,
            'team_name' => 'Pelada do Zé',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => today()->addDays(3)->format('Y-m-d'),
            'start_time' => '19:00',
            'max_players' => 10,
            'price' => '25.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
        ]);
    }

    private function playerProfile(): PlayerProfile
    {
        $user = User::factory()->create(['state' => 'PI']);

        return PlayerProfile::create([
            'user_id' => $user->id,
            'birth_date' => '1995-05-10',
            'state' => 'PI',
            'city' => 'Teresina',
            'phone' => '86999999999',
            'positions' => ['Atacante'],
            'modalities' => ['Society'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
            'plays_outside_city' => false,
        ]);
    }
}
