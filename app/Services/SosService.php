<?php

namespace App\Services;

use App\Exceptions\SosRequestUnavailableException;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\PlayerProfile;
use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Models\User;
use App\Notifications\SosApplicationAccepted;
use App\Notifications\SosApplicationReceived;
use App\Notifications\SosApplicationRejected;
use App\Notifications\SosRequestPublished;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The SOS flow end to end: publish a call, collect competing candidacies,
 * and pick exactly one winner.
 *
 * The competition is what makes this non-trivial. Several goalkeepers can
 * be looking at the same request, and the organizer can be clicking
 * "accept" on two candidates at once from two tabs. Every state change
 * therefore happens inside a transaction that takes a row lock on the
 * SosRequest first — it is the single point of serialization for the
 * whole feature.
 */
class SosService
{
    public function __construct(private readonly GamePlayerService $gamePlayers) {}

    /**
     * Publish an SOS for a match and notify matching goalkeepers.
     *
     * The deadline defaults to kickoff: a call for a goalkeeper is
     * pointless once the match has started.
     */
    public function publish(Game $game, User $organizer, float $offeredValue, ?string $message = null): SosRequest
    {
        $sosRequest = SosRequest::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'position' => SosRequest::POSITION,
            'offered_value' => $offeredValue,
            'message' => $message,
            'status' => SosRequest::STATUS_OPEN,
            'expires_at' => $game->startsAt(),
        ]);

        $sosRequest->setRelation('game', $game);
        $sosRequest->setRelation('organizer', $organizer);

        $recipients = $this->candidatesInRegion($sosRequest);

        Notification::send($recipients, new SosRequestPublished($sosRequest));

        $sosRequest->forceFill(['notified_count' => $recipients->count()])->save();

        return $sosRequest;
    }

    /**
     * Every player who should hear about this SOS: they keep goal, they
     * play the match's modality, and they are in range.
     *
     * "In range" is the match's own city, plus players who declared they
     * travel and live in the organizer's state — a Game records only a
     * city, so the organizer's state is the best available proxy.
     *
     * @return Collection<int, User>
     */
    public function candidatesInRegion(SosRequest $sosRequest): Collection
    {
        $game = $sosRequest->game;
        $organizerState = $sosRequest->organizer?->state ?? $game->user?->state;

        $userIds = PlayerProfile::query()
            ->whereJsonContains('positions', $sosRequest->position)
            ->whereJsonContains('modalities', $game->modality)
            ->where(function ($query) use ($game, $organizerState) {
                $query->where('city', $game->city);

                if (filled($organizerState)) {
                    $query->orWhere(fn ($q) => $q
                        ->where('plays_outside_city', true)
                        ->where('state', $organizerState));
                }
            })
            ->pluck('user_id');

        return User::query()
            ->whereIn('id', $userIds)
            ->where('role', User::ROLE_PLAYER)
            ->whereKeyNot($sosRequest->organizer_id)
            // Someone already in the match has no reason to apply for it.
            ->whereDoesntHave('gamePlayers', fn ($query) => $query
                ->where('game_id', $game->id)
                ->whereNot('status', GamePlayer::STATUS_CANCELLED))
            ->get();
    }

    /**
     * Register (or update) a goalkeeper's candidacy.
     *
     * Candidacies are always pending: the organizer decides, so applying
     * never puts anyone in the match.
     */
    public function apply(SosRequest $sosRequest, User $user, float $askingPrice, ?string $message = null): SosApplication
    {
        // Someone can still reach a call they were not notified about (a
        // shared link, a profile edited since), so the last word on who may
        // answer an SOS lives here rather than in the controller.
        if (! $user->isGoalkeeper()) {
            throw SosRequestUnavailableException::notAGoalkeeper();
        }

        // Publishing skips whoever is already in the match, but they can be
        // added to it after the call went out — so the same rule is
        // enforced here, at the moment it actually matters.
        if ($sosRequest->game?->hasParticipant($user)) {
            throw SosRequestUnavailableException::alreadyInGame();
        }

        $application = DB::transaction(function () use ($sosRequest, $user, $askingPrice, $message) {
            $locked = $this->lockOpenRequest($sosRequest);

            return SosApplication::updateOrCreate(
                ['sos_request_id' => $locked->id, 'user_id' => $user->id],
                [
                    'asking_price' => $askingPrice,
                    'message' => $message,
                    'status' => SosApplication::STATUS_PENDING,
                    'responded_at' => null,
                ],
            );
        });

        $sosRequest->organizer->notify(new SosApplicationReceived($application));

        return $application;
    }

    /**
     * The goalkeeper pulls out before a decision is made.
     */
    public function withdraw(SosApplication $application): SosApplication
    {
        $application->update([
            'status' => SosApplication::STATUS_WITHDRAWN,
            'responded_at' => now(),
        ]);

        return $application;
    }

    /**
     * Award the SOS to one candidate.
     *
     * Everything that decides the winner happens under the request's row
     * lock, so two simultaneous accepts cannot both succeed: the second one
     * finds the request already filled and is turned away.
     */
    public function accept(SosApplication $application): SosApplication
    {
        [$accepted, $rejected] = DB::transaction(function () use ($application) {
            $sosRequest = $this->lockOpenRequest($application->sosRequest);

            $accepted = SosApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! $accepted->isPending()) {
                throw SosRequestUnavailableException::applicationNotPending();
            }

            $accepted->update([
                'status' => SosApplication::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);

            $rejected = SosApplication::query()
                ->where('sos_request_id', $sosRequest->id)
                ->whereKeyNot($accepted->id)
                ->where('status', SosApplication::STATUS_PENDING)
                ->get();

            SosApplication::whereIn('id', $rejected->modelKeys())->update([
                'status' => SosApplication::STATUS_REJECTED,
                'responded_at' => now(),
            ]);

            $sosRequest->setRelation('game', $application->sosRequest->game);

            $this->addToGame($sosRequest, $accepted);

            $sosRequest->forceFill([
                'status' => SosRequest::STATUS_FILLED,
                'accepted_application_id' => $accepted->id,
            ])->save();

            return [$accepted, $rejected];
        });

        $accepted->user->notify(new SosApplicationAccepted($accepted));

        foreach ($rejected as $loser) {
            $loser->user->notify(new SosApplicationRejected($loser));
        }

        return $accepted;
    }

    /**
     * Turn down a single candidate while keeping the SOS open for others.
     */
    public function reject(SosApplication $application): SosApplication
    {
        $rejected = DB::transaction(function () use ($application) {
            $locked = SosApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw SosRequestUnavailableException::applicationNotPending();
            }

            $locked->update([
                'status' => SosApplication::STATUS_REJECTED,
                'responded_at' => now(),
            ]);

            return $locked;
        });

        $rejected->user->notify(new SosApplicationRejected($rejected));

        return $rejected;
    }

    /**
     * Call the whole thing off; every candidate still waiting is told.
     */
    public function cancel(SosRequest $sosRequest): SosRequest
    {
        $pending = DB::transaction(function () use ($sosRequest) {
            $locked = $this->lockOpenRequest($sosRequest);

            $pending = $locked->applications()->where('status', SosApplication::STATUS_PENDING)->get();

            SosApplication::whereIn('id', $pending->modelKeys())->update([
                'status' => SosApplication::STATUS_REJECTED,
                'responded_at' => now(),
            ]);

            $locked->forceFill(['status' => SosRequest::STATUS_CANCELLED])->save();

            return $pending;
        });

        $sosRequest->status = SosRequest::STATUS_CANCELLED;

        foreach ($pending as $application) {
            $application->user->notify(new SosApplicationRejected($application));
        }

        return $sosRequest;
    }

    /**
     * Re-read the request FOR UPDATE and assert it can still be acted on.
     * Callers must already be inside a transaction.
     */
    private function lockOpenRequest(SosRequest $sosRequest): SosRequest
    {
        $locked = SosRequest::whereKey($sosRequest->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isOpen()) {
            throw SosRequestUnavailableException::notOpen();
        }

        return $locked;
    }

    /**
     * Put the winning goalkeeper in the match.
     *
     * The join itself goes through {@see GamePlayerService} so the usual
     * bookkeeping happens, then two SOS-specific facts are applied on top:
     * the organizer's explicit pick outranks the approval and capacity
     * rules, and an SOS goalkeeper is paid by the organizer rather than
     * charged the match fee.
     */
    private function addToGame(SosRequest $sosRequest, SosApplication $application): GamePlayer
    {
        $gamePlayer = $this->gamePlayers->join($sosRequest->game, $application->user, bypassApproval: true);

        $gamePlayer->update([
            'status' => GamePlayer::STATUS_CONFIRMED,
            'amount_due' => 0,
        ]);

        return $gamePlayer;
    }
}
