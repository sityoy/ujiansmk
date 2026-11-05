<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['trial_exam_id', 'participant_name', 'participant_identifier', 'session_token_hash', 'status', 'started_at', 'submitted_at', 'score', 'correct_answers', 'total_questions'])]
class TrialAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'correct_answers' => 'integer',
            'total_questions' => 'integer',
        ];
    }

    public function trialExam(): BelongsTo
    {
        return $this->belongsTo(TrialExam::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TrialAnswer::class);
    }
}
