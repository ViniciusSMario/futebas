<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\User;
use App\Notifications\GamePlayerConfirmed;
use App\Notifications\WaitingListSpotOpened;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GamePlayerService
{
    /**
     * Add or reactivate a user's participation in a game, applying the
     * capacity / waiting-list / approval rules consistently across every
     * entry point (invitation accept, public link, guest sign-up, manual
     * add by the organizer).
     */
    public function join(Game $game, User $user, bool $bypassApproval = false): GamePlayer
    {
        return DB::transaction(function () use ($game, $user, $bypassApproval) {
            $existing = GamePlayer::query()
                ->where('game_id', $game->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status !== GamePlayer::STATUS_CANCELLED) {
                return $existing;
            }

            return $this->save($game, $existing, ['user_id' => $user->id], $bypassApproval);
        });
    }

    /**
     * Add or reactivate a guest contact's (no app account) participation in
     * a game. Always bypasses approval: guests can only ever be added
     * directly by the organizer, who is trusted to confirm them outright.
     */
    public function joinGuest(Game $game, GuestPlayer $guestPlayer): GamePlayer
    {
        return DB::transaction(function () use ($game, $guestPlayer) {
            $existing = GamePlayer::query()
                ->where('game_id', $game->id)
                ->where('guest_player_id', $guestPlayer->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status !== GamePlayer::STATUS_CANCELLED) {
                return $existing;
            }

            return $this->save($game, $existing, ['guest_player_id' => $guestPlayer->id], bypassApproval: true);
        });
    }

    /**
     * Fill spots that opened up on a game by promoting the people who have
     * been waiting longest. Called whenever capacity is freed — someone
     * cancels, the organizer removes a participant, or the player limit is
     * raised — so a vacated spot never sits there unnoticed.
     *
     * Games that require approval are deliberately *not* auto-promoted:
     * vetting each entrant is the whole point of that setting. There the
     * organizer is told a spot opened instead, and confirms by hand.
     *
     * Like every SOS state change, the decision is taken under a row lock
     * on the game and re-checked inside the transaction, so two people
     * leaving at once cannot promote the same spot twice.
     *
     * @return Collection<int, GamePlayer> The participants promoted to confirmed.
     */
    public function promoteFromWaitingList(Game $game): Collection
    {
        [$promoted, $awaitingApproval] = DB::transaction(function () use ($game) {
            $locked = Game::whereKey($game->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isOpen()) {
                return [new Collection, 0];
            }

            $confirmedCount = GamePlayer::query()
                ->where('game_id', $locked->id)
                ->where('status', GamePlayer::STATUS_CONFIRMED)
                ->lockForUpdate()
                ->count();

            $spots = $locked->max_players - $confirmedCount;

            if ($spots <= 0) {
                return [new Collection, 0];
            }

            $waiting = GamePlayer::query()
                ->where('game_id', $locked->id)
                ->where('status', GamePlayer::STATUS_WAITING_LIST)
                ->oldest('joined_at')
                ->oldest('id')
                ->limit($spots)
                ->lockForUpdate()
                ->get();

            if ($locked->requires_approval) {
                return [new Collection, $waiting->count()];
            }

            $waiting->each(fn (GamePlayer $gamePlayer) => $gamePlayer->update([
                'status' => GamePlayer::STATUS_CONFIRMED,
            ]));

            return [$waiting, 0];
        });

        foreach ($promoted as $gamePlayer) {
            $gamePlayer->user?->notify(new GamePlayerConfirmed($gamePlayer, promoted: true));
        }

        if ($awaitingApproval > 0) {
            $game->user->notify(new WaitingListSpotOpened($game, $awaitingApproval));
        }

        return $promoted;
    }

    /**
     * Resolve the capacity-aware status and persist the participation,
     * shared by both the registered-user and guest-contact join paths.
     */
    private function save(Game $game, ?GamePlayer $existing, array $identity, bool $bypassApproval): GamePlayer
    {
        $confirmedCount = GamePlayer::query()
            ->where('game_id', $game->id)
            ->where('status', GamePlayer::STATUS_CONFIRMED)
            ->lockForUpdate()
            ->count();

        $isFull = $confirmedCount >= $game->max_players;

        $status = match (true) {
            $isFull => GamePlayer::STATUS_WAITING_LIST,
            $bypassApproval => GamePlayer::STATUS_CONFIRMED,
            $game->requires_approval => GamePlayer::STATUS_PENDING,
            default => GamePlayer::STATUS_CONFIRMED,
        };

        $attributes = [
            'status' => $status,
            'payment_status' => GamePlayer::PAYMENT_PENDING,
            'amount_due' => $game->price,
            'joined_at' => now(),
            // Reactivating a cancelled participation starts a fresh
            // attendance record — the old check-in belonged to the
            // participation that was called off.
            'checked_in_at' => null,
            'no_show' => false,
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing->fresh();
        }

        return GamePlayer::create([
            'game_id' => $game->id,
            ...$identity,
            ...$attributes,
        ]);
    }
}
