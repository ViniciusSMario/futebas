<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A weekly pelada: the template its occurrences are stamped from, plus
 * the regulars ("mensalistas") who are added to each one.
 */
#[Fillable([
    'user_id',
    'team_name',
    'location',
    'city',
    'state',
    'modality',
    'day_of_week',
    'start_time',
    'end_time',
    'max_players',
    'price',
    'positions',
    'description',
    'requires_approval',
    'status',
])]
class GameSeries extends Model
{
    /** Laravel would otherwise guess "game_seri". */
    protected $table = 'game_series';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_ENDED];

    /** How far ahead occurrences are kept generated. */
    public const WEEKS_AHEAD = 4;

    public const DAYS_OF_WEEK = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'max_players' => 'integer',
            'price' => 'decimal:2',
            'positions' => 'array',
            'requires_approval' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(GameSeriesMember::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function dayName(): string
    {
        return self::DAYS_OF_WEEK[$this->day_of_week] ?? '';
    }

    /**
     * The dates this pelada falls on over the next WEEKS_AHEAD weeks,
     * today included when it's the right weekday and kickoff hasn't
     * passed — no point scheduling a match that already started.
     *
     * @return Collection<int, Carbon>
     */
    public function upcomingDates(): Collection
    {
        $lastDay = self::WEEKS_AHEAD * 7;

        return collect(range(0, $lastDay))
            ->map(fn (int $offset) => today()->addDays($offset))
            ->filter(fn (Carbon $date) => $date->dayOfWeek === $this->day_of_week)
            ->reject(fn (Carbon $date) => $date->isToday() && now()->greaterThan($this->kickoffOn($date)))
            ->values();
    }

    /**
     * This pelada's start time placed on a given date.
     */
    public function kickoffOn(Carbon $date): Carbon
    {
        return $date->copy()->setTimeFromTimeString($this->start_time->format('H:i:s'));
    }

    /**
     * The template values a Game occurrence is created from.
     *
     * @return array<string, mixed>
     */
    public function occurrenceAttributes(Carbon $date): array
    {
        return [
            'user_id' => $this->user_id,
            'game_series_id' => $this->id,
            'team_name' => $this->team_name,
            'location' => $this->location,
            'city' => $this->city,
            'state' => $this->state,
            'modality' => $this->modality,
            'date' => $date->format('Y-m-d'),
            'start_time' => $this->start_time->format('H:i'),
            'end_time' => $this->end_time?->format('H:i'),
            'max_players' => $this->max_players,
            'price' => $this->price,
            'positions' => $this->positions ?? [],
            'description' => $this->description,
            'requires_approval' => $this->requires_approval,
            'status' => Game::STATUS_OPEN,
        ];
    }
}
