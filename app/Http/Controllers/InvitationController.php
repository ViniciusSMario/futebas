<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameInvitationStoreRequest;
use App\Http\Requests\InvitationStoreRequest;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Invitation;
use App\Models\PlayerProfile;
use App\Notifications\InvitationAnswered;
use App\Notifications\InvitationReceived;
use App\Services\GamePlayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * List the authenticated player's invitations: pending ones awaiting a
     * response, and the ones already accepted or declined.
     */
    public function index(Request $request): View
    {
        $invitations = Invitation::query()
            ->where('user_id', $request->user()->id)
            ->with(['game', 'organizer'])
            ->latest()
            ->get()
            ->filter(fn (Invitation $invitation) => $invitation->game !== null);

        return view('invitations.index', [
            'pendentes' => $invitations->where('status', Invitation::STATUS_PENDING)->values(),
            'respondidos' => $invitations->whereIn('status', [Invitation::STATUS_ACCEPTED, Invitation::STATUS_DECLINED])->values(),
        ]);
    }

    /**
     * Display the form for inviting a player to one of the organizer's
     * existing open match requests.
     */
    public function create(PlayerProfile $playerProfile): View
    {
        $alreadyInvitedGameIds = Invitation::query()
            ->where('user_id', $playerProfile->user_id)
            ->pluck('game_id');

        $games = Game::query()
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->whereNotIn('id', $alreadyInvitedGameIds)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return view('invitations.create', [
            'playerProfile' => $playerProfile,
            'games' => $games,
        ]);
    }

    /**
     * Send an invitation from the authenticated organizer to the given
     * player for one of the organizer's existing match requests.
     */
    public function store(InvitationStoreRequest $request, PlayerProfile $playerProfile): RedirectResponse
    {
        $validated = $request->validated();

        $game = Game::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($validated['game_id']);

        $alreadyInvited = Invitation::query()
            ->where('game_id', $game->id)
            ->where('user_id', $playerProfile->user_id)
            ->exists();

        if ($alreadyInvited) {
            return back()->withErrors(['game_id' => __('Este jogador já foi convidado para essa partida.')])->withInput();
        }

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $request->user()->id,
            'user_id' => $playerProfile->user_id,
            'team' => $game->team_name,
            'position' => $validated['position'] ?? ($playerProfile->positions[0] ?? null),
            'value' => $validated['value'] ?? $game->price,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $invitation->user->notify(new InvitationReceived($invitation));

        return redirect()->route('players.show', $playerProfile)->with('status', 'invitation-sent');
    }

    /**
     * Accept an invitation. Only the invited player may do this. Accepting
     * also adds the player to the game's roster, respecting its capacity
     * and approval settings.
     */
    public function accept(Request $request, Invitation $invitation, GamePlayerService $service): RedirectResponse
    {
        abort_unless($invitation->user_id === $request->user()->id, 403);

        if ($invitation->status === Invitation::STATUS_PENDING) {
            DB::transaction(function () use ($invitation, $service, $request) {
                $invitation->update(['status' => Invitation::STATUS_ACCEPTED]);

                if ($invitation->game) {
                    $service->join($invitation->game, $request->user());
                }
            });

            // Only the answer goes out, not a separate "joined the game"
            // notice: to the organizer these are the same event.
            $invitation->organizer?->notify(new InvitationAnswered($invitation));
        }

        return redirect()->route('invitations.index')->with('status', 'invitation-accepted');
    }

    /**
     * Decline an invitation. Only the invited player may do this.
     */
    public function decline(Request $request, Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->user_id === $request->user()->id, 403);

        if ($invitation->status === Invitation::STATUS_PENDING) {
            $invitation->update(['status' => Invitation::STATUS_DECLINED]);

            $invitation->organizer?->notify(new InvitationAnswered($invitation));
        }

        return redirect()->route('invitations.index')->with('status', 'invitation-declined');
    }

    /**
     * Search for players to invite directly to the given game, excluding
     * anyone already an active participant or already pending-invited.
     */
    public function searchForGame(Request $request, Game $game): View
    {
        abort_unless($game->user_id === $request->user()->id, 403);

        // Guest participants have a null user_id, and a single null inside
        // a NOT IN turns the whole comparison null — which would silently
        // empty the search as soon as the game had one guest.
        $excludeUserIds = $game->gamePlayers()
            ->where('status', '!=', GamePlayer::STATUS_CANCELLED)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->concat(
                $game->invitations()->where('status', Invitation::STATUS_PENDING)->pluck('user_id')
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('games.invite-search', [
            'game' => $game,
            'players' => PlayerController::search($request, $excludeUserIds),
            'filters' => $request->only(['position', 'modality', 'city', 'level', 'availability', 'max_price', 'sort']),
        ]);
    }

    /**
     * Send an invitation from the authenticated organizer to the given
     * player, scoped to a specific game (the game comes from the route,
     * not a picked value).
     */
    public function storeForGame(GameInvitationStoreRequest $request, Game $game, PlayerProfile $playerProfile): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($game->isOpen(), 403);

        $validated = $request->validated();

        $alreadyInvited = Invitation::query()
            ->where('game_id', $game->id)
            ->where('user_id', $playerProfile->user_id)
            ->exists();

        if ($alreadyInvited) {
            return back()->withErrors(['user_id' => __('Este jogador já foi convidado para esse Game.')]);
        }

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $request->user()->id,
            'user_id' => $playerProfile->user_id,
            'team' => $game->team_name,
            'position' => $validated['position'] ?? ($playerProfile->positions[0] ?? null),
            'value' => $validated['value'] ?? $game->price,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $invitation->user->notify(new InvitationReceived($invitation));

        return redirect()->route('games.show', ['game' => $game, 'tab' => 'convites'])->with('status', 'invitation-sent');
    }
}
