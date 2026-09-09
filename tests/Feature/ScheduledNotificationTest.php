<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\PlayerProfile;
use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Models\User;
use App\Notifications\GameReminder;
use App\Notifications\PaymentPending;
use App\Notifications\SosApplicationExpired;
use App\Notifications\SosRequestExpired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Os avisos que nascem do relógio, não de alguém clicando: lembrete de
 * partida, SOS que venceu e pagamento em aberto.
 *
 * O que se repete nos três, e é o ponto principal de cada bloco, é o
 * **não** repetir: o scheduler roda de novo a cada hora, e um aviso que
 * sai duas vezes ensina a pessoa a desligar as notificações.
 */
class ScheduledNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // Relógio parado: tudo aqui é "quantas horas antes/depois".
        Carbon::setTestNow(Carbon::create(2026, 8, 26, 12, 0));

        $this->organizer = User::factory()->organizer()->create(['city' => 'Teresina', 'state' => 'PI']);
    }

    // ==================== LEMBRETE DA PARTIDA ====================

    public function test_the_eve_reminder_reaches_confirmed_players_and_the_organizer(): void
    {
        $game = $this->game(['date' => today()->addDay()->format('Y-m-d'), 'start_time' => '09:00']);
        $player = $this->join($game, $this->player('Confirmado'))->user;

        $this->artisan('games:remind')->assertSuccessful();

        Notification::assertSentTo([$player, $this->organizer], GameReminder::class);
        $this->assertNotNull($game->refresh()->reminded_24h_at);
    }

    public function test_the_reminder_never_goes_out_twice(): void
    {
        $game = $this->game(['date' => today()->addDay()->format('Y-m-d'), 'start_time' => '09:00']);
        $this->join($game, $this->player('Confirmado'));

        $this->artisan('games:remind')->assertSuccessful();
        $this->artisan('games:remind')->expectsOutput('0 lembrete(s) enviado(s).');

        Notification::assertSentToTimes($this->organizer, GameReminder::class, 1);
    }

    public function test_the_short_reminder_goes_out_on_its_own_window(): void
    {
        // Hoje às 13h: falta uma hora, então o aviso curto está vencendo.
        $game = $this->game(['date' => today()->format('Y-m-d'), 'start_time' => '13:00']);
        $player = $this->join($game, $this->player('Confirmado'))->user;

        $this->artisan('games:remind')->assertSuccessful();

        Notification::assertSentTo($player, fn (GameReminder $reminder) => $reminder->hoursBefore === Game::REMINDER_LATE_HOURS);
    }

    public function test_a_match_created_at_the_last_minute_only_gets_the_short_reminder(): void
    {
        // Os dois prazos venceram de uma vez; dizer "é amanhã" sobre algo
        // que é daqui a uma hora seria pior que não avisar.
        $game = $this->game(['date' => today()->format('Y-m-d'), 'start_time' => '13:00']);
        $this->join($game, $this->player('Confirmado'));

        $this->artisan('games:remind')->assertSuccessful();

        Notification::assertSentToTimes($this->organizer, GameReminder::class, 1);
        // A véspera é carimbada junto, para não sair depois do curto.
        $this->assertNotNull($game->refresh()->reminded_24h_at);
        $this->assertNotNull($game->reminded_2h_at);
    }

    public function test_nothing_is_sent_once_the_match_has_started(): void
    {
        $this->game(['date' => today()->format('Y-m-d'), 'start_time' => '11:00']);

        $this->artisan('games:remind')->expectsOutput('0 lembrete(s) enviado(s).');

        Notification::assertNothingSent();
    }

    public function test_the_waiting_list_is_not_told_the_match_is_tomorrow(): void
    {
        $game = $this->game(['date' => today()->addDay()->format('Y-m-d'), 'start_time' => '09:00']);
        $waiting = $this->join($game, $this->player('Espera'), GamePlayer::STATUS_WAITING_LIST)->user;

        $this->artisan('games:remind')->assertSuccessful();

        Notification::assertNotSentTo($waiting, GameReminder::class);
    }

    public function test_a_cancelled_match_reminds_nobody(): void
    {
        $game = $this->game([
            // Horário em que o lembrete de véspera estaria vencendo: o que
            // segura o envio é o cancelamento, não o relógio.
            'date' => today()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'status' => Game::STATUS_CANCELLED,
        ]);
        $this->join($game, $this->player('Confirmado'));

        $this->artisan('games:remind')->assertSuccessful();

        Notification::assertNothingSent();
    }

    // ==================== SOS VENCIDO ====================

    public function test_an_expired_call_tells_the_organizer_and_the_candidates(): void
    {
        [$sosRequest, $goalkeeper] = $this->expiredSosWithApplication();

        $this->artisan('sos:notify-expired')->assertSuccessful();

        Notification::assertSentTo($goalkeeper, SosApplicationExpired::class);
        Notification::assertSentTo($this->organizer, fn (SosRequestExpired $sent) => $sent->pendingCount === 1);
        $this->assertNotNull($sosRequest->refresh()->expiry_notified_at);
    }

    public function test_the_expiry_notice_never_goes_out_twice(): void
    {
        [, $goalkeeper] = $this->expiredSosWithApplication();

        $this->artisan('sos:notify-expired')->assertSuccessful();
        $this->artisan('sos:notify-expired')->expectsOutput('0 chamada(s) avisada(s).');

        Notification::assertSentToTimes($goalkeeper, SosApplicationExpired::class, 1);
    }

    public function test_the_status_column_is_left_alone(): void
    {
        // "Expirado" continua sendo derivado de expires_at: o comando avisa,
        // não decide.
        [$sosRequest] = $this->expiredSosWithApplication();

        $this->artisan('sos:notify-expired')->assertSuccessful();

        $this->assertSame(SosRequest::STATUS_OPEN, $sosRequest->refresh()->status);
        $this->assertTrue($sosRequest->hasExpired());
    }

    public function test_a_call_still_within_its_deadline_is_untouched(): void
    {
        $this->sosRequest(['expires_at' => now()->addHours(3)]);

        $this->artisan('sos:notify-expired')->expectsOutput('0 chamada(s) avisada(s).');

        Notification::assertNothingSent();
    }

    public function test_a_call_already_filled_is_not_treated_as_expired(): void
    {
        $this->sosRequest([
            'status' => SosRequest::STATUS_FILLED,
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('sos:notify-expired')->expectsOutput('0 chamada(s) avisada(s).');

        Notification::assertNothingSent();
    }

    public function test_the_organizer_of_a_cancelled_match_is_not_told_again(): void
    {
        [, $goalkeeper] = $this->expiredSosWithApplication(gameAttributes: ['status' => Game::STATUS_CANCELLED]);

        $this->artisan('sos:notify-expired')->assertSuccessful();

        // Ele mesmo cancelou: já sabe que está sem goleiro.
        Notification::assertNotSentTo($this->organizer, SosRequestExpired::class);
        // O candidato, não: para ele a chamada simplesmente parou de responder.
        Notification::assertSentTo($goalkeeper, SosApplicationExpired::class);
    }

    // ==================== PAGAMENTO EM ABERTO ====================

    public function test_whoever_still_owes_gets_one_reminder(): void
    {
        $gamePlayer = $this->finishedGameWithDebt();

        $this->artisan('payments:remind')->assertSuccessful();

        Notification::assertSentTo($gamePlayer->user, PaymentPending::class);
        $this->assertNotNull($gamePlayer->refresh()->payment_reminded_at);
    }

    public function test_the_payment_reminder_never_repeats(): void
    {
        $gamePlayer = $this->finishedGameWithDebt();

        $this->artisan('payments:remind')->assertSuccessful();
        $this->artisan('payments:remind')->expectsOutput('0 lembrete(s) de pagamento enviado(s).');

        Notification::assertSentToTimes($gamePlayer->user, PaymentPending::class, 1);
    }

    public function test_whoever_already_paid_hears_nothing(): void
    {
        $this->finishedGameWithDebt(['payment_status' => GamePlayer::PAYMENT_PAID]);

        $this->artisan('payments:remind')->expectsOutput('0 lembrete(s) de pagamento enviado(s).');

        Notification::assertNothingSent();
    }

    public function test_a_paid_sos_goalkeeper_is_never_charged(): void
    {
        // Entra na partida com valor zero: quem recebe é ele.
        $this->finishedGameWithDebt(['amount_due' => 0]);

        $this->artisan('payments:remind')->expectsOutput('0 lembrete(s) de pagamento enviado(s).');

        Notification::assertNothingSent();
    }

    public function test_a_match_that_is_not_finished_yet_charges_nobody(): void
    {
        $this->finishedGameWithDebt(gameAttributes: ['status' => Game::STATUS_OPEN]);

        $this->artisan('payments:remind')->expectsOutput('0 lembrete(s) de pagamento enviado(s).');

        Notification::assertNothingSent();
    }

    public function test_a_guest_without_an_account_is_left_to_the_organizer(): void
    {
        $game = $this->game([
            'date' => today()->subDays(2)->format('Y-m-d'),
            'status' => Game::STATUS_FINISHED,
        ]);

        $guest = GuestPlayer::create([
            'organizer_id' => $this->organizer->id,
            'name' => 'Convidado do Zé',
            'phone' => '86999990000',
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'guest_player_id' => $guest->id,
            'status' => GamePlayer::STATUS_CONFIRMED,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => '25.00',
            'joined_at' => now()->subDays(5),
        ]);

        $this->artisan('payments:remind')->expectsOutput('0 lembrete(s) de pagamento enviado(s).');

        Notification::assertNothingSent();
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
            'date' => today()->addDay()->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'max_players' => 10,
            'price' => '25.00',
            'positions' => [],
            'status' => Game::STATUS_OPEN,
        ], $attributes));
    }

    private function player(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'state' => 'PI']);

        PlayerProfile::create([
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

        return $user;
    }

    private function join(Game $game, User $user, string $status = GamePlayer::STATUS_CONFIRMED): GamePlayer
    {
        return GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'status' => $status,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now()->subDays(2),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sosRequest(array $attributes = [], array $gameAttributes = []): SosRequest
    {
        $game = $this->game(array_merge(['date' => today()->format('Y-m-d')], $gameAttributes));

        return SosRequest::create(array_merge([
            'game_id' => $game->id,
            'organizer_id' => $this->organizer->id,
            'position' => SosRequest::POSITION,
            'offered_value' => '60.00',
            'status' => SosRequest::STATUS_OPEN,
            'expires_at' => now()->subHour(),
        ], $attributes));
    }

    /**
     * @return array{0: SosRequest, 1: User}
     */
    private function expiredSosWithApplication(array $gameAttributes = []): array
    {
        $sosRequest = $this->sosRequest(gameAttributes: $gameAttributes);
        $goalkeeper = $this->player('Goleiro Candidato');

        SosApplication::create([
            'sos_request_id' => $sosRequest->id,
            'user_id' => $goalkeeper->id,
            'asking_price' => '70.00',
            'status' => SosApplication::STATUS_PENDING,
        ]);

        return [$sosRequest, $goalkeeper];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $gameAttributes
     */
    private function finishedGameWithDebt(array $attributes = [], array $gameAttributes = []): GamePlayer
    {
        $game = $this->game(array_merge([
            'date' => today()->subDays(2)->format('Y-m-d'),
            'status' => Game::STATUS_FINISHED,
        ], $gameAttributes));

        $gamePlayer = $this->join($game, $this->player('Devedor'));

        if ($attributes !== []) {
            $gamePlayer->forceFill($attributes)->save();
        }

        return $gamePlayer;
    }
}
