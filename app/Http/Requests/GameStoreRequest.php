<?php

namespace App\Http\Requests;

use App\Models\Game;
use App\Rules\CityInState;
use App\Support\Cities;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_name' => ['required', 'string', 'max:255'],
            'modality' => ['required', 'string', Rule::in(Game::MODALITIES)],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', Rule::in(array_keys(Cities::states()))],
            'city' => ['required', 'string', 'max:255', new CityInState($this->input('state'))],
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
