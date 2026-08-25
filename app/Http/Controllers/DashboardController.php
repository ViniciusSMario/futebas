<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Invitation;
use App\Models\PlayerProfile;
use App\Models\SosApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the role-specific dashboard for the authenticated user.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->hasRole(User::ROLE_ORGANIZER)) {
            return view('dashboard.organizer', [
                'gamesCreatedCount' => Game::where('user_id', $user->id)->count(),
                'pendingInvitationsSentCount' => Invitation::whereHas('game', fn ($q) => $q->where('user_id', $user->id))
                    ->where('status', 'pending')
                    ->count(),
                'playersNearbyCount' => $user->city
                    ? PlayerProfile::where('city', $user->city)->count()
                    : null,
                // Goalkeepers waiting on a decision — the one number an
                // organizer with a live SOS actually needs to see.
                'pendingSosApplicationsCount' => SosApplication::query()
                    ->where('status', SosApplication::STATUS_PENDING)
                    ->whereHas('sosRequest', fn ($q) => $q->where('organizer_id', $user->id)->live())
                    ->count(),
            ]);
        }

        return view('dashboard.player', [
            'playerProfile' => $user->playerProfile,
            // Matches whose check-in window is open and that the player
            // hasn't confirmed yet — the one thing that's time-sensitive
            // enough to belong on the landing page.
            'checkInPending' => GamePlayer::query()
                ->with('game')
                ->where('user_id', $user->id)
                ->where('status', GamePlayer::STATUS_CONFIRMED)
                ->whereNull('checked_in_at')
                ->whereHas('game', fn ($q) => $q
                    ->where('status', Game::STATUS_OPEN)
                    ->whereDate('date', '>=', today())
                )
                ->get()
                ->filter(fn (GamePlayer $gamePlayer) => $gamePlayer->game->isCheckInOpen())
                ->values(),
            'availabilities' => $user->availabilities()->orderBy('day_of_week')->get(),
            'pendingInvitationsCount' => $user->invitations()->where('status', 'pending')->count(),
            'pendingSosApplicationsCount' => $user->sosApplications()
                ->where('status', SosApplication::STATUS_PENDING)
                ->count(),
        ]);
    }
}
