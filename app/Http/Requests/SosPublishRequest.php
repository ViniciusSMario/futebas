<?php

namespace App\Http\Requests;

use App\Models\Game;
use App\Models\SosRequest;
use App\Rules\CityInState;
use App\Support\Cities;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

/**
 * Publishing an SOS takes one of two shapes: point at a match the
 * organizer already created, or describe a new one inline. `source` picks
 * between them and every match field is only required in the second case.
 */
class SosPublishRequest extends FormRequest
{
    public const SOURCE_EXISTING = 'existing';

    public const SOURCE_NEW = 'new';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in([self::SOURCE_EXISTING, self::SOURCE_NEW])],

            'game_id' => [
                Rule::requiredIf(fn () => $this->input('source') === self::SOURCE_EXISTING),
                'nullable',
                'integer',
                // Only the organizer's own open matches may receive an SOS.
                Rule::exists('games', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->where('status', Game::STATUS_OPEN),
            ],

            'team_name' => [$this->requiredForNewGame(), 'nullable', 'string', 'max:255'],
            'date' => [$this->requiredForNewGame(), 'nullable', 'date', 'after_or_equal:today'],
            'start_time' => [$this->requiredForNewGame(), 'nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => [$this->requiredForNewGame(), 'nullable', 'string', 'max:255'],
            'state' => [$this->requiredForNewGame(), 'nullable', 'string', Rule::in(array_keys(Cities::states()))],
            'city' => [$this->requiredForNewGame(), 'nullable', 'string', 'max:255', new CityInState($this->input('state'))],
            'modality' => [$this->requiredForNewGame(), 'nullable', Rule::in(Game::MODALITIES)],

            'offered_value' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'game_id' => 'partida',
            'team_name' => 'nome do time',
            'offered_value' => 'valor oferecido',
            'start_time' => 'horário',
            'end_time' => 'horário de término',
        ];
    }

    public function isForNewGame(): bool
    {
        return $this->input('source') === self::SOURCE_NEW;
    }

    /**
     * Attributes for the lightweight match an SOS creates on the fly: a
     * single paid slot for a goalkeeper.
     *
     * @return array<string, mixed>
     */
    public function newGameAttributes(): array
    {
        return [
            'team_name' => $this->string('team_name')->value(),
            'location' => $this->string('location')->value(),
            'city' => $this->string('city')->value(),
            'state' => $this->string('state')->value(),
            'modality' => $this->string('modality')->value(),
            'date' => $this->date('date'),
            'start_time' => $this->string('start_time')->value(),
            'end_time' => $this->filled('end_time') ? $this->string('end_time')->value() : null,
            'max_players' => 1,
            // The SOS player is paid by the organizer, not charged a fee.
            'price' => 0,
            'positions' => [SosRequest::POSITION],
            'status' => Game::STATUS_OPEN,
            'requires_approval' => true,
        ];
    }

    private function requiredForNewGame(): RequiredIf
    {
        return Rule::requiredIf(fn () => $this->isForNewGame());
    }
}
