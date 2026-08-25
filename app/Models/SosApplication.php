<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A goalkeeper's candidacy for an `SosRequest`.
 *
 * Candidacies always start as `pending`: an SOS is a competition, so nobody
 * is added to the match until the organizer explicitly accepts one of them.
 */
#[Fillable(['sos_request_id', 'user_id', 'asking_price', 'message', 'status', 'responded_at'])]
class SosApplication extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN];

    protected function casts(): array
    {
        return [
            'asking_price' => 'decimal:2',
            'responded_at' => 'datetime',
        ];
    }

    public function sosRequest(): BelongsTo
    {
        return $this->belongsTo(SosRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** The candidate's sports profile, used to show price and ratings. */
    public function playerProfile(): BelongsTo
    {
        return $this->belongsTo(PlayerProfile::class, 'user_id', 'user_id');
    }
}
