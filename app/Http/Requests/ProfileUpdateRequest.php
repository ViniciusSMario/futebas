<?php

namespace App\Http\Requests;

use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];

        if ($this->user()->hasRole(User::ROLE_ORGANIZER)) {
            $rules['photo'] = ['nullable', 'image', 'max:2048'];
            $rules['state'] = ['required', 'string', Rule::in(array_keys(PlayerProfile::STATES))];
            $rules['city'] = ['required', 'string', 'max:255'];
            $rules['phone'] = ['required', 'string', 'max:20'];
        }

        return $rules;
    }
}
