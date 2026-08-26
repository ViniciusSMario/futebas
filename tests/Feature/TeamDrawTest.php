<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTeam;
use App\Models\GuestPlayer;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Services\TeamDrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamDrawTest extends TestCase
{
    use RefreshDatabase;

    private function createGame(User $organizer, array $attributes = []): Game
    {
        $start = now()->addDay();

        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Furacão FC',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'modality' => 'Society',
            'date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHour()->format('H:i'),
            'max_players' => 20,
            'price' => '25.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
        ], $attributes));
    }

    /**
     * A confirmed participant whose strength is pinned by their declared
     * level, which is what the draw sorts on when there are no ratings yet.
     */
    private function joinWithLevel(Game $game, string $level, array $positions = ['Meia']): GamePlayer
    {
        $user = User::factory()->create();

        PlayerProfile::create([
            'user_id' => $user->id,
            'birth_date' => '1995-04-12',
            'state' => 'PI',
            'city' => 'Teresina',
            'phone' => '86999990000',
            'positions' => $positions,
            'modalities' => ['Society'],
            'level' => $level,
            'price_per_game' => '25.00',
        ]);

        return GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);
    }

    private function joinAsGuest(Game $game, User $organizer, string $name): GamePlayer
    {
        $guest = GuestPlayer::create([
            'organizer_id' => $organizer->id,
            'name' => $name,
            'phone' => '86999990000',
        ]);

        return GamePlayer::create([
            'game_id' => $game->id,
            'guest_player_id' => $guest->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);
    }

    /** The strength each team ended up with, keyed by team name. */
    private function teamScores(Game $game): array
    {
        return GameTeam::where('game_id', $game->id)
            ->with('gamePlayers.user.playerProfile')
            ->get()
            ->mapWithKeys(fn (GameTeam $team) => [
                $team->name => $team->gamePlayers->sum(
                    fn (GamePlayer $player) => $player->user?->playerProfile?->overallScore() ?? PlayerProfile::DEFAULT_SCORE
                ),
            ])
            ->all();
    }

    public function test_a_balanced_draw_splits_strength_evenly_where_a_shuffle_would_not(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        // Two of each level: the only even split puts one of each on each
        // side. A shuffle stacks them together often enough to matter.
        foreach (['Avançado', 'Avançado', 'Intermediário', 'Intermediário', 'Iniciante', 'Iniciante'] as $level) {
            $this->joinWithLevel($game, $level);
        }

        $this->actingAs($organizer)
            ->post(route('game-teams.draw', $game), ['teams_count' => 2, 'mode' => TeamDrawService::MODE_BALANCED])
            ->assertRedirect();

        $scores = array_values($this->teamScores($game));

        $this->assertCount(2, $scores);
        $this->assertSame(0, max($scores) - min($scores), 'os times deveriam sair com a mesma força total');
    }

    public function test_a_balanced_draw_spreads_the_goalkeepers(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $this->joinWithLevel($game, 'Avançado', ['Goleiro']);
        $this->joinWithLevel($game, 'Iniciante', ['Goleiro']);

        foreach (range(1, 8) as $i) {
            $this->joinWithLevel($game, 'Recreativo');
        }

        $this->actingAs($organizer)
            ->post(route('game-teams.draw', $game), ['teams_count' => 2, 'mode' => TeamDrawService::MODE_BALANCED]);

        $goalkeepersPerTeam = GameTeam::where('game_id', $game->id)
            ->with('gamePlayers.user.playerProfile')
            ->get()
            ->map(fn (GameTeam $team) => $team->gamePlayers
                ->filter(fn (GamePlayer $player) => (bool) $player->user?->playerProfile?->isGoalkeeper())
                ->count()
            );

        $this->assertEquals([1, 1], $goalkeepersPerTeam->values()->all());
    }

    public function test_a_balanced_draw_keeps_the_teams_the_same_size(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        // Three goalkeepers across two teams: the goalkeeper pass leaves the
        // sides uneven, and the outfield pass has to repair it.
        foreach (range(1, 3) as $i) {
            $this->joinWithLevel($game, 'Recreativo', ['Goleiro']);
        }

        foreach (range(1, 7) as $i) {
            $this->joinWithLevel($game, 'Recreativo');
        }

        $this->actingAs($organizer)
            ->post(route('game-teams.draw', $game), ['teams_count' => 2, 'mode' => TeamDrawService::MODE_BALANCED]);

        $sizes = GameTeam::where('game_id', $game->id)
            ->withCount('gamePlayers')
            ->pluck('game_players_count')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([5, 5], $sizes);
    }

    public function test_every_confirmed_participant_lands_on_exactly_one_team(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        foreach (['Avançado', 'Iniciante', 'Recreativo', 'Intermediário', 'Recreativo'] as $level) {
            $this->joinWithLevel($game, $level);
        }

        // Guests have no account and no profile — they still have to be drawn.
        $this->joinAsGuest($game, $organizer, 'Zé do Bar');

        $this->actingAs($organizer)
            ->post(route('game-teams.draw', $game), ['teams_count' => 3, 'mode' => TeamDrawService::MODE_BALANCED]);

        $this->assertSame(6, GamePlayer::where('game_id', $game->id)->whereNotNull('game_team_id')->count());
        $this->assertSame(3, GameTeam::where('game_id', $game->id)->count());
    }

    public function test_players_who_are_not_confirmed_are_left_out(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        foreach (['Recreativo', 'Recreativo'] as $level) {
            $this->joinWithLevel($game, $level);
        }

        $waiting = $this->joinWithLevel($game, 'Avançado');
        $waiting->update(['status' => GamePlayer::STATUS_WAITING_LIST]);

        $this->actingAs($organizer)
            ->post(route('game-teams.draw', $game), ['teams_count' => 2]);

        $this->assertNull($waiting->fresh()->game_team_id);
        $this->assertSame(2, GamePlayer::where('game_id', $game->id)->whereNotNull('game_team_id')->count());
    }

    public function test_the_draw_defaults_to_balanced_when_no_mode_is_sent(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        foreach (['Avançado', 'Avançado', 'Iniciante', 'Iniciante'] as $level) {
            $this->joinWithLevel($game, $level);
        }

        $this->actingAs($organizer)->post(route('game-teams.draw', $game), ['teams_count' => 2]);

        $scores = array_values($this->teamScores($game));

        $this->assertSame(0, max($scores) - min($scores));
    }

    public function test_random_mode_still_fills_the_teams(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        foreach (range(1, 6) as $i) {
            $this->joinWithLevel($game, 'Recreativo');
        }

        $this->actingAs($organizer)
            ->post(route('game-teams.draw', $game), ['teams_count' => 2, 'mode' => TeamDrawService::MODE_RANDOM])
            ->assertRedirect();

        $sizes = GameTeam::where('game_id', $game->id)
            ->withCount('gamePlayers')
            ->pluck('game_players_count')
            ->all();

        $this->assertSame([3, 3], $sizes);
    }

    /**
     * Guards the meaning of the two modes: if "aleatório" ever quietly
     * started balancing too, the choice offered to the organizer would be a
     * lie. Measured over repeated draws of a deliberately lopsided roster,
     * where a shuffle strands a team without a keeper roughly 40% of the
     * time — the failure this whole feature exists to prevent.
     */
    public function test_random_mode_really_is_random(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $this->joinWithLevel($game, 'Avançado', ['Goleiro']);
        $this->joinWithLevel($game, 'Intermediário', ['Goleiro']);

        foreach (['Avançado', 'Avançado', 'Intermediário', 'Recreativo', 'Recreativo', 'Iniciante', 'Iniciante', 'Iniciante'] as $level) {
            $this->joinWithLevel($game, $level);
        }

        $lopsided = collect(range(1, 20))->filter(function () use ($organizer, $game) {
            $this->actingAs($organizer)
                ->post(route('game-teams.draw', $game), ['teams_count' => 2, 'mode' => TeamDrawService::MODE_RANDOM]);

            $goalkeepers = GameTeam::where('game_id', $game->id)
                ->with('gamePlayers.user.playerProfile')
                ->get()
                ->map(fn (GameTeam $team) => $team->gamePlayers
                    ->filter(fn (GamePlayer $player) => (bool) $player->user?->playerProfile?->isGoalkeeper())
                    ->count()
                );

            return $goalkeepers->max() - $goalkeepers->min() > 0;
        });

        $this->assertGreaterThan(
            0,
            $lopsided->count(),
            'o modo aleatório nunca desequilibrou os goleiros em 20 sorteios — ele está equilibrando'
        );
    }

    public function test_an_unknown_mode_is_rejected(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);
        $this->joinWithLevel($game, 'Recreativo');
        $this->joinWithLevel($game, 'Recreativo');

        $this->actingAs($organizer)
            ->post(route('game-teams.draw', $game), ['teams_count' => 2, 'mode' => 'enfraquecer-o-rival'])
            ->assertSessionHasErrors('mode');

        $this->assertSame(0, GameTeam::where('game_id', $game->id)->count());
    }

    /**
     * The organizer reaching the draw on a match that is already over is an
     * ordinary mistake — a stale tab, the back button — not an intrusion.
     * It used to answer with a bare 403 page.
     */
    public function test_drawing_on_a_finished_match_explains_itself_instead_of_forbidding(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['status' => Game::STATUS_FINISHED]);

        $this->joinWithLevel($game, 'Recreativo');
        $this->joinWithLevel($game, 'Recreativo');

        $response = $this->actingAs($organizer)
            ->post(route('game-teams.draw', $game), ['teams_count' => 2]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('teams_count');
        $this->assertSame(0, GameTeam::where('game_id', $game->id)->count());
    }

    public function test_drawing_on_a_cancelled_match_is_refused_the_same_way(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['status' => Game::STATUS_CANCELLED]);

        $this->joinWithLevel($game, 'Recreativo');
        $this->joinWithLevel($game, 'Recreativo');

        $this->actingAs($organizer)
            ->post(route('game-teams.draw', $game), ['teams_count' => 2])
            ->assertSessionHasErrors('teams_count');

        $this->assertSame(0, GameTeam::where('game_id', $game->id)->count());
    }

    public function test_a_closed_match_is_not_offered_the_draw_form_at_all(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer, ['status' => Game::STATUS_FINISHED]);
        $this->joinWithLevel($game, 'Recreativo');

        $response = $this->actingAs($organizer)
            ->get(route('games.show', ['game' => $game, 'tab' => 'times']));

        $response->assertOk();
        $response->assertSee('Partida finalizada');
        // The form's action is the one place this URL appears in the page.
        $response->assertDontSee(route('game-teams.draw', $game), false);
    }

    public function test_an_open_match_still_gets_the_draw_form(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);
        $this->joinWithLevel($game, 'Recreativo');

        $response = $this->actingAs($organizer)
            ->get(route('games.show', ['game' => $game, 'tab' => 'times']));

        $response->assertOk();
        $response->assertSee(route('game-teams.draw', $game), false);
        $response->assertDontSee('Partida finalizada');
    }

    public function test_asking_for_more_teams_than_players_is_refused(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        $this->joinWithLevel($game, 'Recreativo');
        $this->joinWithLevel($game, 'Recreativo');

        $this->actingAs($organizer)
            ->post(route('game-teams.draw', $game), ['teams_count' => 4])
            ->assertSessionHasErrors('teams_count');

        $this->assertSame(0, GameTeam::where('game_id', $game->id)->count());
    }

    public function test_redrawing_produces_a_different_lineup_among_equally_strong_players(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        // All the same strength, so nothing but the random tiebreak decides
        // who goes where. Without it a redraw would be a no-op.
        foreach (range(1, 10) as $i) {
            $this->joinWithLevel($game, 'Recreativo');
        }

        $lineups = collect(range(1, 12))->map(function () use ($organizer, $game) {
            $this->actingAs($organizer)->post(route('game-teams.draw', $game), ['teams_count' => 2]);

            return GameTeam::where('game_id', $game->id)
                ->with('gamePlayers')
                ->orderBy('id')
                ->get()
                ->map(fn (GameTeam $team) => $team->gamePlayers->pluck('id')->sort()->implode(','))
                ->implode('|');
        });

        $this->assertGreaterThan(1, $lineups->unique()->count(), 'um novo sorteio deveria mudar os times');
    }

    public function test_a_guest_without_a_profile_does_not_read_as_a_weak_player(): void
    {
        $organizer = User::factory()->organizer()->create();
        $game = $this->createGame($organizer);

        // Everyone rated the same. Whichever side gets the guest must still
        // come out at that same strength — scoring an unknown player as zero
        // would show the organizer a hole that isn't there.
        foreach (range(1, 5) as $i) {
            $this->joinWithLevel($game, 'Intermediário');
        }

        $guest = $this->joinAsGuest($game, $organizer, 'Zé do Bar');

        $this->actingAs($organizer)->post(route('game-teams.draw', $game), ['teams_count' => 2]);

        $this->assertNotNull($guest->fresh()->game_team_id, 'o convidado tem de entrar em algum time');

        $teams = GameTeam::where('game_id', $game->id)
            ->with('gamePlayers.user.playerProfile')
            ->get();

        // Asserted through the summary the "Times" tab prints, which is the
        // number the organizer actually reads.
        $averages = array_column(app(TeamDrawService::class)->summarise($teams), 'average');

        $expected = PlayerProfile::LEVEL_SCORES['Intermediário'];
        $this->assertSame([$expected, $expected], $averages);
    }
}
