<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_subject_id', 'trial_question_bank_id', 'title', 'slug', 'access_code', 'duration_minutes', 'starts_at', 'ends_at', 'is_active'])]
class TrialExam extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'duration_minutes' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function assessmentSubject(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubject::class);
    }

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(TrialQuestionBank::class, 'trial_question_bank_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TrialAttempt::class);
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && $this->questionBank !== null && $this->questionBank->archived_at === null
            && (! $this->starts_at || now()->greaterThanOrEqualTo($this->starts_at))
            && (! $this->ends_at || now()->lessThanOrEqualTo($this->ends_at));
    }
}
