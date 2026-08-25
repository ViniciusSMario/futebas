<?php

namespace App\Http\Requests;

use App\Models\Rating;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RatingStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $stars = ['required', 'integer', 'between:'.Rating::MIN_STARS.','.Rating::MAX_STARS];

        return [
            'overall_rating' => $stars,
            'punctuality_rating' => $stars,
            'behavior_rating' => $stars,
            'performance_rating' => $stars,
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
