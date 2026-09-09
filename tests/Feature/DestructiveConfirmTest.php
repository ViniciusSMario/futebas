<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SosRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Ação destrutiva pergunta antes, e diz o que vai acontecer.
 *
 * O `confirm()` do navegador saiu de cena por dois motivos: dentro do app
 * instalado ele aparece com a cara do sistema no meio de uma tela escura,
 * e é uma linha só — "Cancelar esse Game?" nunca teve onde dizer que os
 * SOS abertos morrem junto. A pergunta e a consequência são coisas
 * diferentes, e é a segunda que faz alguém mudar de ideia.
 *
 * O diálogo em si é do SweetAlert e vive no navegador; o que dá para fixar
 * daqui é o contrato entre o Blade e ele — os `data-confirm` estarem lá, e
 * ninguém ter voltado ao `confirm()` nativo.
 */
class DestructiveConfirmTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->organizer()->create();
    }

    /**
     * A guarda que importa a longo prazo: um `onsubmit="return confirm(...)"`
     * novo funciona, parece certo em revisão, e desfaz tudo isto em
     * silêncio.
     */
    public function test_no_view_falls_back_to_the_browser_confirm(): void
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->filter(fn ($file) => str_contains($file->getContents(), 'return confirm('))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'Use data-confirm em vez do confirm() do navegador.');
    }

    public function test_cancelling_a_match_says_what_it_costs(): void
    {
        $game = $this->game();

        $response = $this->actingAs($this->organizer)->get(route('games.show', $game));

        $response->assertOk();
        $response->assertSee('data-confirm="Cancelar esta partida?"', false);
        // A frase que o confirm() nativo nunca teve espaço para dizer.
        $response->assertSee('qualquer SOS aberto para ela é encerrado junto', false);
    }

    /**
     * Perguntar "remover esse jogador?" numa lista de doze linhas não diz
     * qual delas; o nome diz.
     */
    public function test_removing_a_player_names_the_player(): void
    {
        $game = $this->game();
        $player = User::factory()->create(['name' => 'Rafael Jogador']);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->actingAs($this->organizer)
            ->get(route('games.show', ['game' => $game, 'tab' => 'participantes']))
            ->assertOk()
            ->assertSee('data-confirm="Remover Rafael Jogador da partida?"', false);
    }

    /**
     * O texto antigo dizia "os candidatos pendentes serão avisados" e nada
     * sobre a partida — que continua de pé, e é a dúvida real de quem está
     * com o dedo no botão.
     */
    public function test_cancelling_an_sos_says_the_match_survives(): void
    {
        $game = $this->game();

        $sos = SosRequest::create([
            'game_id' => $game->id,
            'organizer_id' => $this->organizer->id,
            'position' => SosRequest::POSITION,
            'offered_value' => '60.00',
            'status' => SosRequest::STATUS_OPEN,
            'expires_at' => $game->startsAt(),
        ]);

        $this->actingAs($this->organizer)
            ->get(route('sos.show', $sos))
            ->assertOk()
            ->assertSee('data-confirm="Cancelar esta chamada?"', false)
            ->assertSee('A partida continua de pé', false);
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
            'date' => today()->addDays(3)->format('Y-m-d'),
            'start_time' => '19:00',
            'max_players' => 10,
            'price' => '25.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
        ], $attributes));
    }
}
