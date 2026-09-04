<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An open call for a paid substitute — in practice, a goalkeeper — for a
 * specific match.
 *
 * Unlike an `Invitation`, which targets one player the organizer picked, an
 * SOS is broadcast to every matching player in the region and collects
 * competing candidacies (`SosApplication`). The organizer then chooses one,
 * comparing asking price, city and ratings.
 */
#[Fillable(['game_id', 'organizer_id', 'position', 'offered_value', 'message', 'status', 'expires_at'])]
class SosRequest extends Model
{
    /**
     * "SOS Goleiro" is a goalkeeper feature, full stop — the position is
     * never chosen by the organizer. It stays a column because both sides
     * of the flow query on it (which players to notify, which calls a
     * player may answer), not because it can vary.
     */
    public const POSITION = 'Goleiro';

    public const STATUS_OPEN = 'open';

    public const STATUS_FILLED = 'filled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_FILLED, self::STATUS_CANCELLED];

    protected function casts(): array
    {
        return [
            'offered_value' => 'decimal:2',
            'notified_count' => 'integer',
            'expires_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(SosApplication::class);
    }

    public function acceptedApplication(): BelongsTo
    {
        return $this->belongsTo(SosApplication::class, 'accepted_application_id');
    }

    /**
     * Still accepting candidacies: not filled, not cancelled, not past its
     * deadline, and the match itself still on.
     */
    public function isOpen(): bool
    {
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->game === null || $this->game->isOpen();
    }

    public function isFilled(): bool
    {
        return $this->status === self::STATUS_FILLED;
    }

    /**
     * O prazo passou sem ninguém decidir nada.
     *
     * Diferente de `! isOpen()`, que também é verdade para chamada
     * preenchida ou cancelada: aqui é especificamente o caso em que a
     * chamada morreu de velha, que é o único que não avisa ninguém
     * sozinho.
     */
    public function hasExpired(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Expired requests keep the `open` status in the database — the column
     * only changes on an explicit decision — so "still live" is a query on
     * both status and deadline.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function pendingApplicationsCount(): int
    {
        if ($this->relationLoaded('applications')) {
            return $this->applications->where('status', SosApplication::STATUS_PENDING)->count();
        }

        return $this->applications()->where('status', SosApplication::STATUS_PENDING)->count();
    }
}
