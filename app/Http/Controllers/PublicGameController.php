<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicGameGuestJoinRequest;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Notifications\GamePlayerJoined;
use App\Services\GamePlayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicGameController extends Controller
{
    /**
     * Public, shareable page for a game: no authentication required, no
     * participant list (privacy) — just enough to decide whether to join.
     * An authenticated user who already has an active request for this
     * game is sent straight to "Minhas Partidas" to track its status,
     * instead of seeing the join page again.
     */
    public function show(Request $request, Game $game): View|RedirectResponse
    {
        if ($request->user() && $this->hasActiveRequest($request->user()->id, $game)) {
            return redirect()->route('games.mine')->with('status', 'already-requested');
        }

        return view('public.games.show', ['game' => $game]);
    }

    private function hasActiveRequest(int $userId, Game $game): bool
    {
        return GamePlayer::query()
            ->where('game_id', $game->id)
            ->where('user_id', $userId)
            ->where('status', '!=', GamePlayer::STATUS_CANCELLED)
            ->exists();
    }

    /**
     * Handle "Quero Participar". Guests are sent to register — always as a
     * player, never as an organizer — with their intent to join this game
     * preserved across that flow; authenticated users join immediately.
     */
    public function join(Request $request, Game $game): RedirectResponse
    {
        if (! $game->isOpen()) {
            return redirect()
                ->route('public-games.show', $game)
                ->withErrors(['game' => __('Esse Game não está mais aberto para novos participantes.')]);
        }

        if (! $request->user()) {
            session(['intended_game_slug' => $game->slug]);

            return redirect()->route('register');
        }

        return redirect()->route('public-games.show', $game)->with('status', $this->joinStatusFor(
            $this->announce($game, app(GamePlayerService::class)->join($game, $request->user()))
        ));
    }

    /**
     * Handle "Já tenho conta": preserve the intent to join this game and
     * send the guest to the login page.
     */
    public function redirectToLogin(Game $game): RedirectResponse
    {
        if ($game->isOpen()) {
            session(['intended_game_slug' => $game->slug]);
        }

        return redirect()->route('login');
    }

    /**
     * Handle "Jogar sem cadastro": the guest provides just their name and,
     * optionally, phone/e-mail. They're stored as a reusable guest contact
     * owned by the game's organizer (same registry the organizer's manual
     * "Adicionar Jogador" flow uses) and joined to the game directly — no
     * app account is created.
     */
    public function joinAsGuest(PublicGameGuestJoinRequest $request, Game $game): RedirectResponse
    {
        if (! $game->isOpen()) {
            return redirect()
                ->route('public-games.show', $game)
                ->withErrors(['game' => __('Esse Game não está mais aberto para novos participantes.')]);
        }

        $validated = $request->validated();

        $guestPlayer = GuestPlayer::query()
            ->where('organizer_id', $game->user_id)
            ->where('name', $validated['name'])
            ->when($validated['phone'] ?? null, fn ($query, $phone) => $query->where('phone', $phone))
            ->first();

        if (! $guestPlayer) {
            $guestPlayer = GuestPlayer::create([
                'organizer_id' => $game->user_id,
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
            ]);
        }

        return redirect()->route('public-games.show', $game)->with('status', $this->joinStatusFor(
            $this->announce($game, app(GamePlayerService::class)->joinGuest($game, $guestPlayer))
        ));
    }

    /**
     * Let the organizer know somebody came in through the shared link.
     * This lives here rather than in GamePlayerService because only the
     * entry point knows the join was self-service — the same service call
     * also backs the organizer adding someone by hand, which needs no
     * notification.
     */
    private function announce(Game $game, GamePlayer $gamePlayer): GamePlayer
    {
        $game->user->notify(new GamePlayerJoined($gamePlayer));

        return $gamePlayer;
    }

    private function joinStatusFor(GamePlayer $gamePlayer): string
    {
        return match ($gamePlayer->status) {
            GamePlayer::STATUS_CONFIRMED => 'joined-confirmed',
            GamePlayer::STATUS_PENDING => 'joined-pending',
            GamePlayer::STATUS_WAITING_LIST => 'joined-waiting-list',
            default => 'joined-confirmed',
        };
    }
}
