<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'user_id', 'guest_player_id', 'status', 'payment_status', 'amount_due', 'game_team_id', 'joined_at', 'cancellation_reason', 'cancelled_at', 'checked_in_at', 'no_show'])]
class GamePlayer extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_WAITING_LIST = 'waiting_list';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_CONFIRMED, self::STATUS_PENDING, self::STATUS_WAITING_LIST, self::STATUS_CANCELLED];

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_STATUSES = [self::PAYMENT_PENDING, self::PAYMENT_PAID];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'joined_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'no_show' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gameTeam(): BelongsTo
    {
        return $this->belongsTo(GameTeam::class);
    }

    public function guestPlayer(): BelongsTo
    {
        return $this->belongsTo(GuestPlayer::class);
    }

    public function isGuest(): bool
    {
        return $this->guest_player_id !== null;
    }

    public function hasCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    /**
     * Whether this participation can be checked in right now. Check-in is
     * self-service, so a guest contact — who has no account to act with —
     * never checks in; their attendance is only ever the organizer's
     * record.
     */
    public function canCheckIn(): bool
    {
        return $this->status === self::STATUS_CONFIRMED
            && ! $this->isGuest()
            && $this->game->isCheckInOpen();
    }

    /**
     * The registered user or guest contact this participation belongs to,
     * whichever is set.
     */
    public function participant(): User|GuestPlayer
    {
        return $this->isGuest() ? $this->guestPlayer : $this->user;
    }

    public function displayName(): string
    {
        return $this->participant()->name;
    }
}
