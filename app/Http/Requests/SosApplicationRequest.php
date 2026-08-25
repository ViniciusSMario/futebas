<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A goalkeeper applying to an SOS. The asking price is pre-filled with the
 * offered value in the UI but may be changed, so the organizer can compare
 * competing bids.
 */
class SosApplicationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asking_price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'asking_price' => 'valor',
            'message' => 'mensagem',
        ];
    }
}
