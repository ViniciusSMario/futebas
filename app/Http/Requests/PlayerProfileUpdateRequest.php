<?php

namespace App\Http\Requests;

use App\Models\PlayerProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlayerProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
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
            'plays_outside_city' => ['boolean'],
            'price_per_game_outside' => ['required_if:plays_outside_city,1', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
