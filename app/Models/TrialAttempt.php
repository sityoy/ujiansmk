<?php

namespace App\Models;

use App\Enums\GradingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'trial_exam_id', 'student_id', 'participant_name', 'participant_identifier', 'session_token_hash',
    'status', 'duration_minutes', 'started_at', 'submitted_at', 'score', 'grading_status', 'correct_answers', 'total_questions', 'multiple_choice_questions',
    'violation_count', 'security_locked_at', 'security_lock_version', 'last_seen_at',
    'ip_address', 'user_agent',
])]
class TrialAttempt extends Model
{
    public const RESET_STATUS = 'reset';

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'grading_status' => GradingStatus::class,
            'multiple_choice_questions' => 'integer',
            'correct_answers' => 'integer',
            'total_questions' => 'integer',
            'violation_count' => 'integer',
            'security_reset_count' => 'integer',
            'security_locked_at' => 'datetime',
            'security_lock_version' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function trialExam(): BelongsTo
    {
        return $this->belongsTo(TrialExam::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TrialAnswer::class);
    }

    public function questionQuery(): Builder
    {
        return $this->trialExam->questionBank->allQuestions()->getQuery()->where(fn (Builder $query) => $query
            ->whereNull('retired_after_attempt_id')
            ->orWhere('retired_after_attempt_id', '>=', $this->id));
    }

    public function securityIncidents(): HasMany
    {
        return $this->hasMany(TrialSecurityIncident::class);
    }
}
