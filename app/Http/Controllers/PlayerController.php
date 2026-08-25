<?php

namespace App\Http\Controllers;

use App\Models\PlayerProfile;
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

    /**
     * Search for players using the given filters.
     */
    public function index(Request $request): View
    {
        return view('players.index', [
            'players' => self::search($request),
            'filters' => $request->only(['position', 'modality', 'city', 'level', 'availability', 'max_price', 'sort']),
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
        $query = PlayerProfile::query()->with('user')->whereHas('user');

        if ($excludeUserIds !== []) {
            $query->whereNotIn('user_id', $excludeUserIds);
        }

        if ($request->filled('position')) {
            $query->whereJsonContains('positions', $request->string('position')->toString());
        }

        if ($request->filled('modality')) {
            $query->whereJsonContains('modalities', $request->string('modality')->toString());
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%'.$request->string('city')->toString().'%');
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

        return self::applySort($query, $request->string('sort')->toString())
            ->paginate(9)
            ->withQueryString();
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
