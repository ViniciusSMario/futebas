<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'photo_path',
    'birth_date',
    'state',
    'city',
    'phone',
    'positions',
    'modalities',
    'level',
    'price_per_game',
    'plays_outside_city',
    'price_per_game_outside',
    'average_rating',
    'average_punctuality',
    'average_behavior',
    'average_performance',
    'ratings_count',
    'games_played',
    'no_shows',
    'cancellations',
    'attendance_rate',
])]
class PlayerProfile extends Model
{
    public const POSITIONS = [
        'Goleiro',
        'Zagueiro',
        'Lateral',
        'Volante',
        'Meia',
        'Atacante',
    ];

    public const MODALITIES = [
        'Society',
        'Futsal',
        'Campo',
        'Outros',
    ];

    public const LEVELS = [
        'Iniciante',
        'Recreativo',
        'Intermediário',
        'Avançado',
    ];

    /**
     * Strength attributed to a player who has no ratings yet, so a newcomer
     * still has a place on the same 1-99 scale as everyone else.
     */
    public const LEVEL_SCORES = [
        'Iniciante' => 62,
        'Recreativo' => 72,
        'Intermediário' => 80,
        'Avançado' => 88,
    ];

    /** Where a player with neither ratings nor a declared level sits. */
    public const DEFAULT_SCORE = 72;

    public const STATES = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'positions' => 'array',
            'modalities' => 'array',
            'price_per_game' => 'decimal:2',
            'plays_outside_city' => 'boolean',
            'price_per_game_outside' => 'decimal:2',
            'average_rating' => 'decimal:2',
            'average_punctuality' => 'decimal:2',
            'average_behavior' => 'decimal:2',
            'average_performance' => 'decimal:2',
            'ratings_count' => 'integer',
            'games_played' => 'integer',
            'no_shows' => 'integer',
            'cancellations' => 'integer',
            'attendance_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The single 1-99 number this app uses to say how strong a player is.
     *
     * Ratings win once they exist, because they are other people's
     * judgement rather than the player's own; the declared level stands in
     * until then. It is the number printed on the player card, and the one
     * the balanced team draw sorts on — the two must agree, or a draw the
     * organizer can see is unbalanced would call itself balanced.
     */
    public function overallScore(): int
    {
        if ($this->ratings_count > 0) {
            return max(1, min(99, (int) round((float) $this->average_rating * 20)));
        }

        return self::LEVEL_SCORES[$this->level] ?? self::DEFAULT_SCORE;
    }

    /**
     * Whether this player keeps goal — the gate for the whole "SOS
     * Goleiro" surface, which is invisible to everyone else.
     */
    public function isGoalkeeper(): bool
    {
        return in_array(SosRequest::POSITION, $this->positions ?? [], strict: true);
    }

    /**
     * Ratings received by this player, matched through the shared user_id
     * rather than the profile's own id.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class, 'user_id', 'user_id');
    }

    /**
     * Recompute this player's rating averages from all ratings received.
     */
    public function recalculateRatingAverages(): void
    {
        $aggregate = $this->ratings()->selectRaw(<<<'SQL'
            count(*) as count,
            avg(overall_rating) as avg_overall,
            avg(punctuality_rating) as avg_punctuality,
            avg(behavior_rating) as avg_behavior,
            avg(performance_rating) as avg_performance
        SQL)->first();

        $this->update([
            'ratings_count' => $aggregate->count,
            'average_rating' => $aggregate->avg_overall,
            'average_punctuality' => $aggregate->avg_punctuality,
            'average_behavior' => $aggregate->avg_behavior,
            'average_performance' => $aggregate->avg_performance,
        ]);
    }

    /**
     * Recompute this player's attendance record.
     *
     * Only finished matches count — the same universe ratings live in, and
     * the same limitation: a match the organizer never marked as finished
     * is not yet history to this app.
     *
     * A cancellation is deliberately not counted as an absence. Pulling out
     * a day ahead, which the 24h cutoff forces, is what an organizer wants
     * players to do; the number that damages a reputation is the no-show.
     */
    public function recalculateAttendanceStats(): void
    {
        $aggregate = GamePlayer::query()
            ->join('games', 'games.id', '=', 'game_players.game_id')
            ->where('game_players.user_id', $this->user_id)
            ->where('games.status', Game::STATUS_FINISHED)
            ->selectRaw('sum(case when game_players.status = ? and game_players.no_show = 0 then 1 else 0 end) as played', [GamePlayer::STATUS_CONFIRMED])
            ->selectRaw('sum(case when game_players.status = ? and game_players.no_show = 1 then 1 else 0 end) as absences', [GamePlayer::STATUS_CONFIRMED])
            ->selectRaw('sum(case when game_players.status = ? and game_players.cancelled_at is not null then 1 else 0 end) as cancellations', [GamePlayer::STATUS_CANCELLED])
            ->first();

        $played = (int) $aggregate->played;
        $absences = (int) $aggregate->absences;
        $commitments = $played + $absences;

        $this->update([
            'games_played' => $played,
            'no_shows' => $absences,
            'cancellations' => (int) $aggregate->cancellations,
            'attendance_rate' => $commitments > 0 ? round($played / $commitments * 100, 2) : null,
        ]);
    }

    /** Whether there is any attendance record to show yet. */
    public function hasAttendanceHistory(): bool
    {
        return $this->attendance_rate !== null || $this->cancellations > 0;
    }
}
