<?php

namespace App\Http\Requests;

use App\Models\Game;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvitationStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'game_id' => [
                'required',
                'integer',
                Rule::exists('games', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('status', 'open')),
            ],
            'position' => ['nullable', 'string', Rule::in(Game::POSITIONS)],
            'value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
