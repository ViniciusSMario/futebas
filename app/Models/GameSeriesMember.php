<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A regular in a weekly pelada. Like {@see GamePlayer}, this is either a
 * registered user or a guest contact, never both.
 */
#[Fillable(['game_series_id', 'user_id', 'guest_player_id'])]
class GameSeriesMember extends Model
{
    public function gameSeries(): BelongsTo
    {
        return $this->belongsTo(GameSeries::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guestPlayer(): BelongsTo
    {
        return $this->belongsTo(GuestPlayer::class);
    }

    public function isGuest(): bool
    {
        return $this->guest_player_id !== null;
    }

    public function participant(): User|GuestPlayer
    {
        return $this->isGuest() ? $this->guestPlayer : $this->user;
    }

    public function displayName(): string
    {
        return $this->participant()->name;
    }
}
