<?php

namespace App\Http\Requests;

use App\Services\TeamDrawService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameTeamDrawRequest extends FormRequest
{
    /**
     * Balancing is what an organizer wants unless they say otherwise, so a
     * request that omits the mode gets it rather than the old shuffle.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'mode' => $this->input('mode') ?: TeamDrawService::MODE_BALANCED,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'teams_count' => ['required', 'integer', 'min:2', 'max:8'],
            'mode' => ['required', Rule::in(TeamDrawService::MODES)],
        ];
    }
}
