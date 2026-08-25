<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameSeriesMemberStoreRequest extends FormRequest
{
    /**
     * The same three ways to name a person as GamePlayerStoreRequest: an
     * existing app user, one of the organizer's reusable guest contacts,
     * or a brand-new guest contact.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guest_player_id' => [
                'nullable',
                'integer',
                Rule::exists('guest_players', 'id')->where(fn ($query) => $query->where('organizer_id', $this->user()->id)),
            ],
            'new_guest_name' => ['nullable', 'string', 'max:255', 'required_without_all:user_id,guest_player_id'],
            'new_guest_phone' => ['nullable', 'string', 'max:20'],
            'new_guest_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
