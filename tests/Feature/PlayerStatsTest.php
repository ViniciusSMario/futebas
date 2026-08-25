<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\PlayerProfile;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The attendance record kept on a player's profile, and the places an
 * organizer reads it: the player search, their profile, and SOS.
 */
class PlayerStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Matches are placed hours either side of "now"; midday keeps that
        // arithmetic inside the same date.
        $this->travelTo(today()->setTime(12, 0));

        Notification::fake();
    }

    private function organizer(): User
    {
        return User::factory()->organizer()->create();
    }

    private function createPlayer(array $attributes = []): User
    {
        $user = User::factory()->create(['name' => $attributes['name'] ?? 'Jogador Teste']);

        PlayerProfile::create(array_merge([
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
        ], array_diff_key($attributes, ['name' => null])));

        return $user->refresh();
    }

    /** A match that already ended, ready for the organizer to finish. */
    private function pastGame(User $organizer, array $attributes = []): Game
    {
        return Game::create(array_merge([
            'user_id' => $organizer->id,
            'team_name' => 'Furacão FC',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'modality' => 'Society',
            'date' => today()->format('Y-m-d'),
            'start_time' => now()->subHours(3)->format('H:i'),
            'end_time' => now()->subHour()->format('H:i'),
            'max_players' => 10,
            'price' => '25.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
            'requires_approval' => false,
        ], $attributes));
    }

    private function join(Game $game, User $user, array $attributes = []): GamePlayer
    {
        return GamePlayer::create(array_merge([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ], $attributes));
    }

    private function finish(User $organizer, Game $game): void
    {
        $this->actingAs($organizer)->patch(route('games.finish', $game))->assertRedirect();
    }

    public function test_a_player_with_no_history_has_no_attendance_rate(): void
    {
        $player = $this->createPlayer();

        $this->assertNull($player->playerProfile->attendance_rate);
        $this->assertSame(0, $player->playerProfile->games_played);
        $this->assertFalse($player->playerProfile->hasAttendanceHistory());
    }

    public function test_finishing_a_match_counts_it_for_everyone_who_played(): void
    {
        $organizer = $this->organizer();
        $player = $this->createPlayer();
        $game = $this->pastGame($organizer);
        $this->join($game, $player);

        $this->finish($organizer, $game);

        $profile = $player->playerProfile->fresh();
        $this->assertSame(1, $profile->games_played);
        $this->assertSame(0, $profile->no_shows);
        $this->assertSame('100.00', $profile->attendance_rate);
    }

    public function test_an_unfinished_match_counts_for_nothing_yet(): void
    {
        $organizer = $this->organizer();
        $player = $this->createPlayer();
        $game = $this->pastGame($organizer);
        $this->join($game, $player);

        $player->playerProfile->recalculateAttendanceStats();

        $this->assertSame(0, $player->playerProfile->fresh()->games_played);
        $this->assertNull($player->playerProfile->fresh()->attendance_rate);
    }

    public function test_a_no_show_lowers_the_attendance_rate(): void
    {
        $organizer = $this->organizer();
        $player = $this->createPlayer();

        $played = $this->pastGame($organizer, ['location' => 'Quadra A']);
        $this->join($played, $player);
        $this->finish($organizer, $played);

        $missed = $this->pastGame($organizer, ['location' => 'Quadra B', 'date' => today()->subDay()->format('Y-m-d')]);
        $this->join($missed, $player, ['no_show' => true]);
        $this->finish($organizer, $missed);

        $profile = $player->playerProfile->fresh();
        $this->assertSame(1, $profile->games_played);
        $this->assertSame(1, $profile->no_shows);
        $this->assertSame('50.00', $profile->attendance_rate);
    }

    public function test_marking_an_absence_after_the_match_was_finished_updates_the_record(): void
    {
        $organizer = $this->organizer();
        $player = $this->createPlayer();
        $game = $this->pastGame($organizer);
        $gamePlayer = $this->join($game, $player);

        $this->finish($organizer, $game);
        $this->assertSame('100.00', $player->playerProfile->fresh()->attendance_rate);

        $this->actingAs($organizer)->patch(route('game-players.no-show', [$game, $gamePlayer]))->assertRedirect();

        $profile = $player->playerProfile->fresh();
        $this->assertSame(0, $profile->games_played);
        $this->assertSame(1, $profile->no_shows);
        $this->assertSame('0.00', $profile->attendance_rate);
    }

    public function test_cancelling_in_advance_is_recorded_but_is_not_an_absence(): void
    {
        $organizer = $this->organizer();
        $player = $this->createPlayer();

        // Far enough ahead that the player may still pull out.
        $game = $this->pastGame($organizer, ['date' => today()->addDays(3)->format('Y-m-d'), 'start_time' => '19:00', 'end_time' => '20:00']);
        $this->join($game, $player);

        $this->actingAs($player)->delete(route('games.leave', $game))->assertRedirect();

        // The match then happens and is finished.
        $game->update(['date' => today()->subDay()->format('Y-m-d')]);
        $this->finish($organizer, $game->fresh());

        $profile = $player->playerProfile->fresh();
        $this->assertSame(1, $profile->cancellations);
        $this->assertSame(0, $profile->no_shows);
        // No commitment kept and none broken, so there's no rate to show.
        $this->assertNull($profile->attendance_rate);
        $this->assertTrue($profile->hasAttendanceHistory());
    }

    public function test_finishing_a_match_with_guests_keeps_working(): void
    {
        $organizer = $this->organizer();
        $player = $this->createPlayer();
        $game = $this->pastGame($organizer);
        $guest = GuestPlayer::create(['organizer_id' => $organizer->id, 'name' => 'Convidado Zé']);

        $this->join($game, $player);
        GamePlayer::create([
            'game_id' => $game->id,
            'guest_player_id' => $guest->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
        ]);

        $this->finish($organizer, $game);

        $this->assertSame(1, $player->playerProfile->fresh()->games_played);
    }

    public function test_a_player_without_a_sports_profile_does_not_break_finishing(): void
    {
        $organizer = $this->organizer();
        $bare = User::factory()->create();
        $game = $this->pastGame($organizer);
        $this->join($game, $bare);

        $this->finish($organizer, $game);

        $this->assertSame(Game::STATUS_FINISHED, $game->fresh()->status);
    }

    public function test_the_search_can_be_ordered_by_reliability(): void
    {
        $organizer = $this->organizer();
        $reliable = $this->createPlayer(['name' => 'Ana Presente']);
        $flaky = $this->createPlayer(['name' => 'Bruno Furao']);

        $reliable->playerProfile->update(['attendance_rate' => '95.00', 'games_played' => 19]);
        $flaky->playerProfile->update(['attendance_rate' => '40.00', 'games_played' => 4]);

        $response = $this->actingAs($organizer)->get('/players/search?sort=attendance');

        $response->assertOk();
        $response->assertSeeInOrder(['Ana Presente', 'Bruno Furao']);
    }

    public function test_the_search_can_be_ordered_by_rating_and_puts_unrated_players_last(): void
    {
        $organizer = $this->organizer();
        $best = $this->createPlayer(['name' => 'Ana Craque']);
        $good = $this->createPlayer(['name' => 'Bruno Bom']);
        $unrated = $this->createPlayer(['name' => 'Carlos Novato']);

        $best->playerProfile->update(['average_rating' => '4.90', 'ratings_count' => 10]);
        $good->playerProfile->update(['average_rating' => '3.20', 'ratings_count' => 5]);

        $response = $this->actingAs($organizer)->get('/players/search?sort=rating');

        $response->assertOk();
        $response->assertSeeInOrder(['Ana Craque', 'Bruno Bom', 'Carlos Novato']);
        $this->assertNull($unrated->playerProfile->average_rating);
    }

    public function test_the_search_can_be_ordered_by_price(): void
    {
        $organizer = $this->organizer();
        $this->createPlayer(['name' => 'Ana Barata', 'price_per_game' => '20.00']);
        $this->createPlayer(['name' => 'Bruno Caro', 'price_per_game' => '90.00']);

        $this->actingAs($organizer)
            ->get('/players/search?sort=price')
            ->assertSeeInOrder(['Ana Barata', 'Bruno Caro']);
    }

    public function test_the_default_ordering_is_unchanged(): void
    {
        $organizer = $this->organizer();
        $older = $this->createPlayer(['name' => 'Ana Antiga']);
        $this->createPlayer(['name' => 'Bruno Recente']);

        // Both profiles are created within the same second otherwise, which
        // leaves latest() with nothing to order on.
        $older->playerProfile->forceFill(['created_at' => now()->subDay()])->save();

        // Newest first, as before the sort option existed.
        $this->actingAs($organizer)
            ->get('/players/search')
            ->assertSeeInOrder(['Bruno Recente', 'Ana Antiga']);
    }

    public function test_the_profile_page_shows_the_attendance_record_to_an_organizer(): void
    {
        $organizer = $this->organizer();
        $player = $this->createPlayer();
        $game = $this->pastGame($organizer);
        $this->join($game, $player);
        $this->finish($organizer, $game);

        $response = $this->actingAs($organizer)->get(route('players.show', $player->playerProfile));

        $response->assertOk();
        $response->assertSee('Histórico');
        $response->assertSee('Partidas jogadas');
        $response->assertSee('100%');
    }

    public function test_a_player_without_history_sees_the_empty_record(): void
    {
        $organizer = $this->organizer();
        $player = $this->createPlayer();

        $this->actingAs($organizer)
            ->get(route('players.show', $player->playerProfile))
            ->assertSee('Ainda não há partidas finalizadas no histórico deste jogador.');
    }

    public function test_the_player_sees_their_own_record_and_rating_trend(): void
    {
        $organizer = $this->organizer();
        $player = $this->createPlayer();
        $game = $this->pastGame($organizer);
        $this->join($game, $player);
        $this->finish($organizer, $game);

        // One rating per match, so the trend needs a second one.
        $earlier = $this->pastGame($organizer, ['date' => today()->subDays(7)->format('Y-m-d')]);

        foreach ([$earlier->id => 3, $game->id => 5] as $gameId => $stars) {
            Rating::create([
                'game_id' => $gameId,
                'organizer_id' => $organizer->id,
                'user_id' => $player->id,
                'overall_rating' => $stars,
                'punctuality_rating' => $stars,
                'behavior_rating' => $stars,
                'performance_rating' => $stars,
            ]);
        }

        $response = $this->actingAs($player)->get(route('ratings.show', $player));

        $response->assertOk();
        $response->assertSee('Histórico');
        $response->assertSee('Evolução das notas');
    }
}
