<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\PlayerProfile;
use App\Models\SosRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * O prazo do SOS dito como quem corre contra ele o lê.
 *
 * O SOS é a única coisa no app que é uma corrida: muitos goleiros disputam
 * uma vaga, e a chamada vence. Mesmo assim o prazo aparecia só do lado do
 * organizador, escrito "Até 12/09 19:00" — a subtração que decide se o
 * goleiro responde agora ou depois do jantar ficava por conta dele, e do
 * lado dele o prazo não aparecia de forma alguma.
 */
class SosDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-09 08:00:00'));

        $this->organizer = User::factory()->organizer()->create(['city' => 'Teresina', 'state' => 'PI']);

        $this->game = Game::create([
            'user_id' => $this->organizer->id,
            'team_name' => 'Pelada da quinta',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => '2026-09-12',
            'start_time' => '19:00',
            'max_players' => 10,
            'price' => '20.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_under_an_hour_counts_in_minutes(): void
    {
        $sos = $this->sos(Carbon::now()->addMinutes(40));

        $this->assertSame('Faltam 40 minutos', $sos->deadlineLabel());
        $this->assertSame('urgent', $sos->deadlineUrgency());
    }

    public function test_under_a_day_counts_in_hours(): void
    {
        $sos = $this->sos(Carbon::now()->addHours(5));

        $this->assertSame('Faltam 5 horas', $sos->deadlineLabel());
        $this->assertSame('soon', $sos->deadlineUrgency());
    }

    /**
     * Passado um dia a contagem regressiva para de ajudar: ninguém
     * converte "faltam 51 horas" em plano, e a data já entregava isso
     * pronto.
     */
    public function test_beyond_a_day_goes_back_to_saying_the_day(): void
    {
        $sos = $this->sos(Carbon::parse('2026-09-12 19:00:00'));

        $this->assertSame('Até sáb, 12/09 · 19:00', $sos->deadlineLabel());
        $this->assertSame('calm', $sos->deadlineUrgency());
    }

    public function test_a_past_deadline_says_so(): void
    {
        $sos = $this->sos(Carbon::now()->subHour());

        $this->assertSame('Prazo encerrado', $sos->deadlineLabel());
        $this->assertSame('past', $sos->deadlineUrgency());
    }

    /**
     * Sem prazo não há contagem, e escrever "sem prazo" na tela seria
     * ruído: a chamada vale enquanto a partida valer.
     */
    public function test_a_call_without_a_deadline_says_nothing(): void
    {
        $sos = $this->sos(null);

        $this->assertNull($sos->deadlineLabel());
        $this->assertNull($sos->deadlineUrgency());
    }

    /**
     * O lado que mais precisa do prazo é o que não o tinha.
     */
    public function test_the_goalkeeper_sees_the_deadline_on_the_opportunity(): void
    {
        $sos = $this->sos(Carbon::now()->addHours(5));
        $goalkeeper = $this->goalkeeper();

        $this->actingAs($goalkeeper)
            ->get(route('sos-opportunities.show', $sos))
            ->assertOk()
            ->assertSee('Faltam 5 horas');
    }

    public function test_the_opportunity_list_names_the_match_and_shows_the_deadline(): void
    {
        $this->sos(Carbon::now()->addHours(5));
        $goalkeeper = $this->goalkeeper();

        $this->actingAs($goalkeeper)
            ->get(route('sos-opportunities.index'))
            ->assertOk()
            ->assertSee('Pelada da quinta')
            ->assertSee('Sáb, 12/09 · 19:00')
            ->assertSee('Faltam 5 horas');
    }

    public function test_the_organizer_list_names_the_match_and_shows_the_deadline(): void
    {
        $this->sos(Carbon::now()->addHours(5));

        $this->actingAs($this->organizer)
            ->get(route('sos.index'))
            ->assertOk()
            ->assertSee('Pelada da quinta')
            ->assertSee('Sáb, 12/09 · 19:00')
            ->assertSee('Faltam 5 horas');
    }

    // ==================== APOIO ====================

    private function sos(?Carbon $expiresAt): SosRequest
    {
        return SosRequest::create([
            'game_id' => $this->game->id,
            'organizer_id' => $this->organizer->id,
            'position' => SosRequest::POSITION,
            'offered_value' => '60.00',
            'status' => SosRequest::STATUS_OPEN,
            'expires_at' => $expiresAt,
        ]);
    }

    private function goalkeeper(): User
    {
        $user = User::factory()->create(['name' => 'Rafael Goleiro', 'state' => 'PI']);

        PlayerProfile::create([
            'user_id' => $user->id,
            'birth_date' => '1995-05-10',
            'state' => 'PI',
            'city' => 'Teresina',
            'phone' => '86999999999',
            'positions' => ['Goleiro'],
            'modalities' => ['Society'],
            'level' => 'Avançado',
            'price_per_game' => '50.00',
            'plays_outside_city' => false,
        ]);

        return $user;
    }
}
