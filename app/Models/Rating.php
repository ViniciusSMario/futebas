<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'organizer_id', 'user_id', 'overall_rating', 'punctuality_rating', 'behavior_rating', 'performance_rating', 'comment'])]
class Rating extends Model
{
    public const MIN_STARS = 1;

    public const MAX_STARS = 5;

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'punctuality_rating' => 'integer',
            'behavior_rating' => 'integer',
            'performance_rating' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
