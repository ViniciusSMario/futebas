<?php

namespace App\Models;

use App\Services\WebPush\P256;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One browser (or installed PWA) that agreed to receive push messages.
 *
 * A single user commonly has several: phone, desktop, a reinstalled app.
 * Keys arrive base64url-encoded from the Push API and are stored that way.
 */
#[Fillable(['user_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'user_agent'])]
class PushSubscription extends Model
{
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The endpoint is a long URL, so the unique index lives on its hash.
        static::saving(function (PushSubscription $subscription) {
            $subscription->endpoint_hash = hash('sha256', $subscription->endpoint);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The subscription's P-256 public key as raw bytes. */
    public function rawPublicKey(): string
    {
        return P256::base64UrlDecode($this->public_key);
    }

    /** The subscription's 16-byte auth secret as raw bytes. */
    public function rawAuthToken(): string
    {
        return P256::base64UrlDecode($this->auth_token);
    }
}
