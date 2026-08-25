<?php

namespace App\Http\Controllers;

use App\Exceptions\SosRequestUnavailableException;
use App\Http\Requests\SosPublishRequest;
use App\Models\Game;
use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Services\SosService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The organizer's side of "SOS Goleiro": publish a paid call for a
 * goalkeeper and pick between the goalkeepers who answer it.
 *
 * The player's side lives in {@see SosOpportunityController}.
 */
class SosController extends Controller
{
    public function __construct(private readonly SosService $sos) {}

    /**
     * The organizer's own SOS calls, newest first.
     */
    public function index(Request $request): View
    {
        $sosRequests = SosRequest::query()
            ->where('organizer_id', $request->user()->id)
            ->with(['game', 'applications'])
            ->latest()
            ->get();

        return view('sos.index', [
            'sosRequests' => $sosRequests,
        ]);
    }

    /**
     * Form to publish a new SOS, either against an existing match or a
     * new one described inline.
     */
    public function create(Request $request): View
    {
        return view('sos.create', [
            'games' => $this->availableGames($request),
        ]);
    }

    /**
     * Publish the SOS and fan out notifications to goalkeepers in the
     * match's region.
     */
    public function store(SosPublishRequest $request): RedirectResponse
    {
        $organizer = $request->user();

        $game = $request->isForNewGame()
            ? Game::create([...$request->newGameAttributes(), 'user_id' => $organizer->id])
            : Game::where('user_id', $organizer->id)->findOrFail($request->integer('game_id'));

        $sosRequest = $this->sos->publish(
            $game,
            $organizer,
            (float) $request->input('offered_value'),
            $request->input('message'),
        );

        return redirect()
            ->route('sos.show', $sosRequest)
            ->with('status', 'sos-published');
    }

    /**
     * The competition itself: every candidacy side by side, so the
     * organizer can weigh price, location and ratings before choosing.
     */
    public function show(Request $request, SosRequest $sosRequest): View
    {
        $this->authorizeOrganizer($request, $sosRequest);

        $sosRequest->load(['game', 'acceptedApplication']);

        $applications = $sosRequest->applications()
            ->with(['user.playerProfile'])
            ->get()
            // Pending candidacies stay on top; the rest fall below in the
            // order they were answered.
            ->sortBy(fn (SosApplication $application) => [
                $application->isPending() ? 0 : 1,
                $application->created_at,
            ])
            ->values();

        return view('sos.show', [
            'sosRequest' => $sosRequest,
            'applications' => $applications,
        ]);
    }

    /**
     * Award the SOS to one goalkeeper. Losing the race here is normal —
     * another tab may have decided first — so it comes back as a message.
     */
    public function accept(Request $request, SosRequest $sosRequest, SosApplication $application): RedirectResponse
    {
        $this->authorizeOrganizer($request, $sosRequest);
        abort_unless($application->sos_request_id === $sosRequest->id, 404);

        try {
            $this->sos->accept($application);
        } catch (SosRequestUnavailableException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('sos.show', $sosRequest)
            ->with('status', 'sos-accepted');
    }

    /**
     * Turn one candidate down while leaving the SOS open to others.
     */
    public function reject(Request $request, SosRequest $sosRequest, SosApplication $application): RedirectResponse
    {
        $this->authorizeOrganizer($request, $sosRequest);
        abort_unless($application->sos_request_id === $sosRequest->id, 404);

        try {
            $this->sos->reject($application);
        } catch (SosRequestUnavailableException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('sos.show', $sosRequest)
            ->with('status', 'sos-rejected');
    }

    public function cancel(Request $request, SosRequest $sosRequest): RedirectResponse
    {
        $this->authorizeOrganizer($request, $sosRequest);

        try {
            $this->sos->cancel($sosRequest);
        } catch (SosRequestUnavailableException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('sos.show', $sosRequest)
            ->with('status', 'sos-cancelled');
    }

    private function authorizeOrganizer(Request $request, SosRequest $sosRequest): void
    {
        abort_unless($sosRequest->organizer_id === $request->user()->id, 403);
    }

    /**
     * Matches this organizer could attach an SOS to: still open, still in
     * the future, and without a live call already running.
     *
     * @return Collection<int, Game>
     */
    private function availableGames(Request $request): Collection
    {
        return Game::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Game::STATUS_OPEN)
            ->whereDate('date', '>=', now()->toDateString())
            ->whereDoesntHave('sosRequests', fn ($query) => $query->live())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }
}
