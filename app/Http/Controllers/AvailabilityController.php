<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvailabilityUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    /**
     * Display the authenticated user's availability form.
     */
    public function edit(Request $request): View
    {
        return view('availability.edit', [
            'availabilities' => $request->user()->availabilities()->orderBy('day_of_week')->get(),
        ]);
    }

    /**
     * Update the authenticated user's availability.
     */
    public function update(AvailabilityUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $user->availabilities()->delete();

        $rows = array_map(fn (int $day) => [
            'user_id' => $user->id,
            'day_of_week' => $day,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'created_at' => now(),
            'updated_at' => now(),
        ], array_values(array_unique($validated['days'])));

        $user->availabilities()->insert($rows);

        return redirect()->route('availability.edit')->with('status', 'availability-updated');
    }
}
