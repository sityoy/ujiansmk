<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_subject_id', 'trial_question_bank_id', 'trial_location_id', 'title', 'slug', 'access_code', 'duration_minutes', 'starts_at', 'ends_at', 'is_active'])]
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(TrialLocation::class, 'trial_location_id');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(TrialCheckin::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TrialAttempt::class);
    }

    public function currentAttempts(): HasMany
    {
        return $this->attempts()->where('status', '!=', TrialAttempt::RESET_STATUS);
    }

    public function scopeVisibleToMonitor(Builder $query, User $user): Builder
    {
        return $query->when($user->role === UserRole::Teacher, fn (Builder $query) => $query
            ->whereHas('assessmentSubject', fn (Builder $component) => $component->where('teacher_user_id', $user->id)));
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && $this->questionBank !== null && $this->questionBank->archived_at === null
            && (! $this->starts_at || now()->greaterThanOrEqualTo($this->starts_at))
            && (! $this->ends_at || now()->lessThanOrEqualTo($this->ends_at));
    }
}
