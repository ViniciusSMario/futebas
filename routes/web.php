<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GamePlayerController;
use App\Http\Controllers\GameSeriesController;
use App\Http\Controllers\GameTeamController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerProfileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicGameController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SosController;
use App\Http\Controllers\SosOpportunityController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public game link — no authentication required, so a Game's organizer can
// share it with anyone (WhatsApp, Instagram, etc).
Route::get('/g/{game:slug}', [PublicGameController::class, 'show'])->name('public-games.show');
Route::get('/g/{game:slug}/participar', [PublicGameController::class, 'join'])->name('public-games.join');
Route::get('/g/{game:slug}/entrar', [PublicGameController::class, 'redirectToLogin'])->name('public-games.login');
Route::post('/g/{game:slug}/participar-sem-cadastro', [PublicGameController::class, 'joinAsGuest'])->name('public-games.join-guest');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Push notifications are per-device and role-agnostic: the service
    // worker registers on any authenticated page.
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    Route::post('/push-subscriptions/teste', [PushSubscriptionController::class, 'test'])->name('push-subscriptions.test');

    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notificacoes/marcar-lidas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notificacoes/{notification}', [NotificationController::class, 'show'])->name('notifications.show');

    Route::get('/games/search', [GameController::class, 'index'])->name('games.search');
    Route::get('/games/mine', [GameController::class, 'mine'])->name('games.mine');
    Route::delete('/games/{game}/participar', [GamePlayerController::class, 'leave'])->name('games.leave');

    // Match-day presence, declared by the participants themselves — an
    // organizer who also plays checks in the same way, so this stays out
    // of the role groups.
    Route::post('/games/{game}/presenca', [GamePlayerController::class, 'checkIn'])->name('games.check-in');
    Route::delete('/games/{game}/presenca', [GamePlayerController::class, 'undoCheckIn'])->name('games.check-in.undo');

    // Player-only: sports profile, availability, invitations received.
    Route::middleware('role:player')->group(function () {
        Route::get('/player-profile', [PlayerProfileController::class, 'edit'])->name('player-profile.edit');
        Route::put('/player-profile', [PlayerProfileController::class, 'update'])->name('player-profile.update');

        Route::get('/availability', [AvailabilityController::class, 'edit'])->name('availability.edit');
        Route::put('/availability', [AvailabilityController::class, 'update'])->name('availability.update');

        Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::patch('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
        Route::patch('/invitations/{invitation}/decline', [InvitationController::class, 'decline'])->name('invitations.decline');
        Route::get('/ratings/{user}', [RatingController::class, 'show'])->name('ratings.show');

        // SOS seen from the goalkeeper's side: the calls they were notified
        // about, and their (always pending) candidacies for them.
        Route::get('/sos/oportunidades', [SosOpportunityController::class, 'index'])->name('sos-opportunities.index');
        Route::get('/sos/oportunidades/{sosRequest}', [SosOpportunityController::class, 'show'])->name('sos-opportunities.show');
        Route::post('/sos/oportunidades/{sosRequest}/candidatar', [SosOpportunityController::class, 'store'])->name('sos-opportunities.apply');
        Route::delete('/sos/oportunidades/{sosRequest}/candidatar', [SosOpportunityController::class, 'withdraw'])->name('sos-opportunities.withdraw');
    });

    // Organizer-only: searching players, sending invitations, SOS, creating games.
    Route::middleware('role:organizer')->group(function () {
        Route::get('/players/search', [PlayerController::class, 'index'])->name('players.search');
        Route::get('/invitations/create/{playerProfile}', [InvitationController::class, 'create'])->name('invitations.create');
        Route::post('/invitations/{playerProfile}', [InvitationController::class, 'store'])->name('invitations.store');
        Route::get('/games/create', [GameController::class, 'create'])->name('games.create');
        Route::post('/games', [GameController::class, 'store'])->name('games.store');
        Route::patch('/games/{game}/finish', [GameController::class, 'finish'])->name('games.finish');

        Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
        Route::get('/games/{game}/editar', [GameController::class, 'edit'])->name('games.edit');
        Route::patch('/games/{game}', [GameController::class, 'update'])->name('games.update');
        Route::patch('/games/{game}/cancelar', [GameController::class, 'cancel'])->name('games.cancel');

        Route::get('/games/{game}/participantes/adicionar', [GamePlayerController::class, 'create'])->name('game-players.create');
        Route::post('/games/{game}/participantes', [GamePlayerController::class, 'store'])->name('game-players.store');
        Route::patch('/games/{game}/participantes/{gamePlayer}/confirmar', [GamePlayerController::class, 'confirm'])->name('game-players.confirm');
        Route::patch('/games/{game}/participantes/{gamePlayer}/pagamento', [GamePlayerController::class, 'updatePayment'])->name('game-players.payment');
        Route::patch('/games/{game}/participantes/{gamePlayer}/falta', [GamePlayerController::class, 'toggleNoShow'])->name('game-players.no-show');
        Route::delete('/games/{game}/participantes/{gamePlayer}', [GamePlayerController::class, 'destroy'])->name('game-players.destroy');

        Route::get('/games/{game}/convites/buscar', [InvitationController::class, 'searchForGame'])->name('games.invitations.search');
        Route::post('/games/{game}/convites/{playerProfile}', [InvitationController::class, 'storeForGame'])->name('games.invitations.store');

        Route::post('/games/{game}/times/sortear', [GameTeamController::class, 'draw'])->name('game-teams.draw');

        // Weekly peladas: the recurring series and its regulars.
        Route::get('/peladas', [GameSeriesController::class, 'index'])->name('game-series.index');
        Route::get('/peladas/nova', [GameSeriesController::class, 'create'])->name('game-series.create');
        Route::post('/peladas', [GameSeriesController::class, 'store'])->name('game-series.store');
        Route::get('/peladas/{gameSeries}', [GameSeriesController::class, 'show'])->name('game-series.show');
        Route::patch('/peladas/{gameSeries}/encerrar', [GameSeriesController::class, 'end'])->name('game-series.end');
        Route::post('/peladas/{gameSeries}/mensalistas', [GameSeriesController::class, 'storeMember'])->name('game-series.members.store');
        Route::delete('/peladas/{gameSeries}/mensalistas/{member}', [GameSeriesController::class, 'destroyMember'])->name('game-series.members.destroy');

        Route::get('/sos', [SosController::class, 'index'])->name('sos.index');
        Route::get('/sos/novo', [SosController::class, 'create'])->name('sos.create');
        Route::post('/sos', [SosController::class, 'store'])->name('sos.store');
        Route::get('/sos/{sosRequest}', [SosController::class, 'show'])->name('sos.show');
        Route::patch('/sos/{sosRequest}/cancelar', [SosController::class, 'cancel'])->name('sos.cancel');
        Route::patch('/sos/{sosRequest}/candidaturas/{application}/aceitar', [SosController::class, 'accept'])->name('sos.accept');
        Route::patch('/sos/{sosRequest}/candidaturas/{application}/recusar', [SosController::class, 'reject'])->name('sos.reject');

        Route::get('/games/{game}/ratings', [RatingController::class, 'index'])->name('ratings.index');
        Route::get('/games/{game}/ratings/{player}/create', [RatingController::class, 'create'])->name('ratings.create');
        Route::post('/games/{game}/ratings/{player}', [RatingController::class, 'store'])->name('ratings.store');
    });

    // Shared: viewing a specific public profile (e.g. an organizer's search result,
    // or a player previewing their own profile) isn't role-exclusive.
    Route::get('/players/{playerProfile}', [PlayerController::class, 'show'])->name('players.show');
});

require __DIR__.'/auth.php';
