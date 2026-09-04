<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\Plan;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public const AVAILABILITY_OPTIONS = [
        '' => 'Qualquer dia',
        'today' => 'Hoje',
        'tomorrow' => 'Amanhã',
        'weekend' => 'Final de semana',
        '0' => 'Domingo',
        '1' => 'Segunda',
        '2' => 'Terça',
        '3' => 'Quarta',
        '4' => 'Quinta',
        '5' => 'Sexta',
        '6' => 'Sábado',
    ];

    /**
     * How the results can be ordered. Reputation and reliability are the
     * two an organizer actually picks on.
     */
    public const SORT_OPTIONS = [
        '' => 'Mais recentes',
        'rating' => 'Melhor avaliados',
        'attendance' => 'Mais presentes',
        'price' => 'Menor preço',
    ];

    public function __construct(private readonly PlanService $plans) {}

    /**
     * Search for players using the given filters.
     */
    public function index(Request $request): View
    {
        return view('players.index', [
            'players' => self::search($request),
            'filters' => $request->only(['position', 'modality', 'city', 'level', 'availability', 'max_price', 'sort', 'nearby', 'state']),
            'canUseNearby' => (bool) $request->user()?->planAllows(Feature::NEARBY_CITIES),
            'nearbyPlan' => $this->plans->upgradeFor(Feature::NEARBY_CITIES, $request->user()->currentPlan()),
        ]);
    }

    /**
     * Display a single player's public profile.
     */
    public function show(PlayerProfile $playerProfile): View
    {
        $playerProfile->load(['user.availabilities' => fn ($query) => $query->orderBy('day_of_week')]);

        return view('players.show', [
            'playerProfile' => $playerProfile,
        ]);
    }

    /**
     * Build the filtered player search query.
     *
     * @param  array<int, int>  $excludeUserIds  User ids to omit from the results (e.g. players already in a game).
     */
    public static function search(Request $request, array $excludeUserIds = []): LengthAwarePaginator
    {
        $query = PlayerProfile::query()
            ->select('player_profiles.*')
            // O plano do jogador vem junto do resultado, para a ordenação
            // por destaque e para o selo no card. É subconsulta, e não
            // join, porque `users` e `player_profiles` têm colunas de mesmo
            // nome (city, state) que um join deixaria ambíguas.
            ->addSelect(['plan' => User::query()
                ->select('plan')
                ->whereColumn('users.id', 'player_profiles.user_id'),
            ])
            ->with('user')
            ->whereHas('user');

        if ($excludeUserIds !== []) {
            $query->whereNotIn('user_id', $excludeUserIds);
        }

        if ($request->filled('position')) {
            $query->whereJsonContains('positions', $request->string('position')->toString());
        }

        if ($request->filled('modality')) {
            $query->whereJsonContains('modalities', $request->string('modality')->toString());
        }

        if ($request->filled('state')) {
            $query->where('state', $request->string('state')->upper()->toString());
        }

        if ($request->filled('city')) {
            self::applyCity($query, $request);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->string('level')->toString());
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_game', '<=', (float) $request->input('max_price'));
        }

        if ($request->filled('availability')) {
            $days = match ($request->string('availability')->toString()) {
                'today' => [now()->dayOfWeek],
                'tomorrow' => [now()->addDay()->dayOfWeek],
                'weekend' => [0, 6],
                default => is_numeric($request->input('availability')) ? [(int) $request->input('availability')] : [],
            };

            if ($days !== []) {
                $query->whereHas('user.availabilities', fn ($q) => $q->whereIn('day_of_week', $days));
            }
        }

        return self::applySort(
            self::applyHighlight($query),
            $request->string('sort')->toString(),
        )
            ->paginate(9)
            ->withQueryString();
    }

    /**
     * Filtro de cidade — e, para quem tem o recurso no plano, também as
     * cidades vizinhas.
     *
     * "Vizinha" aqui é o mesmo critério que o SOS usa para escolher quem
     * avisar: jogadores do estado de quem procura que declararam jogar
     * fora da própria cidade. É a melhor aproximação de distância que os
     * dados permitem — o perfil guarda cidade e estado, não coordenadas —
     * e tem a vantagem de só trazer quem topa se deslocar.
     *
     * @param  Builder<PlayerProfile>  $query
     */
    private static function applyCity(Builder $query, Request $request): void
    {
        $city = $request->string('city')->toString();
        $user = $request->user();

        // Exata: a cidade vem do select do catálogo do IBGE, então um
        // 'like' só serviria para "Bom Jesus" trazer "Bom Jesus da Lapa"
        // junto.
        if (! $request->boolean('nearby') || ! $user?->planAllows(Feature::NEARBY_CITIES)) {
            $query->where('city', $city);

            return;
        }

        // O estado da própria busca manda; o de quem procura é a
        // aproximação de quando a busca não escolheu nenhum.
        $state = $request->filled('state')
            ? $request->string('state')->upper()->toString()
            : $user->state;

        $query->where(function (Builder $region) use ($city, $state) {
            $region->where('city', $city);

            if (filled($state)) {
                $region->orWhere(fn (Builder $q) => $q
                    ->where('plays_outside_city', true)
                    ->where('state', $state));
            }
        });
    }

    /**
     * Destaque de quem assina: aparece antes, em qualquer ordenação.
     *
     * A ordenação lê a cópia do plano em `users.plan`, porque um `order by`
     * não tem como chamar as regras de assinatura em PHP. Essa cópia pode
     * atrasar alguns instantes; um destaque que demora a aparecer é bem
     * diferente de um recurso liberado indevidamente, e é por isso que
     * nenhum gate lê essa coluna.
     *
     * @param  Builder<PlayerProfile>  $query
     * @return Builder<PlayerProfile>
     */
    private static function applyHighlight(Builder $query): Builder
    {
        $cases = '';
        $bindings = [];

        foreach (Plan::catalog() as $plan) {
            if (! $plan->allows(Feature::SEARCH_HIGHLIGHT)) {
                continue;
            }

            $cases .= ' when ? then '.$plan->rank();
            $bindings[] = $plan->value;
        }

        if ($cases === '') {
            return $query;
        }

        return $query->orderByRaw('case plan'.$cases.' else 0 end desc', $bindings);
    }

    /**
     * Order the results by one of the SORT_OPTIONS.
     *
     * Nulls sort last under DESC in both MySQL and SQLite, so players with
     * no ratings — or no attendance record yet — fall below those who have
     * one, which is what an organizer ranking by reputation expects. Volume
     * only breaks ties: a single five-star review still outranks a long
     * strong record, the familiar limitation of a plain average.
     *
     * @param  Builder<PlayerProfile>  $query
     * @return Builder<PlayerProfile>
     */
    private static function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'rating' => $query->orderByDesc('average_rating')->orderByDesc('ratings_count'),
            'attendance' => $query->orderByDesc('attendance_rate')->orderByDesc('games_played'),
            'price' => $query->orderBy('price_per_game'),
            default => $query->latest(),
        };
    }
}
