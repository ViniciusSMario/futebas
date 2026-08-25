<?php

namespace App\Http\Requests;

use App\Models\Game;
use App\Models\GameSeries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameSeriesStoreRequest extends FormRequest
{
    /**
     * Mirrors GameStoreRequest, with the concrete date swapped for the
     * weekday the pelada repeats on.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_name' => ['required', 'string', 'max:255'],
            'modality' => ['required', 'string', Rule::in(Game::MODALITIES)],
            'day_of_week' => ['required', 'integer', Rule::in(array_keys(GameSeries::DAYS_OF_WEEK))],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'max_players' => ['required', 'integer', 'min:2', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'requires_approval' => ['nullable', 'boolean'],
            'organizer_is_playing' => ['nullable', 'boolean'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['string', Rule::in(Game::POSITIONS)],
        ];
    }
}
