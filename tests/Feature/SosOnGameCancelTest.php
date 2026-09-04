<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\PlayerProfile;
use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Models\User;
use App\Notifications\GameCancelled;
use App\Notifications\SosApplicationCancelled;
use App\Services\SosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cancelar a partida encerra a chamada de goleiro dela.
 *
 * Antes, o único fim possível para a chamada era o prazo vencer: o goleiro
 * que tinha reservado a noite só ficava sabendo horas depois, e por um
 * aviso que dizia a coisa errada — a mensagem de "não foi dessa vez" era a
 * mesma usada quando alguém era escolhido.
 */
class SosOnGameCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->organizer = User::factory()->organizer()->create(['city' => 'Teresina', 'state' => 'PI']);
    }

    public function test_cancelling_the_match_closes_its_open_call(): void
    {
        [$game, $sosRequest] = $this->gameWithLiveSos();

        $this->actingAs($this->organizer)
            ->patch(route('games.cancel', $game))
            ->assertRedirect();

        $this->assertSame(SosRequest::STATUS_CANCELLED, $sosRequest->refresh()->status);
        $this->assertSame(Game::STATUS_CANCELLED, $game->refresh()->status);
    }

    public function test_the_candidates_hear_about_it_right_away(): void
    {
        [$game, $sosRequest] = $this->gameWithLiveSos();
        $goalkeeper = $this->applicant($sosRequest);

        $this->actingAs($this->organizer)->patch(route('games.cancel', $game));

        // E com o motivo certo: a partida caiu, ninguém foi escolhido.
        Notification::assertSentTo(
            $goalkeeper,
            fn (SosApplicationCancelled $sent) => $sent->matchCancelled === true,
        );
    }

    public function test_the_pending_candidacy_is_closed_too(): void
    {
        [$game, $sosRequest] = $this->gameWithLiveSos();
        $goalkeeper = $this->applicant($sosRequest);

        $this->actingAs($this->organizer)->patch(route('games.cancel', $game));

        $application = SosApplication::where('user_id', $goalkeeper->id)->firstOrFail();

        $this->assertSame(SosApplication::STATUS_REJECTED, $application->status);
        $this->assertNotNull($application->responded_at);
    }

    public function test_calling_off_the_call_alone_says_something_different(): void
    {
        // O outro caminho para o mesmo fim: o organizador desiste da chamada
        // e mantém a partida. Aqui a mensagem não pode falar em partida
        // cancelada.
        [, $sosRequest] = $this->gameWithLiveSos();
        $goalkeeper = $this->applicant($sosRequest);

        $this->actingAs($this->organizer)
            ->patch(route('sos.cancel', $sosRequest))
            ->assertRedirect();

        Notification::assertSentTo(
            $goalkeeper,
            fn (SosApplicationCancelled $sent) => $sent->matchCancelled === false,
        );
    }

    public function test_a_goalkeeper_already_chosen_is_told_as_a_participant(): void
    {
        [$game, $sosRequest] = $this->gameWithLiveSos();
        $goalkeeper = $this->applicant($sosRequest);

        // Aceito antes do cancelamento: virou participante da partida.
        app(SosService::class)->accept(
            SosApplication::where('user_id', $goalkeeper->id)->firstOrFail()
        );

        $this->actingAs($this->organizer)->patch(route('games.cancel', $game));

        Notification::assertSentTo($goalkeeper, GameCancelled::class);
        Notification::assertNotSentTo($goalkeeper, SosApplicationCancelled::class);
        // A chamada já estava preenchida: o cancelamento da partida não a
        // reescreve.
        $this->assertSame(SosRequest::STATUS_FILLED, $sosRequest->refresh()->status);
    }

    public function test_cancelling_a_match_without_any_call_still_works(): void
    {
        $game = $this->game();

        $this->actingAs($this->organizer)
            ->patch(route('games.cancel', $game))
            ->assertRedirect();

        $this->assertSame(Game::STATUS_CANCELLED, $game->refresh()->status);
    }

    // ==================== APOIO ====================

    private function game(): Game
    {
        return Game::create([
            'user_id' => $this->organizer->id,
            'team_name' => 'Furacão FC',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'state' => 'PI',
            'modality' => 'Society',
            'date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'max_players' => 10,
            'price' => '25.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
        ]);
    }

    /**
     * @return array{0: Game, 1: SosRequest}
     */
    private function gameWithLiveSos(): array
    {
        $game = $this->game();

        $sosRequest = SosRequest::create([
            'game_id' => $game->id,
            'organizer_id' => $this->organizer->id,
            'position' => SosRequest::POSITION,
            'offered_value' => '60.00',
            'status' => SosRequest::STATUS_OPEN,
            'expires_at' => $game->startsAt(),
        ]);

        return [$game, $sosRequest];
    }

    private function applicant(SosRequest $sosRequest): User
    {
        $user = User::factory()->create(['name' => 'Goleiro Candidato', 'state' => 'PI']);

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

        SosApplication::create([
            'sos_request_id' => $sosRequest->id,
            'user_id' => $user->id,
            'asking_price' => '70.00',
            'status' => SosApplication::STATUS_PENDING,
        ]);

        return $user;
    }
}
