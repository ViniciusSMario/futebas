<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerProfileUpdateRequest;
use App\Models\PlayerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PlayerProfileController extends Controller
{
    /**
     * Display the authenticated user's player profile form.
     */
    public function edit(Request $request): View
    {
        return view('player-profile.edit', [
            'user' => $request->user(),
            'playerProfile' => $request->user()->playerProfile,
        ]);
    }

    /**
     * Update the authenticated user's player profile.
     */
    public function update(PlayerProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $user->fill(['name' => $validated['name']])->save();

        $playerProfile = PlayerProfile::firstOrNew(['user_id' => $user->id]);

        $positions = array_values(array_unique(array_merge(
            [$validated['position_primary']],
            $validated['positions_secondary'] ?? []
        )));

        $photoPath = $playerProfile->photo_path;

        if ($request->hasFile('photo')) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            $photoPath = $request->file('photo')->store('player-photos', 'public');
        }

        $playsOutsideCity = $request->boolean('plays_outside_city');

        $playerProfile->fill([
            'photo_path' => $photoPath,
            'birth_date' => $validated['birth_date'],
            'state' => $validated['state'],
            'city' => $validated['city'],
            'phone' => $validated['phone'],
            'positions' => $positions,
            'modalities' => array_values($validated['modalities']),
            'level' => $validated['level'],
            'price_per_game' => $validated['price_per_game'],
            'plays_outside_city' => $playsOutsideCity,
            'price_per_game_outside' => $playsOutsideCity ? $validated['price_per_game_outside'] : null,
        ]);

        $playerProfile->save();

        return redirect()->route('player-profile.edit')->with('status', 'player-profile-updated');
    }
}
