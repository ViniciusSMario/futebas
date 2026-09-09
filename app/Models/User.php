<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Feature;
use App\Enums\Plan;
use App\Services\PlanService;
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
        // Mesma razão do papel: a conta já nasce sabendo em que plano
        // está, sem depender de reler o padrão do banco.
        'plan' => Plan::FREE->value,
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
     * O plano que vale para este usuário agora.
     *
     * Pergunta à assinatura, não à coluna `plan`: só ela sabe se o período
     * pago ainda está de pé. A coluna é uma cópia para a busca ordenar, e
     * uma cópia atrasada nunca pode liberar um recurso.
     */
    public function currentPlan(): Plan
    {
        return $this->subscription?->effectivePlan() ?? Plan::default();
    }

    /** Está neste plano ou em um acima dele? */
    public function onPlan(Plan $plan): bool
    {
        return $this->currentPlan()->covers($plan);
    }

    /**
     * O plano libera este recurso? Vale para os recursos booleanos; os que
     * têm teto mensal passam pelo {@see PlanService}, que
     * precisa contar o uso do mês para responder.
     */
    public function planAllows(Feature $feature): bool
    {
        return $this->currentPlan()->allows($feature);
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
            'plan' => Plan::class,
        ];
    }

    /** A assinatura desta conta, se ela já teve alguma. */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
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
