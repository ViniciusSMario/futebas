<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameSeries;
use App\Models\PlayerProfile;
use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo data has one job: every screen shown in a presentation must have
 * something real in it. This walks that script so a broken seeder is caught
 * here rather than in front of an audience.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
    }

    private function organizer(): User
    {
        return User::where('email', 'organizador@futebas.test')->firstOrFail();
    }

    private function player(): User
    {
        return User::where('email', 'jogador@futebas.test')->firstOrFail();
    }

    private function goalkeeper(): User
    {
        return User::where('email', 'goleiro@futebas.test')->firstOrFail();
    }

    public function test_the_demo_accounts_exist_and_can_sign_in(): void
    {
        foreach (['organizador@futebas.test', 'jogador@futebas.test', 'goleiro@futebas.test'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'password'])
                ->assertRedirect(route('dashboard'));

            $this->post('/logout');
        }

        $this->assertTrue($this->organizer()->hasRole(User::ROLE_ORGANIZER));
        $this->assertTrue($this->player()->hasRole(User::ROLE_PLAYER));
        $this->assertTrue($this->goalkeeper()->isGoalkeeper());
    }

    public function test_both_dashboards_render(): void
    {
        $this->actingAs($this->organizer())->get('/dashboard')->assertOk();
        $this->actingAs($this->player())->get('/dashboard')->assertOk();
    }

    public function test_the_player_search_has_people_to_rank(): void
    {
        $rated = PlayerProfile::where('ratings_count', '>', 0)->count();
        $withAttendance = PlayerProfile::whereNotNull('attendance_rate')->count();

        $this->assertGreaterThan(5, $rated, 'Sem avaliações não há como demonstrar a ordenação por reputação.');
        $this->assertGreaterThan(5, $withAttendance);

        // The two orderings must actually separate people, not tie.
        $this->assertGreaterThan(1, PlayerProfile::distinct()->count('average_rating'));
        $this->assertGreaterThan(1, PlayerProfile::distinct()->count('attendance_rate'));

        $this->actingAs($this->organizer())->get('/players/search?sort=attendance')->assertOk();
        $this->actingAs($this->organizer())->get('/players/search?sort=rating')->assertOk();
    }

    public function test_the_game_search_finds_matches_the_player_can_join(): void
    {
        $response = $this->actingAs($this->player())->get('/games/search');

        $response->assertOk();
        $response->assertDontSee('Nenhuma partida encontrada');
    }

    public function test_a_full_match_is_available_to_demo_the_waiting_list(): void
    {
        $full = Game::query()
            ->where('status', Game::STATUS_OPEN)
            ->upcoming()
            ->get()
            ->first(fn (Game $game) => $game->isFull());

        $this->assertNotNull($full, 'Nenhuma partida lotada para demonstrar a lista de espera.');
        $this->assertGreaterThan(0, GamePlayer::where('game_id', $full->id)
            ->where('status', GamePlayer::STATUS_WAITING_LIST)
            ->count());
    }

    public function test_todays_match_has_the_check_in_window_open_for_the_demo_player(): void
    {
        $gamePlayer = GamePlayer::query()
            ->where('user_id', $this->player()->id)
            ->whereNull('checked_in_at')
            ->with('game')
            ->get()
            ->first(fn (GamePlayer $entry) => $entry->canCheckIn());

        $this->assertNotNull($gamePlayer, 'Nenhuma partida com check-in aberto para o jogador da demo.');

        $this->actingAs($this->player())->get('/dashboard')->assertSee('Você joga hoje!');
    }

    public function test_a_finished_match_is_ready_for_the_rating_flow(): void
    {
        $finished = Game::where('user_id', $this->organizer()->id)
            ->where('status', Game::STATUS_FINISHED)
            ->latest('date')
            ->firstOrFail();

        $response = $this->actingAs($this->organizer())->get(route('ratings.index', $finished));

        $response->assertOk();
        $response->assertDontSee('Nenhum jogador confirmado nesta partida.');
        // Both states on screen: some already rated, some still to rate.
        $response->assertSee('Avaliado');
        $response->assertSee('Avaliar');
    }

    public function test_the_player_has_ratings_and_history_to_show(): void
    {
        $response = $this->actingAs($this->player())->get(route('ratings.show', $this->player()));

        $response->assertOk();
        $response->assertSee('Histórico');
        $response->assertSee('Evolução das notas');
    }

    public function test_the_weekly_series_generated_its_occurrences_with_regulars_seated(): void
    {
        $series = GameSeries::firstOrFail();

        $this->assertSame(GameSeries::WEEKS_AHEAD, $series->games()->count());
        $this->assertGreaterThan(5, $series->members()->count());

        $occurrence = $series->games()->orderBy('date')->firstOrFail();
        $this->assertGreaterThan(5, $occurrence->confirmedPlayersCount());

        $this->actingAs($this->organizer())->get(route('game-series.show', $series))->assertOk();
    }

    public function test_the_open_sos_has_goalkeepers_competing_at_different_prices(): void
    {
        $sosRequest = SosRequest::where('status', SosRequest::STATUS_OPEN)->firstOrFail();

        $applications = SosApplication::where('sos_request_id', $sosRequest->id)
            ->where('status', SosApplication::STATUS_PENDING)
            ->get();

        $this->assertCount(3, $applications);
        $this->assertCount(3, $applications->pluck('asking_price')->unique());

        $this->actingAs($this->organizer())->get(route('sos.show', $sosRequest))->assertOk();
    }

    public function test_the_demo_player_has_an_invitation_and_a_notification_waiting(): void
    {
        $this->assertGreaterThan(0, $this->player()->invitations()->where('status', 'pending')->count());
        $this->assertGreaterThan(0, $this->player()->notifications()->count());

        $this->actingAs($this->player())->get('/notificacoes')->assertDontSee('Nada por aqui ainda');
    }

    public function test_every_seeded_match_has_a_shareable_public_link(): void
    {
        $this->assertSame(0, Game::whereNull('slug')->count());

        $game = Game::where('status', Game::STATUS_OPEN)->upcoming()->firstOrFail();

        $this->get(route('public-games.show', $game))->assertOk();
    }
}
