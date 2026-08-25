<?php

namespace App\Http\Controllers;

use App\Http\Requests\RatingStoreRequest;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Invitation;
use App\Models\Rating;
use App\Models\User;
use App\Notifications\PlayerRated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RatingController extends Controller
{
    /**
     * List the ratings a player has received from organizers, with their
     * average scores. Only the rated player may view their own ratings.
     */
    public function show(Request $request, User $user): View
    {
        abort_unless($user->id === $request->user()->id, 403);

        $ratings = Rating::query()
            ->where('user_id', $user->id)
            ->with(['organizer', 'game'])
            ->latest()
            ->get();

        return view('ratings.show', [
            'playerProfile' => $user->playerProfile,
            'ratings' => $ratings,
        ]);
    }

    /**
     * List the players who took part in one of the organizer's finished
     * games, showing who's already been rated.
     */
    public function index(Request $request, Game $game): View
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($game->hasEnded(), 403);

        return view('ratings.index', [
            'game' => $game,
            'participants' => $this->rateableParticipants($game),
            'ratedUserIds' => Rating::query()->where('game_id', $game->id)->pluck('user_id'),
        ]);
    }

    /**
     * Everyone who took part in the match and can still be rated.
     *
     * Sourced from `GamePlayer`, which every way into a game writes: the
     * public link, the game search, a weekly pelada, SOS, or the organizer
     * adding someone by hand. Listing accepted `Invitation`s alone — as
     * this once did — hid every participant who arrived by any other
     * route, which by now is most of them.
     *
     * Invitations are still folded in, because older participations may
     * only have one of those. Between them, this is exactly the set
     * {@see self::authorizeRating()} will let through.
     *
     * Guests are left out: a `Rating` hangs off a user account, and they
     * have none. So is the organizer, who doesn't rate themselves.
     *
     * @return Collection<int, array{user: User, position: ?string}>
     */
    private function rateableParticipants(Game $game): Collection
    {
        $fromParticipations = $game->gamePlayers()
            ->whereNotNull('user_id')
            ->where('status', GamePlayer::STATUS_CONFIRMED)
            ->with('user.playerProfile')
            ->orderBy('joined_at')
            ->get()
            ->map(fn (GamePlayer $gamePlayer) => [
                'user' => $gamePlayer->user,
                'position' => $gamePlayer->user?->playerProfile?->positions[0] ?? null,
            ]);

        $fromInvitations = $game->invitations()
            ->where('status', Invitation::STATUS_ACCEPTED)
            ->with('user')
            ->get()
            ->map(fn (Invitation $invitation) => [
                'user' => $invitation->user,
                'position' => $invitation->position,
            ]);

        return $fromParticipations
            ->concat($fromInvitations)
            ->filter(fn (array $entry) => $entry['user'] !== null)
            ->reject(fn (array $entry) => $entry['user']->id === $game->user_id)
            ->unique(fn (array $entry) => $entry['user']->id)
            ->values();
    }

    /**
     * Display the form to rate a player who took part in one of the
     * organizer's finished games.
     */
    public function create(Request $request, Game $game, User $player): View|RedirectResponse
    {
        $this->authorizeRating($request, $game, $player);

        if ($this->alreadyRated($game, $player)) {
            return $this->redirectAfterRating($game, 'already-rated');
        }

        return view('ratings.create', [
            'game' => $game,
            'player' => $player,
        ]);
    }

    /**
     * Save the organizer's rating for a player and refresh the player's
     * rating averages.
     */
    public function store(RatingStoreRequest $request, Game $game, User $player): RedirectResponse
    {
        $this->authorizeRating($request, $game, $player);

        if ($this->alreadyRated($game, $player)) {
            return back()->withErrors(['rating' => __('Você já avaliou este jogador nesta partida.')]);
        }

        $rating = Rating::create([
            ...$request->validated(),
            'game_id' => $game->id,
            'organizer_id' => $request->user()->id,
            'user_id' => $player->id,
        ]);

        $player->playerProfile?->recalculateRatingAverages();

        $player->notify(new PlayerRated($rating));

        return $this->redirectAfterRating($game, 'rating-saved');
    }

    /**
     * Where to send the organizer after rating a player: the ratings hub
     * once the match has ended (the normal post-match flow), or back to
     * the participants tab when rating a cancelled player right away,
     * since the hub itself is gated on the match having ended.
     */
    private function redirectAfterRating(Game $game, string $status): RedirectResponse
    {
        if ($game->hasEnded()) {
            return redirect()->route('ratings.index', $game)->with('status', $status);
        }

        return redirect()->route('games.show', ['game' => $game, 'tab' => 'participantes'])->with('status', $status);
    }

    /**
     * Ensure the authenticated organizer may rate this player for this
     * game: it must be their own game, and the player must have actually
     * taken part in it.
     *
     * A cancelled participation may be rated right away, so the organizer
     * can flag a late cancellation/no-show without waiting for the match.
     * Otherwise, rating is only allowed once the match has ended, and the
     * player must have a confirmed `GamePlayer` row or an accepted
     * `Invitation` (older participations may only have the latter).
     */
    private function authorizeRating(Request $request, Game $game, User $player): void
    {
        abort_unless($game->user_id === $request->user()->id, 403);

        $gamePlayer = GamePlayer::query()
            ->where('game_id', $game->id)
            ->where('user_id', $player->id)
            ->whereIn('status', [GamePlayer::STATUS_CONFIRMED, GamePlayer::STATUS_CANCELLED])
            ->first();

        if ($gamePlayer?->status === GamePlayer::STATUS_CANCELLED) {
            return;
        }

        abort_unless($game->hasEnded(), 403);

        $wasConfirmedPlayer = $gamePlayer !== null || Invitation::query()
            ->where('game_id', $game->id)
            ->where('user_id', $player->id)
            ->where('status', Invitation::STATUS_ACCEPTED)
            ->exists();

        abort_unless($wasConfirmedPlayer, 404);
    }

    private function alreadyRated(Game $game, User $player): bool
    {
        return Rating::query()
            ->where('game_id', $game->id)
            ->where('user_id', $player->id)
            ->exists();
    }
}
