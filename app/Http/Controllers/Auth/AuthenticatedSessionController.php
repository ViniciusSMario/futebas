<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Game;
use App\Services\GamePlayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($redirect = $this->redirectToIntendedGame($request)) {
            return $redirect;
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * If the user arrived here from a public game link ("Quero Participar"
     * → "já tenho conta"), join them to that game now and send them back
     * to it instead of the intended URL, so the intent to join isn't lost.
     */
    private function redirectToIntendedGame(Request $request): ?RedirectResponse
    {
        $slug = session()->pull('intended_game_slug');

        if (! $slug) {
            return null;
        }

        $game = Game::where('slug', $slug)->first();

        if (! $game || ! $game->isOpen()) {
            return null;
        }

        app(GamePlayerService::class)->join($game, $request->user());

        return redirect()->route('public-games.show', $game)->with('status', 'joined-game');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
