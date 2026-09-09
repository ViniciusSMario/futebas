<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Compartilhar o link público da partida.
 *
 * Esse link é o laço de crescimento do app inteiro — o organizador cria a
 * pelada aqui e as vagas se completam no grupo do WhatsApp — e era um
 * `<input readonly>` com `onclick="this.select()"` ao lado de um botão que
 * *abria* o link em outra aba. Nenhum dos dois compartilhava nada, e
 * selecionar texto dentro de um input é o gesto que ninguém faz no celular.
 *
 * A folha nativa e a cópia vivem no navegador. O que se fixa daqui é o
 * caminho que funciona sem JavaScript nenhum: o link do WhatsApp já
 * montado, com a pelada escrita por extenso.
 */
class GameShareTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-09 08:00:00'));

        $this->organizer = User::factory()->organizer()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * O recado vai pronto. Sem ele o organizador cola uma URL solta no
     * grupo e digita a pelada de novo à mão — o trabalho que este app
     * existe para tirar dele.
     */
    public function test_the_whatsapp_link_carries_the_match_written_out(): void
    {
        $game = $this->game();

        $response = $this->actingAs($this->organizer)->get(route('games.show', $game));

        $response->assertOk();

        $expected = 'https://wa.me/?text='.rawurlencode(
            'Pelada do Zé — Sáb, 12/09 · 19:00. Arena Society Central, Teresina. Restam 10 vagas. '
            .'Entra aí: '.route('public-games.show', $game)
        );

        $response->assertSee($expected, false);
    }

    /**
     * Partida lotada não deixa de ser compartilhável: a lista de espera é
     * exatamente o que se está oferecendo.
     */
    public function test_a_full_match_offers_the_waiting_list_instead_of_spots(): void
    {
        $game = $this->game(['max_players' => 1]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => User::factory()->create()->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($this->organizer)
            ->get(route('games.show', $game))
            ->assertOk()
            ->assertSee(rawurlencode('Lotada, mas dá para entrar na lista de espera.'), false);
    }

    /** O link em si continua à vista, e continua selecionável. */
    public function test_the_url_itself_is_still_on_the_page(): void
    {
        $game = $this->game();

        $this->actingAs($this->organizer)
            ->get(route('games.show', $game))
            ->assertOk()
            ->assertSee(route('public-games.show', $game));
    }

    /**
     * Divulgar a partida é do organizador. Quem joga abre a mesma tela e
     * não recebe o painel de divulgação junto.
     */
    public function test_a_participant_does_not_get_the_share_panel(): void
    {
        $game = $this->game();
        $player = User::factory()->create();

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($player)
            ->get(route('games.show', $game))
            ->assertOk()
            ->assertDontSee('Chamar gente')
            ->assertDontSee('wa.me', false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function game(array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $this->organizer->id,
            'team_name' => 'Pelada do Zé',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => '2026-09-12',
            'start_time' => '19:00',
            'max_players' => 10,
            'price' => '25.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
        ], $attributes));
    }
}
