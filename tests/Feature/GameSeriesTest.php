<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameSeries;
use App\Models\GameSeriesMember;
use App\Models\GuestPlayer;
use App\Models\User;
use App\Notifications\AddedToGameSeries;
use App\Services\GameSeriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Weekly peladas: generating occurrences and seating the regulars.
 */
class GameSeriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Occurrences are placed relative to "today"; pinning the clock to
        // a Monday midday keeps every weekday assertion deterministic.
        $this->travelTo(today()->next('Monday')->setTime(12, 0));
    }

    private function organizer(): User
    {
        return User::factory()->organizer()->create();
    }

    /** @param  array<string, mixed>  $attributes */
    private function createSeries(User $organizer, array $attributes = []): GameSeries
    {
        return GameSeries::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Pelada de Quinta',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'day_of_week' => 4, // Thursday
            'start_time' => '19:00',
            'end_time' => '20:00',
            'max_players' => 14,
            'price' => '25.00',
            'positions' => ['Goleiro'],
            'requires_approval' => false,
            'status' => GameSeries::STATUS_ACTIVE,
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'team_name' => 'Pelada de Quinta',
            'modality' => 'Society',
            'day_of_week' => 4,
            'start_time' => '19:00',
            'end_time' => '20:00',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'max_players' => 14,
            'price' => '25.00',
        ], $overrides);
    }

    public function test_players_cannot_reach_the_weekly_pelada_area(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('game-series.index'))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('game-series.index'))->assertRedirect('/login');
    }

    public function test_creating_a_series_stamps_out_the_next_weeks_of_occurrences(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)
            ->post(route('game-series.store'), $this->storePayload(['organizer_is_playing' => false]))
            ->assertRedirect();

        $series = GameSeries::firstOrFail();
        $games = $series->games()->orderBy('date')->get();

        $this->assertCount(GameSeries::WEEKS_AHEAD, $games);
        $games->each(function (Game $game) use ($organizer) {
            $this->assertSame(4, $game->date->dayOfWeek);
            $this->assertSame($organizer->id, $game->user_id);
            $this->assertSame(Game::STATUS_OPEN, $game->status);
            $this->assertSame('19:00', $game->start_time->format('H:i'));
        });
    }

    public function test_todays_occurrence_is_generated_while_kickoff_is_still_ahead(): void
    {
        // Monday midday, pelada is Monday 19:00 — tonight still counts.
        $series = $this->createSeries($this->organizer(), ['day_of_week' => 1, 'start_time' => '19:00']);

        app(GameSeriesService::class)->syncUpcoming($series);

        $this->assertTrue($series->games()->get()->contains(
            fn (Game $game) => $game->date->isSameDay(today())
        ));
    }

    public function test_todays_occurrence_is_skipped_once_kickoff_has_passed(): void
    {
        // Monday midday, pelada is Monday 08:00 — that one already kicked off.
        $series = $this->createSeries($this->organizer(), ['day_of_week' => 1, 'start_time' => '08:00']);

        app(GameSeriesService::class)->syncUpcoming($series);

        $this->assertFalse($series->games()->get()->contains(
            fn (Game $game) => $game->date->isSameDay(today())
        ));
        $this->assertSame(GameSeries::WEEKS_AHEAD, $series->games()->count());
    }

    public function test_generation_is_idempotent(): void
    {
        $organizer = $this->organizer();
        $series = $this->createSeries($organizer);
        $service = app(GameSeriesService::class);

        $service->syncUpcoming($series);
        $countAfterFirst = $series->games()->count();

        $service->syncUpcoming($series);
        $service->syncUpcoming($series);

        $this->assertSame($countAfterFirst, $series->games()->count());
    }

    public function test_regulars_are_seated_confirmed_in_every_generated_occurrence(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $regular = User::factory()->create();
        $series = $this->createSeries($organizer);
        $service = app(GameSeriesService::class);

        $service->addMember($series, $regular);
        $service->syncUpcoming($series);

        $games = $series->games()->get();
        $this->assertCount(GameSeries::WEEKS_AHEAD, $games);

        foreach ($games as $game) {
            $gamePlayer = GamePlayer::where('game_id', $game->id)->where('user_id', $regular->id)->firstOrFail();
            $this->assertSame(GamePlayer::STATUS_CONFIRMED, $gamePlayer->status);
        }
    }

    public function test_a_regular_is_seated_confirmed_even_when_the_series_requires_approval(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $regular = User::factory()->create();
        $series = $this->createSeries($organizer, ['requires_approval' => true]);
        $service = app(GameSeriesService::class);

        $service->addMember($series, $regular);
        $service->syncUpcoming($series);

        $game = $series->games()->firstOrFail();
        $gamePlayer = GamePlayer::where('game_id', $game->id)->where('user_id', $regular->id)->firstOrFail();

        $this->assertSame(GamePlayer::STATUS_CONFIRMED, $gamePlayer->status);
    }

    public function test_adding_a_regular_backfills_the_occurrences_already_generated(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $series = $this->createSeries($organizer);
        $service = app(GameSeriesService::class);

        $service->syncUpcoming($series);

        $latecomer = User::factory()->create();
        $service->addMember($series, $latecomer);

        foreach ($series->games()->get() as $game) {
            $this->assertDatabaseHas('game_players', [
                'game_id' => $game->id,
                'user_id' => $latecomer->id,
                'status' => GamePlayer::STATUS_CONFIRMED,
            ]);
        }

        Notification::assertSentTo($latecomer, AddedToGameSeries::class);
    }

    public function test_a_guest_can_be_a_regular_and_gets_no_notification(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $series = $this->createSeries($organizer);
        $guest = GuestPlayer::create(['organizer_id' => $organizer->id, 'name' => 'Zé do Bairro']);

        app(GameSeriesService::class)->addMember($series, $guest);
        app(GameSeriesService::class)->syncUpcoming($series);

        $game = $series->games()->firstOrFail();

        $this->assertDatabaseHas('game_players', [
            'game_id' => $game->id,
            'guest_player_id' => $guest->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
        ]);

        Notification::assertNothingSent();
    }

    public function test_the_organizer_can_play_their_own_pelada_without_being_notified(): void
    {
        Notification::fake();

        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('game-series.store'), $this->storePayload(['organizer_is_playing' => true]));

        $series = GameSeries::firstOrFail();
        $game = $series->games()->firstOrFail();

        $this->assertDatabaseHas('game_players', [
            'game_id' => $game->id,
            'user_id' => $organizer->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
        ]);

        Notification::assertNothingSent();
    }

    public function test_more_regulars_than_spots_puts_the_extras_on_the_waiting_list(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $series = $this->createSeries($organizer, ['max_players' => 2]);
        $service = app(GameSeriesService::class);

        $first = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();

        $service->addMember($series, $first);
        $service->addMember($series, $second);
        $service->addMember($series, $third);
        $service->syncUpcoming($series);

        $game = $series->games()->orderBy('date')->firstOrFail();

        $this->assertSame(2, GamePlayer::where('game_id', $game->id)->where('status', GamePlayer::STATUS_CONFIRMED)->count());
        $this->assertSame(1, GamePlayer::where('game_id', $game->id)->where('status', GamePlayer::STATUS_WAITING_LIST)->count());
    }

    public function test_removing_a_regular_stops_future_seating_but_leaves_existing_matches_alone(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $regular = User::factory()->create();
        $series = $this->createSeries($organizer);
        $service = app(GameSeriesService::class);

        $service->addMember($series, $regular);
        $service->syncUpcoming($series);

        $existingGame = $series->games()->firstOrFail();
        $member = GameSeriesMember::where('game_series_id', $series->id)->firstOrFail();

        $this->actingAs($organizer)
            ->delete(route('game-series.members.destroy', [$series, $member]))
            ->assertRedirect();

        $this->assertDatabaseMissing('game_series_members', ['id' => $member->id]);

        // Still confirmed for the match they were already counted on for.
        $this->assertDatabaseHas('game_players', [
            'game_id' => $existingGame->id,
            'user_id' => $regular->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
        ]);
    }

    public function test_ending_a_series_stops_generation_and_keeps_the_matches(): void
    {
        $organizer = $this->organizer();
        $series = $this->createSeries($organizer);
        $service = app(GameSeriesService::class);

        $service->syncUpcoming($series);
        $countBefore = $series->games()->count();

        $this->actingAs($organizer)->patch(route('game-series.end', $series))->assertRedirect();

        $series->games()->delete();
        $service->syncUpcoming($series->fresh());

        $this->assertSame(GameSeries::STATUS_ENDED, $series->fresh()->status);
        $this->assertSame(0, $series->games()->count());
        $this->assertGreaterThan(0, $countBefore);
    }

    public function test_another_organizer_cannot_see_or_touch_the_series(): void
    {
        $organizer = $this->organizer();
        $series = $this->createSeries($organizer);
        $intruder = $this->organizer();

        $this->actingAs($intruder)->get(route('game-series.show', $series))->assertForbidden();
        $this->actingAs($intruder)->patch(route('game-series.end', $series))->assertForbidden();
        $this->actingAs($intruder)
            ->post(route('game-series.members.store', $series), ['user_id' => User::factory()->create()->id])
            ->assertForbidden();
    }

    public function test_the_series_page_lists_its_occurrences_and_regulars(): void
    {
        Notification::fake();

        $organizer = $this->organizer();
        $series = $this->createSeries($organizer);
        $regular = User::factory()->create(['name' => 'Carlos Mensalista']);

        app(GameSeriesService::class)->addMember($series, $regular);

        $response = $this->actingAs($organizer)->get(route('game-series.show', $series));

        $response->assertOk();
        $response->assertSee('Carlos Mensalista');
        $response->assertSee('Próximas partidas');
    }

    public function test_the_generate_command_tops_up_active_series_only(): void
    {
        $organizer = $this->organizer();
        $active = $this->createSeries($organizer);
        $ended = $this->createSeries($organizer, ['day_of_week' => 2, 'status' => GameSeries::STATUS_ENDED]);

        $this->artisan('series:generate')->assertSuccessful();

        $this->assertSame(GameSeries::WEEKS_AHEAD, $active->games()->count());
        $this->assertSame(0, $ended->games()->count());
    }

    public function test_series_occurrences_behave_like_ordinary_games(): void
    {
        $organizer = $this->organizer();
        $series = $this->createSeries($organizer);

        app(GameSeriesService::class)->syncUpcoming($series);

        // They show up in the organizer's games list...
        $this->actingAs($organizer)->get('/games/mine')->assertSee('Arena Society Central');

        // ...and in the player-facing search, like any other open match.
        $this->actingAs(User::factory()->create())
            ->get('/games/search')
            ->assertSee('Arena Society Central');
    }

    public function test_a_standalone_game_is_unaffected_and_has_no_series(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('games.store'), [
            'team_name' => 'Jogo Avulso',
            'modality' => 'Society',
            'date' => today()->addDays(2)->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'location' => 'Quadra Avulsa',
            'city' => 'Teresina',
            'state' => 'PI',
            'max_players' => 10,
            'price' => '20.00',
        ])->assertRedirect();

        $game = Game::where('team_name', 'Jogo Avulso')->firstOrFail();

        $this->assertNull($game->game_series_id);
        $this->assertNull($game->gameSeries);
    }
}
