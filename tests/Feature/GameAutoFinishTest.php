<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `games:finish` — a partida que ninguém encerrou.
 *
 * O que está em jogo aqui não é o status: é a ficha de presença. Enquanto
 * a partida fica aberta ela não conta para ninguém, então um organizador
 * que some apaga aquela noite do histórico de todos os que jogaram. E o
 * contrário também precisa valer: fechar cedo demais fecharia partida
 * ainda em campo.
 */
class GameAutoFinishTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // Relógio parado: todo caso aqui é "quantas horas depois do fim", e
        // com now() de verdade uma rodada às 2h da manhã calcularia horários
        // no dia anterior e quebraria sozinha.
        Carbon::setTestNow(Carbon::create(2026, 8, 26, 21, 0));

        $this->organizer = User::factory()->organizer()->create(['city' => 'Teresina', 'state' => 'PI']);
    }

    public function test_a_match_nobody_closed_is_finished_once_the_grace_period_passes(): void
    {
        $game = $this->game([
            'date' => today()->format('Y-m-d'),
            'start_time' => '15:00',
            'end_time' => '17:00', // fim + 3h de folga = 20h, e agora são 21h
        ]);

        $this->artisan('games:finish')->assertSuccessful();

        $this->assertSame(Game::STATUS_FINISHED, $game->refresh()->status);
    }

    public function test_finishing_settles_the_attendance_of_everyone_who_played(): void
    {
        $game = $this->finishableGame();
        $player = $this->player('Quem Jogou');

        $this->join($game, $player->user);

        // Aberta, a partida não conta para ninguém.
        $player->user->playerProfile->recalculateAttendanceStats();
        $this->assertSame(0, $player->refresh()->games_played);

        $this->artisan('games:finish')->assertSuccessful();

        $this->assertSame(1, $player->refresh()->games_played);
    }

    public function test_the_organizer_can_rate_a_match_the_clock_closed(): void
    {
        $game = $this->finishableGame();
        $player = $this->player('Avaliável');
        $this->join($game, $player->user);

        // Antes: avaliar é recusado porque a partida não terminou.
        $this->actingAs($this->organizer)->get(route('ratings.index', $game))->assertForbidden();

        $this->artisan('games:finish')->assertSuccessful();

        $this->actingAs($this->organizer)->get(route('ratings.index', $game))->assertOk();
    }

    public function test_a_match_still_within_the_grace_period_is_left_alone(): void
    {
        $game = $this->game([
            'date' => today()->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00', // acabou há uma hora: ainda dentro da folga
        ]);

        $this->artisan('games:finish')->assertSuccessful();

        $this->assertSame(Game::STATUS_OPEN, $game->refresh()->status);
    }

    public function test_a_match_without_an_end_time_is_not_closed_at_kickoff(): void
    {
        // Sem `end_time`, finishesAt() cai para o horário de início: é o
        // caso que a folga existe para proteger — a partida começou há uma
        // hora e está rolando agora.
        $game = $this->game([
            'date' => today()->format('Y-m-d'),
            'start_time' => '20:00', // começou há uma hora e está rolando
            'end_time' => null,
        ]);

        $this->artisan('games:finish')->assertSuccessful();

        $this->assertSame(Game::STATUS_OPEN, $game->refresh()->status);
    }

    public function test_a_cancelled_match_is_never_resurrected_as_finished(): void
    {
        $game = $this->finishableGame(['status' => Game::STATUS_CANCELLED]);

        $this->artisan('games:finish')->assertSuccessful();

        $this->assertSame(Game::STATUS_CANCELLED, $game->refresh()->status);
    }

    public function test_running_twice_finishes_nothing_the_second_time(): void
    {
        $this->finishableGame();

        $this->artisan('games:finish')->expectsOutput('1 partida(s) finalizada(s).');
        $this->artisan('games:finish')->expectsOutput('0 partida(s) finalizada(s).');
    }

    public function test_a_future_match_is_untouched(): void
    {
        $game = $this->game([
            'date' => today()->addDays(3)->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
        ]);

        $this->artisan('games:finish')->assertSuccessful();

        $this->assertSame(Game::STATUS_OPEN, $game->refresh()->status);
    }

    public function test_a_no_show_marked_after_the_clock_closed_still_counts(): void
    {
        $game = $this->finishableGame();
        $player = $this->player('Faltou');
        $gamePlayer = $this->join($game, $player->user);

        $this->artisan('games:finish')->assertSuccessful();
        $this->assertSame(1, $player->refresh()->games_played);

        // Marcar falta continua liberado depois de finalizada, e refaz a
        // ficha: quem some não fica com presença de graça.
        $this->actingAs($this->organizer)
            ->patch(route('game-players.no-show', [$game, $gamePlayer]))
            ->assertRedirect();

        $player->refresh();

        $this->assertSame(0, $player->games_played);
        $this->assertSame(1, $player->no_shows);
    }

    // ==================== APOIO ====================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function game(array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $this->organizer->id,
            'team_name' => 'Furacão FC',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => today()->subDay()->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'max_players' => 10,
            'price' => '25.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
        ], $attributes));
    }

    /** Uma partida de ontem: fim previsto e folga já bem para trás. */
    private function finishableGame(array $attributes = []): Game
    {
        return $this->game($attributes);
    }

    private function player(string $name): PlayerProfile
    {
        $user = User::factory()->create(['name' => $name, 'state' => 'PI']);

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

    private function join(Game $game, User $user): GamePlayer
    {
        return GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'amount_due' => $game->price,
            'joined_at' => now()->subDays(2),
        ]);
    }
}
