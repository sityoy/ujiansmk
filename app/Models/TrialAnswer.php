<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trial_attempt_id', 'trial_question_id', 'answer', 'is_correct', 'points_awarded'])]
class TrialAnswer extends Model
{
    protected $table = 'trial_responses';

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'points_awarded' => 'decimal:2',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TrialAttempt::class, 'trial_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TrialQuestion::class, 'trial_question_id');
    }
}
