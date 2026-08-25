<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\Game;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Services\GamePlayerService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view. When the visitor arrived here with a
     * pending Game to join (public link), the role choice is skipped
     * entirely and the form always registers them as a player.
     */
    public function create(): View
    {
        $intendedGame = null;

        if ($slug = session('intended_game_slug')) {
            $intendedGame = Game::where('slug', $slug)->first();
            $intendedGame = $intendedGame?->isOpen() ? $intendedGame : null;
        }

        return view('auth.register', [
            'forcePlayerRole' => $intendedGame !== null,
            'intendedGame' => $intendedGame,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        $user = $request->input('role') === User::ROLE_ORGANIZER
            ? $this->storeOrganizer($request)
            : $this->storePlayer($request);

        event(new Registered($user));

        Auth::login($user);

        if ($redirect = $this->redirectToIntendedGame($user)) {
            return $redirect;
        }

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * If the user arrived here from a public game link ("Quero
     * Participar"), join them to that game now and send them back to it
     * instead of the dashboard, so the intent to join isn't lost.
     */
    private function redirectToIntendedGame(User $user): ?RedirectResponse
    {
        $slug = session()->pull('intended_game_slug');

        if (! $slug) {
            return null;
        }

        $game = Game::where('slug', $slug)->first();

        if (! $game || ! $game->isOpen()) {
            return null;
        }

        app(GamePlayerService::class)->join($game, $user);

        return redirect()->route('public-games.show', $game)->with('status', 'joined-game');
    }

    /**
     * Validate and create a player: user account, sports profile and availability.
     */
    private function storePlayer(Request $request): User
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'photo' => ['nullable', 'image', 'max:2048'],
            'birth_date' => ['required', 'date', 'before:today'],
            'state' => ['required', 'string', Rule::in(array_keys(PlayerProfile::STATES))],
            'city' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'position_primary' => ['required', 'string', Rule::in(PlayerProfile::POSITIONS)],
            'positions_secondary' => ['array'],
            'positions_secondary.*' => ['string', Rule::in(PlayerProfile::POSITIONS)],
            'modalities' => ['required', 'array', 'min:1'],
            'modalities.*' => ['string', Rule::in(PlayerProfile::MODALITIES)],
            'level' => ['required', 'string', Rule::in(PlayerProfile::LEVELS)],
            'price_per_game' => ['required', 'numeric', 'min:0'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_PLAYER,
        ]);

        $positions = array_values(array_unique(array_merge(
            [$validated['position_primary']],
            $validated['positions_secondary'] ?? []
        )));

        PlayerProfile::create([
            'user_id' => $user->id,
            'photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('player-photos', 'public') : null,
            'birth_date' => $validated['birth_date'],
            'state' => $validated['state'],
            'city' => $validated['city'],
            'phone' => $validated['phone'],
            'positions' => $positions,
            'modalities' => array_values($validated['modalities']),
            'level' => $validated['level'],
            'price_per_game' => $validated['price_per_game'],
        ]);

        Availability::insert(array_map(fn (int $day) => [
            'user_id' => $user->id,
            'day_of_week' => $day,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'created_at' => now(),
            'updated_at' => now(),
        ], array_values(array_unique($validated['days']))));

        return $user;
    }

    /**
     * Validate and create an organizer: a user account with basic contact info.
     */
    private function storeOrganizer(Request $request): User
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'photo' => ['nullable', 'image', 'max:2048'],
            'phone' => ['required', 'string', 'max:20'],
            'state' => ['required', 'string', Rule::in(array_keys(PlayerProfile::STATES))],
            'city' => ['required', 'string', 'max:255'],
        ]);

        return User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_ORGANIZER,
            'phone' => $validated['phone'],
            'state' => $validated['state'],
            'city' => $validated['city'],
            'photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('organizer-photos', 'public') : null,
        ]);
    }
}
