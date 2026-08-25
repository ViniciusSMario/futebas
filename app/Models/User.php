<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'city', 'state', 'photo_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_PLAYER = 'player';

    public const ROLE_ORGANIZER = 'organizer';

    public const ROLES = [self::ROLE_PLAYER, self::ROLE_ORGANIZER];

    protected $attributes = [
        'role' => self::ROLE_PLAYER,
    ];

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Whether this user is a player who keeps goal. Gates the "SOS
     * Goleiro" navigation and pages, which exist only for goalkeepers.
     */
    public function isGoalkeeper(): bool
    {
        return $this->hasRole(self::ROLE_PLAYER)
            && (bool) $this->playerProfile?->isGoalkeeper();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function playerProfile(): HasOne
    {
        return $this->hasOne(PlayerProfile::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    /** Browsers/devices this user opted into push notifications on. */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** SOS candidacies this user (a goalkeeper) has sent. */
    public function sosApplications(): HasMany
    {
        return $this->hasMany(SosApplication::class);
    }

    /** SOS requests this user (an organizer) has published. */
    public function sosRequests(): HasMany
    {
        return $this->hasMany(SosRequest::class, 'organizer_id');
    }

    /**
     * Store (or refresh) a browser push subscription. The endpoint is the
     * identity of a device, so re-subscribing on the same browser updates
     * the existing row instead of piling up duplicates.
     */
    public function updatePushSubscription(string $endpoint, string $publicKey, string $authToken, ?string $userAgent = null): PushSubscription
    {
        $subscription = PushSubscription::firstOrNew(['endpoint_hash' => hash('sha256', $endpoint)]);

        $subscription->fill([
            'user_id' => $this->id,
            'endpoint' => $endpoint,
            'public_key' => $publicKey,
            'auth_token' => $authToken,
            'user_agent' => $userAgent,
        ])->save();

        return $subscription;
    }
}
