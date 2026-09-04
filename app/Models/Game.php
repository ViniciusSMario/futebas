<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'game_series_id', 'team_name', 'location', 'city', 'state', 'modality', 'date', 'start_time', 'end_time', 'max_players', 'price', 'positions', 'description', 'requires_approval', 'status'])]
class Game extends Model
{
    public const POSITIONS = PlayerProfile::POSITIONS;

    public const MODALITIES = PlayerProfile::MODALITIES;

    public const STATUS_OPEN = 'open';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_FINISHED, self::STATUS_CANCELLED];

    /**
     * How long before kickoff participants may confirm they're coming.
     * Twelve hours keeps it a same-day gesture: for a 19h match it opens
     * at 7h that morning.
     */
    public const CHECK_IN_OPENS_HOURS_BEFORE = 12;

    /**
     * Folga entre o fim previsto e a finalização automática.
     *
     * Existe por dois motivos concretos. Partida atrasa, e fechar no
     * minuto do fim previsto pegaria gente ainda em campo. E `finishesAt()`
     * cai para o horário de *início* quando o organizador não informou o
     * término — sem folga, essas partidas seriam finalizadas no apito
     * inicial. Três horas cobrem a pelada mais longa com sobra.
     */
    public const AUTO_FINISH_GRACE_HOURS = 3;

    /**
     * Quantas horas antes do início cada lembrete sai.
     *
     * O da véspera casa de propósito com o corte de
     * {@see self::isCancellableByPlayer()}: quem for desistir ainda
     * consegue liberar a vaga a tempo de alguém pegar.
     */
    public const REMINDER_EARLY_HOURS = 24;

    public const REMINDER_LATE_HOURS = 2;

    protected static function booted(): void
    {
        static::creating(function (Game $game) {
            if (blank($game->slug)) {
                do {
                    $slug = Str::lower(Str::random(8));
                } while (DB::table('games')->where('slug', $slug)->exists());

                $game->slug = $slug;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'max_players' => 'integer',
            'price' => 'decimal:2',
            'positions' => 'array',
            'requires_approval' => 'boolean',
            'reminded_24h_at' => 'datetime',
            'reminded_2h_at' => 'datetime',
        ];
    }

    /**
     * Restrict to matches that haven't started yet: any future date, or
     * today's matches whose start time is still ahead. `date` is stored
     * with a time component and `start_time` as a bare time, so both
     * comparisons go through whereDate/whereTime to stay portable between
     * MySQL (dev) and SQLite (tests).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereDate('date', '>', today())
                ->orWhere(fn (Builder $query) => $query
                    ->whereDate('date', today())
                    ->whereTime('start_time', '>=', now())
                );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The weekly pelada this match is an occurrence of, if any. */
    public function gameSeries(): BelongsTo
    {
        return $this->belongsTo(GameSeries::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function gameTeams(): HasMany
    {
        return $this->hasMany(GameTeam::class);
    }

    /**
     * Registered users taking part in this match — the audience for
     * anything that changes about it. Guest contacts have no account to
     * notify, and the organizer is excluded since they're the one making
     * the changes.
     *
     * @return EloquentCollection<int, User>
     */
    public function notifiableParticipants(): EloquentCollection
    {
        $userIds = $this->gamePlayers()
            ->whereNotNull('user_id')
            ->where('status', '!=', GamePlayer::STATUS_CANCELLED)
            ->where('user_id', '!=', $this->user_id)
            ->pluck('user_id');

        return User::query()->whereIn('id', $userIds)->get();
    }

    /**
     * Quem deve ser lembrado da partida: os confirmados com conta, mais o
     * organizador.
     *
     * O organizador entra aqui — e é excluído de
     * {@see self::notifiableParticipants()} — porque a regra de lá é "não
     * avise ninguém sobre a própria ação". Um lembrete não é ação de
     * ninguém: é o relógio, e o dono da pelada também esquece dela.
     *
     * Só confirmados: para quem está na lista de espera ou aguardando
     * aprovação, "sua partida é amanhã" seria mentira.
     *
     * @return EloquentCollection<int, User>
     */
    public function reminderRecipients(): EloquentCollection
    {
        $userIds = $this->gamePlayers()
            ->whereNotNull('user_id')
            ->where('status', GamePlayer::STATUS_CONFIRMED)
            ->pluck('user_id')
            ->push($this->user_id)
            ->unique();

        return User::query()->whereIn('id', $userIds)->get();
    }

    /**
     * Whether this user already has a live participation in the match —
     * confirmed, awaiting approval, or on the waiting list. Someone who is
     * already in has nothing to be invited or called out to.
     */
    public function hasParticipant(User $user): bool
    {
        return $this->gamePlayers()
            ->where('user_id', $user->id)
            ->whereNot('status', GamePlayer::STATUS_CANCELLED)
            ->exists();
    }

    /**
     * Recompute the attendance record of every registered participant,
     * after something about this match changed what it says about them.
     * Guests have no profile to keep a record on.
     */
    public function refreshParticipantStats(): void
    {
        $this->gamePlayers()
            ->whereNotNull('user_id')
            ->with('user.playerProfile')
            ->get()
            ->each(fn (GamePlayer $gamePlayer) => $gamePlayer->user?->playerProfile?->recalculateAttendanceStats());
    }

    /** Paid last-minute calls published for this match ("SOS Goleiro"). */
    public function sosRequests(): HasMany
    {
        return $this->hasMany(SosRequest::class);
    }

    /**
     * Number of players currently confirmed for this game, using a
     * withCount() aggregate or an already-loaded `gamePlayers` relation
     * when available to avoid N+1 queries in listings.
     */
    public function confirmedPlayersCount(): int
    {
        if (array_key_exists('confirmed_players_count', $this->attributes)) {
            return (int) $this->attributes['confirmed_players_count'];
        }

        if ($this->relationLoaded('gamePlayers')) {
            return $this->gamePlayers->where('status', GamePlayer::STATUS_CONFIRMED)->count();
        }

        return $this->gamePlayers()->where('status', GamePlayer::STATUS_CONFIRMED)->count();
    }

    public function spotsRemaining(): int
    {
        return max(0, $this->max_players - $this->confirmedPlayersCount());
    }

    public function isFull(): bool
    {
        return $this->spotsRemaining() === 0;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Aggregate financial summary for confirmed participants: how much is
     * expected in total, how much has been marked as paid, and how much is
     * still pending. Computed with a single aggregate query.
     *
     * @return array{expected: float, received: float, pending: float}
     */
    public function financialSummary(): array
    {
        $totals = $this->gamePlayers()
            ->where('status', GamePlayer::STATUS_CONFIRMED)
            ->selectRaw('COALESCE(SUM(amount_due), 0) as expected')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount_due ELSE 0 END), 0) as received")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN amount_due ELSE 0 END), 0) as pending")
            ->first();

        return [
            'expected' => (float) $totals->expected,
            'received' => (float) $totals->received,
            'pending' => (float) $totals->pending,
        ];
    }

    /**
     * Whether the organizer has explicitly finished this match. Only
     * finished matches are eligible for ratings.
     */
    public function hasEnded(): bool
    {
        return $this->status === 'finished';
    }

    /**
     * The moment this match is scheduled to end, combining its date with
     * the end time (or the start time, if no end time was given).
     */
    public function finishesAt(): Carbon
    {
        $time = $this->end_time ?? $this->start_time;

        return $this->date->copy()->setTimeFromTimeString($time->format('H:i:s'));
    }

    /**
     * The moment this match is scheduled to start, combining its date with
     * the start time.
     */
    public function startsAt(): Carbon
    {
        return $this->date->copy()->setTimeFromTimeString($this->start_time->format('H:i:s'));
    }

    /**
     * Whether a confirmed player may still cancel their own participation:
     * only allowed until 24 hours before the match starts.
     */
    public function isCancellableByPlayer(): bool
    {
        return now()->addHours(24)->lessThanOrEqualTo($this->startsAt());
    }

    /**
     * The moment participants may start confirming their presence.
     */
    public function checkInOpensAt(): Carbon
    {
        return $this->startsAt()->subHours(self::CHECK_IN_OPENS_HOURS_BEFORE);
    }

    /**
     * Whether confirmed participants can check in right now. The window
     * closes at kickoff rather than at some earlier deadline: locking it
     * sooner would brand as absent whoever simply confirmed late, and the
     * organizer already sees the list filling up in real time.
     */
    public function isCheckInOpen(): bool
    {
        return $this->isOpen() && now()->between($this->checkInOpensAt(), $this->startsAt());
    }

    /**
     * Whether the check-in window has opened at all — the point from which
     * "hasn't confirmed yet" starts meaning something to the organizer.
     */
    public function hasCheckInStarted(): bool
    {
        return now()->greaterThanOrEqualTo($this->checkInOpensAt());
    }

    /**
     * Whether the organizer may mark this open match as finished: its
     * scheduled end time must have already passed.
     */
    public function isEligibleToFinish(): bool
    {
        return $this->status === 'open' && now()->greaterThanOrEqualTo($this->finishesAt());
    }

    /**
     * Close the match: the one operation, wherever the decision came from.
     *
     * Lives on the model because there are now two callers — the organizer
     * pressing "finalizar" and the `games:finish` routine — and a match
     * finished by the clock has to become exactly the same thing as one
     * finished by hand. Settling attendance here is the point: a match only
     * enters anyone's record once it is finished.
     */
    public function finish(): void
    {
        $this->update(['status' => self::STATUS_FINISHED]);

        $this->refreshParticipantStats();
    }

    /**
     * Whether this match may be closed *without anyone asking* — the
     * scheduled end plus {@see self::AUTO_FINISH_GRACE_HOURS}.
     *
     * Deliberately stricter than {@see self::isEligibleToFinish()}: the
     * organizer pressing "finalizar" knows the match is over, while the
     * clock only suspects it.
     */
    public function isEligibleToAutoFinish(): bool
    {
        return $this->status === self::STATUS_OPEN
            && now()->greaterThanOrEqualTo($this->finishesAt()->addHours(self::AUTO_FINISH_GRACE_HOURS));
    }

    /**
     * Matches the auto-finish routine needs to look at.
     *
     * Coarse on purpose: the exact moment is a date column plus a time
     * column, and MySQL and SQLite spell that sum differently, so the cut
     * is made in PHP by `isEligibleToAutoFinish()`. This only narrows the
     * candidates to open matches that could already be over.
     *
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeAwaitingAutoFinish(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN)
            ->whereDate('date', '<=', now()->toDateString());
    }
}
