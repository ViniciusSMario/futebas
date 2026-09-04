<?php

namespace App\Http\Controllers;

use App\Exceptions\PlanLimitReachedException;
use App\Exceptions\SosRequestUnavailableException;
use App\Http\Requests\SosApplicationRequest;
use App\Models\GamePlayer;
use App\Models\PlayerProfile;
use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Services\SosService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The goalkeeper's side of "SOS Goleiro": the calls they were notified
 * about, and their candidacies for them.
 *
 * Applying never books the slot — every candidacy is pending until the
 * organizer picks a winner in {@see SosController}.
 */
class SosOpportunityController extends Controller
{
    public function __construct(private readonly SosService $sos) {}

    /**
     * Open calls this player could take, plus the ones they already
     * answered so they can follow the outcome.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $profile = $user->playerProfile;

        // Non-goalkeepers get the page, but empty and explained — a 403
        // would be unhelpful to someone one profile edit away from using it.
        $opportunities = $user->isGoalkeeper()
            ? SosRequest::query()
                ->live()
                ->where(fn (Builder $query) => $this->scopeToRegion($query, $profile, $user->state))
                ->with(['game', 'organizer'])
                ->whereHas('game', fn (Builder $query) => $query
                    ->where('status', 'open')
                    // A call for the match they're already in isn't an
                    // opportunity. Publishing skips them, but they may have
                    // been added to the match after it went out.
                    ->whereDoesntHave('gamePlayers', fn (Builder $players) => $players
                        ->where('user_id', $user->id)
                        ->whereNot('status', GamePlayer::STATUS_CANCELLED))
                )
                ->orderBy('expires_at')
                ->get()
            : SosRequest::query()->whereRaw('1 = 0')->get();

        $applications = $user->sosApplications()
            ->with(['sosRequest.game', 'sosRequest.organizer'])
            ->latest()
            ->get();

        return view('sos.opportunities.index', [
            'opportunities' => $opportunities,
            'applications' => $applications,
            'applicationsByRequest' => $applications->keyBy('sos_request_id'),
            'playerProfile' => $profile,
            'isGoalkeeper' => $user->isGoalkeeper(),
        ]);
    }

    /**
     * One call in full, with the form to apply or update an existing bid.
     */
    public function show(Request $request, SosRequest $sosRequest): View
    {
        // A specific call is goalkeeper-only territory, however the link
        // was reached.
        abort_unless($request->user()->isGoalkeeper(), 403);

        $sosRequest->load(['game', 'organizer']);

        $application = $sosRequest->applications()
            ->where('user_id', $request->user()->id)
            ->first();

        return view('sos.opportunities.show', [
            'sosRequest' => $sosRequest,
            'application' => $application,
            'alreadyInGame' => (bool) $sosRequest->game?->hasParticipant($request->user()),
            'competitorsCount' => $sosRequest->applications()
                ->where('status', SosApplication::STATUS_PENDING)
                ->when($application, fn ($query) => $query->whereKeyNot($application->id))
                ->count(),
        ]);
    }

    /**
     * Apply, or revise an earlier bid. Always lands as pending.
     */
    public function store(SosApplicationRequest $request, SosRequest $sosRequest): RedirectResponse
    {
        // Chamada que fechou e limite do plano que acabou são a mesma
        // conversa para quem está aqui: um recado na tela, e a página
        // continua de pé.
        try {
            $this->sos->apply(
                $sosRequest,
                $request->user(),
                (float) $request->input('asking_price'),
                $request->input('message'),
            );
        } catch (SosRequestUnavailableException|PlanLimitReachedException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('sos-opportunities.show', $sosRequest)
            ->with('status', 'sos-applied');
    }

    /**
     * Pull out of a call the player is no longer available for.
     */
    public function withdraw(Request $request, SosRequest $sosRequest): RedirectResponse
    {
        $application = $sosRequest->applications()
            ->where('user_id', $request->user()->id)
            ->where('status', SosApplication::STATUS_PENDING)
            ->firstOrFail();

        $this->sos->withdraw($application);

        return redirect()
            ->route('sos-opportunities.index')
            ->with('status', 'sos-withdrawn');
    }

    /**
     * Mirror of {@see SosService::candidatesInRegion()} from the
     * goalkeeper's point of view: the same city and modality rules,
     * expressed as a filter over calls rather than over players.
     *
     * Position is not among them — every SOS is for a goalkeeper, and only
     * goalkeepers reach this query.
     */
    private function scopeToRegion(Builder $query, ?PlayerProfile $profile, ?string $userState): Builder
    {
        if ($profile === null) {
            // No sports profile yet: nothing matches, but the page still
            // renders and can explain why.
            return $query->whereRaw('1 = 0');
        }

        $modalities = $profile->modalities ?? [];
        $state = $profile->state ?? $userState;

        return $query
            ->whereHas('game', function (Builder $game) use ($modalities, $profile, $state) {
                $game->whereIn('modality', $modalities ?: ['']);

                $game->where(function (Builder $region) use ($profile, $state) {
                    $region->where('city', $profile->city);

                    if ($profile->plays_outside_city && filled($state)) {
                        // Travelling players also see calls from anywhere in
                        // their state — the match's state, the same column
                        // SosService::candidatesInRegion() reads.
                        $region->orWhere('state', $state);
                    }
                });
            });
    }
}
