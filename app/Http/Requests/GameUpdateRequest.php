<?php

namespace App\Http\Requests;

use App\Models\Game;
use App\Rules\CityInState;
use App\Support\Cities;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Game $game */
        $game = $this->route('game');

        return [
            'team_name' => ['required', 'string', 'max:255'],
            'modality' => ['required', 'string', Rule::in(Game::MODALITIES)],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', Rule::in(array_keys(Cities::states()))],
            'city' => ['required', 'string', 'max:255', new CityInState($this->input('state'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'max_players' => [
                'required',
                'integer',
                'min:2',
                'max:100',
                function ($attribute, $value, $fail) use ($game) {
                    if ($value < $game->confirmedPlayersCount()) {
                        $fail(__('O limite de jogadores não pode ser menor que o número de confirmados (:count).', ['count' => $game->confirmedPlayersCount()]));
                    }
                },
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'requires_approval' => ['nullable', 'boolean'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['string', Rule::in(Game::POSITIONS)],
        ];
    }
}
