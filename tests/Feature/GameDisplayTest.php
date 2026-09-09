<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Como a partida se apresenta: o nome dela e o dia no vocabulário de quem
 * marca pelada.
 *
 * O cartão de "Minhas Partidas" tinha `12/09/2026` por título e não dizia
 * o nome da partida em lugar nenhum — três peladas confirmadas viravam
 * três cartões idênticos identificados por uma data que ninguém usa para
 * se lembrar de nada. Estas asserções existem para que a data continue
 * dizendo o dia da semana, e para fixar os três casos em que ela não diz:
 * ontem, hoje e amanhã, quando o que importa é justamente que é hoje.
 */
class GameDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->organizer()->create();
    }

    public function test_yesterday_today_and_tomorrow_are_said_by_name(): void
    {
        // Uma quarta-feira qualquer, para que "hoje" e "amanhã" não caiam
        // por acidente num fim de semana e o teste passe pelo motivo errado.
        Carbon::setTestNow(Carbon::parse('2026-09-09 08:00:00'));

        $this->assertSame('Hoje', $this->game(['date' => '2026-09-09'])->dayLabel());
        $this->assertSame('Amanhã', $this->game(['date' => '2026-09-10'])->dayLabel());

        // "Ontem" existe pelo passado recente: o organizador chega na tela
        // de avaliar logo depois de encerrar a partida da véspera.
        $this->assertSame('Ontem', $this->game(['date' => '2026-09-08'])->dayLabel());
    }

    public function test_any_other_day_carries_its_weekday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-09 08:00:00'));

        $this->assertSame('Sáb, 12/09', $this->game(['date' => '2026-09-12'])->dayLabel());
    }

    /**
     * O ano só aparece quando não é o corrente, que é o único caso em que
     * ele diz alguma coisa — no histórico de partidas antigas ele diz.
     */
    public function test_the_year_shows_up_only_when_it_is_not_the_current_one(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-09 08:00:00'));

        $this->assertSame('Sex, 12/12/2025', $this->game(['date' => '2025-12-12'])->dayLabel());
    }

    public function test_the_when_label_joins_the_day_and_the_kickoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-09 08:00:00'));

        $this->assertSame(
            'Sáb, 12/09 · 19:00',
            $this->game(['date' => '2026-09-12', 'start_time' => '19:00'])->whenLabel()
        );
    }

    /**
     * `location` é texto livre; sem cidade e estado, "Arena Society
     * Central" cai em qualquer uma das cidades que têm uma.
     */
    public function test_the_map_link_carries_the_city_and_the_state(): void
    {
        $url = $this->game()->mapUrl();

        $this->assertStringStartsWith('https://www.google.com/maps/search/?api=1&query=', $url);
        $this->assertStringContainsString(rawurlencode('Arena Society Central, Teresina, PI'), $url);
    }

    /**
     * O cartão de "Minhas Partidas" agora se identifica pelo nome da
     * partida e leva para dentro dela.
     */
    public function test_the_my_games_card_shows_the_name_and_links_to_the_game(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-09 08:00:00'));

        $game = $this->game(['date' => '2026-09-12', 'team_name' => 'Pelada do Zé']);

        $this->actingAs($this->organizer)
            ->get(route('games.mine'))
            ->assertOk()
            ->assertSee('Pelada do Zé')
            ->assertSee('Sáb, 12/09 · 19:00')
            ->assertSee(route('games.show', $game));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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
            'date' => today()->addDays(3)->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'max_players' => 10,
            'price' => '25.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
        ], $attributes));
    }
}
