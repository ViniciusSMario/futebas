<?php

namespace App\Http\Controllers;

use App\Http\Requests\GamePlayerLeaveRequest;
use App\Http\Requests\GamePlayerStoreRequest;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\User;
use App\Notifications\GamePlayerConfirmed;
use App\Services\GamePlayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GamePlayerController extends Controller
{
    /**
     * Display the form to search for an existing user, or one of the
     * organizer's own reusable guest contacts, to add manually to the
     * game — plus the option to register a brand-new guest contact.
     */
    public function create(Request $request, Game $game): View
    {
        abort_unless($game->user_id === $request->user()->id, 403);

        $userResults = collect();
        $guestResults = collect();
        $query = $request->string('q')->trim()->toString();

        if ($query !== '') {
            $activeGamePlayers = $game->gamePlayers()->where('status', '!=', GamePlayer::STATUS_CANCELLED);

            $excludeUserIds = (clone $activeGamePlayers)->whereNotNull('user_id')->pluck('user_id')->push($game->user_id);
            $excludeGuestPlayerIds = (clone $activeGamePlayers)->whereNotNull('guest_player_id')->pluck('guest_player_id');

            $userResults = User::query()
                ->whereNotIn('id', $excludeUserIds)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })
                ->orderBy('name')
                ->limit(20)
                ->get();

            $guestResults = GuestPlayer::query()
                ->where('organizer_id', $request->user()->id)
                ->whereNotIn('id', $excludeGuestPlayerIds)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })
                ->orderBy('name')
                ->limit(20)
                ->get();
        }

        return view('games.add-player', [
            'game' => $game,
            'q' => $query,
            'userResults' => $userResults,
            'guestResults' => $guestResults,
        ]);
    }

    /**
     * Add a participant directly to the game, bypassing the game's
     * approval setting (the organizer is trusted to confirm directly).
     * The participant is either an existing app user, one of the
     * organizer's reusable guest contacts, or a brand-new guest contact
     * (created here and kept for reuse in future Games).
     */
    public function store(GamePlayerStoreRequest $request, Game $game, GamePlayerService $service): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($game->isOpen(), 403);

        $validated = $request->validated();

        if (! empty($validated['user_id'])) {
            $user = User::findOrFail($validated['user_id']);
            $service->join($game, $user, bypassApproval: true);
        } else {
            $guestPlayer = ! empty($validated['guest_player_id'])
                ? GuestPlayer::where('organizer_id', $request->user()->id)->findOrFail($validated['guest_player_id'])
                : GuestPlayer::create([
                    'organizer_id' => $request->user()->id,
                    'name' => $validated['new_guest_name'],
                    'phone' => $validated['new_guest_phone'] ?? null,
                    'email' => $validated['new_guest_email'] ?? null,
                ]);

            $service->joinGuest($game, $guestPlayer);
        }

        return redirect()->route('games.show', ['game' => $game, 'tab' => 'participantes'])->with('status', 'player-added');
    }

    /**
     * Promote a waiting-list or pending-approval participant to confirmed
     * by hand. Freed spots are filled automatically now
     * ({@see GamePlayerService::promoteFromWaitingList()}), so this is the
     * organizer's override: it is the only path for approval-gated games,
     * which are never auto-promoted, and it deliberately still allows
     * going past capacity — the organizer knows their own pitch.
     */
    public function confirm(Request $request, Game $game, GamePlayer $gamePlayer): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($gamePlayer->game_id === $game->id, 404);
        abort_unless(in_array($gamePlayer->status, [GamePlayer::STATUS_WAITING_LIST, GamePlayer::STATUS_PENDING], true), 422);

        $gamePlayer->update(['status' => GamePlayer::STATUS_CONFIRMED]);

        // Getting a spot used to be silent — the player had to open the app
        // and notice. Guests have no account to tell.
        $gamePlayer->user?->notify(new GamePlayerConfirmed($gamePlayer));

        return redirect()->route('games.show', ['game' => $game, 'tab' => 'participantes'])->with('status', 'player-confirmed');
    }

    /**
     * Toggle a participant's payment status between pending and paid.
     */
    public function updatePayment(Request $request, Game $game, GamePlayer $gamePlayer): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($gamePlayer->game_id === $game->id, 404);

        $gamePlayer->update([
            'payment_status' => $gamePlayer->payment_status === GamePlayer::PAYMENT_PAID
                ? GamePlayer::PAYMENT_PENDING
                : GamePlayer::PAYMENT_PAID,
        ]);

        return redirect()->back()->with('status', 'payment-updated');
    }

    /**
     * Remove a participant from the game (soft: keeps the row for history).
     */
    public function destroy(Request $request, Game $game, GamePlayer $gamePlayer, GamePlayerService $service): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($gamePlayer->game_id === $game->id, 404);

        $freedASpot = $gamePlayer->status === GamePlayer::STATUS_CONFIRMED;

        $gamePlayer->update(['status' => GamePlayer::STATUS_CANCELLED]);

        if ($freedASpot) {
            $service->promoteFromWaitingList($game);
        }

        return redirect()->route('games.show', ['game' => $game, 'tab' => 'participantes'])->with('status', 'player-removed');
    }

    /**
     * Toggle the organizer's record that a confirmed participant didn't
     * turn up. Kept apart from the check-in — which only the participant
     * writes — and only available once the check-in window has opened,
     * since before that there's nothing to be absent from yet.
     */
    public function toggleNoShow(Request $request, Game $game, GamePlayer $gamePlayer): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($gamePlayer->game_id === $game->id, 404);
        abort_unless($gamePlayer->status === GamePlayer::STATUS_CONFIRMED, 422);
        abort_unless($game->hasCheckInStarted(), 422);

        $gamePlayer->update(['no_show' => ! $gamePlayer->no_show]);

        // Marking an absence after the match was finished still has to move
        // the player's record.
        $gamePlayer->user?->playerProfile?->recalculateAttendanceStats();

        return redirect()
            ->route('games.show', ['game' => $game, 'tab' => 'participantes'])
            ->with('status', 'no-show-updated');
    }

    /**
     * Confirm the authenticated participant's presence on match day.
     */
    public function checkIn(Request $request, Game $game): RedirectResponse
    {
        $gamePlayer = $this->participationOf($request, $game);

        abort_unless($gamePlayer->canCheckIn(), 422);

        // A confirmation from the participant themselves outranks an
        // absence the organizer had already pencilled in.
        $gamePlayer->update(['checked_in_at' => now(), 'no_show' => false]);

        return redirect()->route('games.mine')->with('status', 'checked-in');
    }

    /**
     * Undo a check-in, for whoever tapped it by mistake or no longer can
     * make it. Only while the window is still open — once the match has
     * started, attendance is history.
     */
    public function undoCheckIn(Request $request, Game $game): RedirectResponse
    {
        $gamePlayer = $this->participationOf($request, $game);

        abort_unless($gamePlayer->hasCheckedIn() && $game->isCheckInOpen(), 422);

        $gamePlayer->update(['checked_in_at' => null]);

        return redirect()->route('games.mine')->with('status', 'check-in-undone');
    }

    /**
     * The authenticated user's own participation in the given game.
     */
    private function participationOf(Request $request, Game $game): GamePlayer
    {
        return GamePlayer::query()
            ->where('game_id', $game->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    /**
     * Cancel the authenticated user's own confirmed participation in the
     * game. Only allowed until 24 hours before the match starts.
     */
    public function leave(GamePlayerLeaveRequest $request, Game $game, GamePlayerService $service): RedirectResponse
    {
        $gamePlayer = $this->participationOf($request, $game);

        abort_unless($gamePlayer->status === GamePlayer::STATUS_CONFIRMED, 422);
        abort_unless($game->isCancellableByPlayer(), 422);

        $gamePlayer->update([
            'status' => GamePlayer::STATUS_CANCELLED,
            'cancellation_reason' => $request->validated('reason'),
            'cancelled_at' => now(),
        ]);

        $service->promoteFromWaitingList($game);

        return redirect()->route('games.mine')->with('status', 'left-game');
    }
}
