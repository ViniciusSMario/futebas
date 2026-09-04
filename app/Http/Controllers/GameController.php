<?php

namespace App\Http\Controllers;

use App\Exceptions\SosRequestUnavailableException;
use App\Http\Requests\GameStoreRequest;
use App\Http\Requests\GameUpdateRequest;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Invitation;
use App\Models\Rating;
use App\Models\SosRequest;
use App\Notifications\GameCancelled;
use App\Notifications\GameUpdated;
use App\Services\GamePlayerService;
use App\Services\SosService;
use App\Services\TeamDrawService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class GameController extends Controller
{
    private const TABS = ['informacoes', 'participantes', 'convites', 'pagamentos', 'times'];

    /**
     * Date windows offered by the game search, mirroring the vocabulary
     * the player already knows from the availability filters.
     */
    public const PERIOD_OPTIONS = [
        '' => 'Qualquer data',
        'today' => 'Hoje',
        'tomorrow' => 'Amanhã',
        'weekend' => 'Fim de semana',
        'week' => 'Próximos 7 dias',
    ];

    private const SEARCH_FILTERS = ['q', 'state', 'city', 'modality', 'position', 'period', 'max_price', 'with_spots'];

    /**
     * Search open matches the authenticated user could still join — the
     * player's counterpart to the organizer's "Procurar Jogadores".
     */
    public function index(Request $request): View
    {
        return view('games.search', [
            'games' => $this->searchGames($request),
            'filters' => $request->only(self::SEARCH_FILTERS),
        ]);
    }

    /**
     * Build the filtered game search: only open, not-yet-started matches
     * the user isn't already part of, soonest first.
     */
    private function searchGames(Request $request): LengthAwarePaginator
    {
        $user = $request->user();

        $query = Game::query()
            ->with('user')
            ->withCount(['gamePlayers as confirmed_players_count' => fn ($q) => $q->where('status', GamePlayer::STATUS_CONFIRMED)])
            ->where('status', Game::STATUS_OPEN)
            ->upcoming()
            // A match the user organizes, or already has a live
            // participation in, isn't something they can join — it belongs
            // in "Minhas Partidas" instead.
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('gamePlayers', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', '!=', GamePlayer::STATUS_CANCELLED)
            );

        if ($request->filled('q')) {
            $term = $request->string('q')->trim()->toString();

            $query->where(fn ($q) => $q
                ->where('team_name', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%")
            );
        }

        if ($request->filled('state')) {
            $query->where('state', $request->string('state')->upper()->toString());
        }

        // Comparação exata: a cidade vem de um select do catálogo do IBGE,
        // e um 'like' faria "Bom Jesus" trazer também "Bom Jesus da Lapa".
        // Quem quer buscar por pedaço de nome tem o campo de texto acima.
        if ($request->filled('city')) {
            $query->where('city', $request->string('city')->toString());
        }

        if ($request->filled('modality')) {
            $query->where('modality', $request->string('modality')->toString());
        }

        // A game's `positions` are the ones the organizer is still looking
        // for; an empty list means "any position is welcome".
        if ($request->filled('position')) {
            $position = $request->string('position')->toString();

            $query->where(fn ($q) => $q
                ->whereJsonContains('positions', $position)
                ->orWhereJsonLength('positions', 0)
            );
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        if ($request->boolean('with_spots')) {
            // Confirmed participants still below capacity. Expressed as a
            // correlated subquery because the comparison is against
            // another column, which whereHas() can't express.
            $query->whereRaw(
                '(select count(*) from game_players where game_players.game_id = games.id and game_players.status = ?) < games.max_players',
                [GamePlayer::STATUS_CONFIRMED]
            );
        }

        $this->applyPeriodFilter($query, $request->string('period')->toString());

        return $query->orderBy('date')->orderBy('start_time')->paginate(9)->withQueryString();
    }

    /**
     * Narrow the search to one of the PERIOD_OPTIONS windows. Every
     * comparison uses whereDate because `date` is persisted with a time
     * component.
     */
    private function applyPeriodFilter(Builder $query, string $period): void
    {
        match ($period) {
            'today' => $query->whereDate('date', today()),
            'tomorrow' => $query->whereDate('date', today()->addDay()),
            'week' => $query->whereDate('date', '<=', today()->addDays(7)),
            'weekend' => $query->where(function (Builder $query) {
                foreach ($this->upcomingWeekendDates() as $date) {
                    $query->orWhereDate('date', $date);
                }
            }),
            default => null,
        };
    }

    /**
     * The Saturday and Sunday falling within the next seven days — today
     * included, so a search made on a Saturday still shows that night.
     *
     * @return array<int, Carbon>
     */
    private function upcomingWeekendDates(): array
    {
        return collect(range(0, 6))
            ->map(fn (int $offset) => today()->addDays($offset))
            ->filter(fn ($date) => $date->isSaturday() || $date->isSunday())
            ->values()
            ->all();
    }

    /**
     * Display the form to create a new game request.
     */
    public function create(Request $request): View
    {
        return view('games.create');
    }

    /**
     * Store a new game request created by the authenticated organizer.
     */
    public function store(GameStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $game = Game::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'positions' => $validated['positions'] ?? [],
            'requires_approval' => $request->boolean('requires_approval'),
            'status' => Game::STATUS_OPEN,
        ]);

        if ($request->boolean('organizer_is_playing')) {
            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $request->user()->id,
                'status' => GamePlayer::STATUS_CONFIRMED,
                'payment_status' => GamePlayer::PAYMENT_PENDING,
                'amount_due' => $game->price,
                'joined_at' => now(),
            ]);
        }

        return redirect()->route('games.mine')->with('status', 'game-created');
    }

    /**
     * Display the organizer's management panel for a game, with the
     * requested tab's data eager-loaded to avoid N+1 queries.
     */
    public function show(Request $request, Game $game, TeamDrawService $teams): View
    {
        abort_unless($game->user_id === $request->user()->id, 403);

        $tab = $request->string('tab')->toString();
        $tab = in_array($tab, self::TABS, true) ? $tab : 'informacoes';

        $data = ['game' => $game, 'tab' => $tab];

        match ($tab) {
            'participantes' => [
                $data['gamePlayers'] = $game->gamePlayers()
                    ->with(['user.playerProfile', 'guestPlayer'])
                    ->orderBy('joined_at')
                    ->get()
                    ->sortBy(fn (GamePlayer $gamePlayer) => array_search($gamePlayer->status, GamePlayer::STATUSES, true))
                    ->values(),
                $data['ratedUserIds'] = Rating::query()->where('game_id', $game->id)->pluck('user_id'),
            ],
            'convites' => $data['invitations'] = $game->invitations()
                ->with('user')
                ->where('status', Invitation::STATUS_PENDING)
                ->latest()
                ->get(),
            'pagamentos' => [
                $data['financialSummary'] = $game->financialSummary(),
                $data['gamePlayers'] = $game->gamePlayers()
                    ->with(['user.playerProfile', 'guestPlayer'])
                    ->where('status', GamePlayer::STATUS_CONFIRMED)
                    ->orderBy('joined_at')
                    ->get(),
            ],
            'times' => [
                $data['gameTeams'] = $game->gameTeams()
                    ->with('gamePlayers.user.playerProfile', 'gamePlayers.guestPlayer')
                    ->get(),
                // The strength each team came out with, so the balancing is
                // something the organizer can check rather than trust.
                $data['teamBalance'] = $teams->summarise($data['gameTeams']),
            ],
            default => null,
        };

        $data['confirmedCount'] = $game->confirmedPlayersCount();

        return view('games.show', $data);
    }

    /**
     * Display the form to edit an existing game.
     */
    public function edit(Request $request, Game $game): View
    {
        abort_unless($game->user_id === $request->user()->id, 403);

        return view('games.edit', ['game' => $game]);
    }

    /**
     * Update an existing open game.
     */
    public function update(GameUpdateRequest $request, Game $game, GamePlayerService $service): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($game->isOpen(), 403);

        $validated = $request->validated();
        $priceChanged = bccomp((string) $game->price, (string) $validated['price'], 2) !== 0;
        $capacityGrew = (int) $validated['max_players'] > $game->max_players;
        $changes = $this->describeChanges($game, $validated);

        $game->update([
            ...$validated,
            'positions' => $validated['positions'] ?? [],
            'requires_approval' => $request->boolean('requires_approval'),
        ]);

        // Players who haven't paid yet still owe whatever the price now is —
        // carry the correction through. Whoever already paid keeps the
        // amount they were actually charged, since that's settled history.
        if ($priceChanged) {
            $game->gamePlayers()
                ->where('status', '!=', GamePlayer::STATUS_CANCELLED)
                ->where('payment_status', GamePlayer::PAYMENT_PENDING)
                ->update(['amount_due' => $game->price]);
        }

        // Raising the player limit opens spots just like someone leaving.
        if ($capacityGrew) {
            $service->promoteFromWaitingList($game);
        }

        if ($changes !== []) {
            Notification::send($game->notifiableParticipants(), new GameUpdated($game, $changes));
        }

        return redirect()->route('games.show', $game)->with('status', 'game-updated');
    }

    /**
     * Describe, in words, the edits a participant actually needs to hear
     * about. Everything else an organizer can change — description,
     * positions wanted, approval setting — doesn't move anyone's plans, so
     * it's saved without interrupting them.
     *
     * @param  array<string, mixed>  $validated
     * @return array<int, string>
     */
    private function describeChanges(Game $game, array $validated): array
    {
        $changes = [];

        $newDate = Carbon::parse($validated['date']);
        if ($game->date->format('Y-m-d') !== $newDate->format('Y-m-d')) {
            $changes[] = __('Data: :from → :to', [
                'from' => $game->date->format('d/m'),
                'to' => $newDate->format('d/m'),
            ]);
        }

        $newStart = Carbon::parse($validated['start_time'])->format('H:i');
        if ($game->start_time->format('H:i') !== $newStart) {
            $changes[] = __('Horário: :from → :to', [
                'from' => $game->start_time->format('H:i'),
                'to' => $newStart,
            ]);
        }

        if ($game->location !== $validated['location']) {
            $changes[] = __('Local: :from → :to', [
                'from' => $game->location,
                'to' => $validated['location'],
            ]);
        }

        if (bccomp((string) $game->price, (string) $validated['price'], 2) !== 0) {
            $changes[] = __('Valor: R$ :from → R$ :to', [
                'from' => number_format((float) $game->price, 2, ',', '.'),
                'to' => number_format((float) $validated['price'], 2, ',', '.'),
            ]);
        }

        return $changes;
    }

    /**
     * Cancel an open game.
     */
    public function cancel(Request $request, Game $game, SosService $sos): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($game->isOpen(), 403);

        // Antes de cancelar a partida, e a ordem não é estilo: uma chamada de
        // SOS só pode ser cancelada enquanto está aberta, e
        // `SosRequest::isOpen()` pergunta à partida. Invertido, o serviço
        // recusaria o cancelamento e os goleiros ficariam esperando resposta
        // de uma partida que não existe mais.
        $this->cancelLiveSosRequests($game, $sos);

        $game->cancel();

        // Whoever was counting on this match: everyone taking part, plus
        // anyone still holding an unanswered invitation to it.
        $pendingInvitees = $game->invitations()
            ->where('status', Invitation::STATUS_PENDING)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        Notification::send(
            $game->notifiableParticipants()->merge($pendingInvitees)->unique('id'),
            new GameCancelled($game)
        );

        return redirect()->route('games.show', $game)->with('status', 'game-cancelled');
    }

    /**
     * Encerra as chamadas de goleiro que ainda estavam de pé.
     *
     * Sem isto, o único fim possível para elas era o prazo vencer — e o
     * goleiro que reservou a noite só descobriria horas depois, pelo
     * `sos:notify-expired`, que a partida tinha caído antes.
     */
    private function cancelLiveSosRequests(Game $game, SosService $sos): void
    {
        $game->sosRequests()->live()->get()->each(function (SosRequest $sosRequest) use ($sos) {
            try {
                $sos->cancel($sosRequest, matchCancelled: true);
            } catch (SosRequestUnavailableException) {
                // Alguém escolheu um goleiro neste exato instante: ele já
                // entrou na partida e vai ser avisado do cancelamento como
                // participante, junto com todo mundo.
            }
        });
    }

    /**
     * Mark an open match as finished once its scheduled end time has
     * passed, making it eligible for the organizer to rate players.
     */
    public function finish(Request $request, Game $game): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($game->isEligibleToFinish(), 403);

        $game->finish();

        return redirect()->route('games.mine')->with('status', 'game-finished');
    }

    /**
     * List the authenticated user's games: as organizer, or as a player who
     * participates (confirmed/pending/waiting list) or has a pending
     * invitation, grouped into confirmed / pending / finished.
     */
    public function mine(Request $request): View
    {
        $user = $request->user();

        $gamePlayers = GamePlayer::query()
            ->with('game')
            ->where('user_id', $user->id)
            ->whereIn('status', [GamePlayer::STATUS_CONFIRMED, GamePlayer::STATUS_PENDING, GamePlayer::STATUS_WAITING_LIST])
            ->get()
            ->filter(fn (GamePlayer $gamePlayer) => $gamePlayer->game !== null);

        $participationsByGame = $gamePlayers->keyBy('game_id');

        // An organizer who also plays gets a single 'organizer' card, but
        // it still carries their own participation so they can check in
        // from it like everyone else.
        $organized = Game::query()
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (Game $game) => $this->buildEntry($game, 'organizer', null, null, null, $participationsByGame->get($game->id)));

        // Games the user organizes already have an 'organizer' entry
        // above — skip those here to avoid a duplicate card.
        $gamePlayers = $gamePlayers->filter(fn (GamePlayer $gamePlayer) => $gamePlayer->game->user_id !== $user->id);

        // Invitations carry the team/position the organizer assigned at
        // invite time; look them up (best-effort) to keep showing that
        // detail once the invitation has turned into a participation.
        $invitationsByGame = Invitation::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('game_id');

        $participating = $gamePlayers->map(function (GamePlayer $gamePlayer) use ($invitationsByGame) {
            $invitation = $invitationsByGame->get($gamePlayer->game_id);

            return $this->buildEntry(
                $gamePlayer->game,
                'player',
                $invitation?->team,
                $invitation?->position,
                $gamePlayer->status,
                $gamePlayer
            );
        });

        $coveredGameIds = $gamePlayers->pluck('game_id');

        $invited = Invitation::query()
            ->with('game')
            ->where('user_id', $user->id)
            ->where('status', Invitation::STATUS_PENDING)
            ->whereNotIn('game_id', $coveredGameIds)
            ->get()
            ->filter(fn (Invitation $invitation) => $invitation->game !== null)
            ->map(fn (Invitation $invitation) => $this->buildEntry(
                $invitation->game,
                'player',
                $invitation->team,
                $invitation->position,
                Invitation::STATUS_PENDING
            ));

        $entries = $organized->concat($participating)->concat($invited);

        return view('games.mine', [
            'confirmadas' => $this->sortAscending($entries->where('bucket', 'confirmadas')),
            'pendentes' => $this->sortAscending($entries->where('bucket', 'pendentes')),
            'finalizadas' => $this->sortDescending($entries->where('bucket', 'finalizadas')),
        ]);
    }

    /**
     * @return array{game: Game, role: string, team: ?string, position: ?string, bucket: string, status_label: string, game_player: ?GamePlayer}
     */
    private function buildEntry(Game $game, string $role, ?string $team, ?string $position, ?string $participationStatus, ?GamePlayer $gamePlayer = null): array
    {
        $isOver = in_array($game->status, [Game::STATUS_FINISHED, Game::STATUS_CANCELLED], true) || $game->date->lt(today());

        $isConfirmed = $participationStatus === GamePlayer::STATUS_CONFIRMED;

        $bucket = match (true) {
            $isOver => 'finalizadas',
            $role === 'organizer' => 'confirmadas',
            $isConfirmed => 'confirmadas',
            default => 'pendentes',
        };

        $statusLabel = match (true) {
            $game->status === Game::STATUS_CANCELLED => __('Cancelada'),
            $isOver => __('Finalizada'),
            $bucket === 'confirmadas' => __('Confirmada'),
            $participationStatus === GamePlayer::STATUS_WAITING_LIST => __('Lista de espera'),
            default => __('Pendente'),
        };

        return [
            'game' => $game,
            'role' => $role,
            'team' => $team,
            'position' => $position,
            'bucket' => $bucket,
            'status_label' => $statusLabel,
            'game_player' => $gamePlayer,
        ];
    }

    private function sortAscending(Collection $entries): Collection
    {
        return $entries->sortBy(fn (array $entry) => $entry['game']->date->format('Y-m-d').$entry['game']->start_time->format('H:i'))->values();
    }

    private function sortDescending(Collection $entries): Collection
    {
        return $entries->sortByDesc(fn (array $entry) => $entry['game']->date->format('Y-m-d').$entry['game']->start_time->format('H:i'))->values();
    }
}
