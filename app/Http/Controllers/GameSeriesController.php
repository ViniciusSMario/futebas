<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameSeriesMemberStoreRequest;
use App\Http\Requests\GameSeriesStoreRequest;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameSeries;
use App\Models\GameSeriesMember;
use App\Models\GuestPlayer;
use App\Models\User;
use App\Services\GameSeriesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Weekly peladas: the recurring series an organizer runs, its generated
 * occurrences, and the regulars seated in each one.
 */
class GameSeriesController extends Controller
{
    /**
     * List the organizer's series, topping up each active one's window of
     * upcoming occurrences on the way through — this is what stands in for
     * a scheduler.
     */
    public function index(Request $request, GameSeriesService $service): View
    {
        $series = GameSeries::query()
            ->where('user_id', $request->user()->id)
            ->withCount('members')
            ->latest()
            ->get();

        $series->where('status', GameSeries::STATUS_ACTIVE)
            ->each(fn (GameSeries $one) => $service->syncUpcoming($one));

        return view('game-series.index', ['series' => $series]);
    }

    public function create(): View
    {
        return view('game-series.create');
    }

    /**
     * Create the series and stamp out its first occurrences right away, so
     * the organizer sees a filled calendar instead of an empty promise.
     */
    public function store(GameSeriesStoreRequest $request, GameSeriesService $service): RedirectResponse
    {
        $validated = $request->validated();

        $series = GameSeries::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'positions' => $validated['positions'] ?? [],
            'requires_approval' => $request->boolean('requires_approval'),
            'status' => GameSeries::STATUS_ACTIVE,
        ]);

        if ($request->boolean('organizer_is_playing')) {
            $service->addMember($series, $request->user());
        }

        $service->syncUpcoming($series);

        return redirect()->route('game-series.show', $series)->with('status', 'series-created');
    }

    /**
     * The series' upcoming occurrences and its regulars, plus the search
     * used to add another one.
     */
    public function show(Request $request, GameSeries $gameSeries, GameSeriesService $service): View
    {
        abort_unless($gameSeries->user_id === $request->user()->id, 403);

        if ($gameSeries->isActive()) {
            $service->syncUpcoming($gameSeries);
        }

        $members = $gameSeries->members()->with(['user', 'guestPlayer'])->get();

        return view('game-series.show', [
            'series' => $gameSeries,
            'members' => $members,
            'upcoming' => $gameSeries->games()
                ->withCount(['gamePlayers as confirmed_players_count' => fn ($q) => $q->where('status', GamePlayer::STATUS_CONFIRMED)])
                ->where('status', Game::STATUS_OPEN)
                ->upcoming()
                ->orderBy('date')
                ->get(),
            'q' => $query = $request->string('q')->trim()->toString(),
            ...$this->searchResults($request, $gameSeries, $members, $query),
        ]);
    }

    /**
     * Add a regular: an existing user, a saved guest contact, or a
     * brand-new guest contact created here and kept for reuse.
     */
    public function storeMember(GameSeriesMemberStoreRequest $request, GameSeries $gameSeries, GameSeriesService $service): RedirectResponse
    {
        abort_unless($gameSeries->user_id === $request->user()->id, 403);
        abort_unless($gameSeries->isActive(), 403);

        $validated = $request->validated();

        $participant = match (true) {
            ! empty($validated['user_id']) => User::findOrFail($validated['user_id']),
            ! empty($validated['guest_player_id']) => GuestPlayer::where('organizer_id', $request->user()->id)
                ->findOrFail($validated['guest_player_id']),
            default => GuestPlayer::create([
                'organizer_id' => $request->user()->id,
                'name' => $validated['new_guest_name'],
                'phone' => $validated['new_guest_phone'] ?? null,
                'email' => $validated['new_guest_email'] ?? null,
            ]),
        };

        $service->addMember($gameSeries, $participant);

        return redirect()->route('game-series.show', $gameSeries)->with('status', 'member-added');
    }

    /**
     * Drop a regular. Occurrences already generated keep them — see
     * {@see GameSeriesService::removeMember()}.
     */
    public function destroyMember(Request $request, GameSeries $gameSeries, GameSeriesMember $member, GameSeriesService $service): RedirectResponse
    {
        abort_unless($gameSeries->user_id === $request->user()->id, 403);
        abort_unless($member->game_series_id === $gameSeries->id, 404);

        $service->removeMember($member);

        return redirect()->route('game-series.show', $gameSeries)->with('status', 'member-removed');
    }

    /**
     * Stop generating new occurrences, leaving the calendar as it stands.
     */
    public function end(Request $request, GameSeries $gameSeries, GameSeriesService $service): RedirectResponse
    {
        abort_unless($gameSeries->user_id === $request->user()->id, 403);

        $service->end($gameSeries);

        return redirect()->route('game-series.show', $gameSeries)->with('status', 'series-ended');
    }

    /**
     * People who could still be added as regulars, excluding those already
     * on the list. Mirrors the game's "Adicionar Jogador" search.
     *
     * @param  Collection<int, GameSeriesMember>  $members
     * @return array{userResults: Collection<int, User>, guestResults: Collection<int, GuestPlayer>}
     */
    private function searchResults(Request $request, GameSeries $series, Collection $members, string $query): array
    {
        if ($query === '') {
            return ['userResults' => collect(), 'guestResults' => collect()];
        }

        $excludeUserIds = $members->pluck('user_id')->filter();
        $excludeGuestIds = $members->pluck('guest_player_id')->filter();

        return [
            'userResults' => User::query()
                ->whereNotIn('id', $excludeUserIds)
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                )
                ->orderBy('name')
                ->limit(20)
                ->get(),
            'guestResults' => GuestPlayer::query()
                ->where('organizer_id', $request->user()->id)
                ->whereNotIn('id', $excludeGuestIds)
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                )
                ->orderBy('name')
                ->limit(20)
                ->get(),
        ];
    }
}
