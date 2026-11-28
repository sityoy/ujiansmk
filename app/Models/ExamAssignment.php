<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['assessment_subject_id', 'student_id', 'exam_session_id', 'status', 'assigned_at'])]
class ExamAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'assigned_at' => 'datetime',
            'security_reset_count' => 'integer',
        ];
    }

    public function assessmentSubject(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubject::class);
    }

    public function scopeForActiveStudents(Builder $query): Builder
    {
        return $query->whereHas('student', fn (Builder $students) => $students->where('is_active', true));
    }

    public function scopeVisibleInRoster(Builder $query): Builder
    {
        return $query->where(fn (Builder $roster) => $roster->forActiveStudents()
            ->orWhereHas('examSession', fn (Builder $sessions) => $sessions
                ->where('status', SessionStatus::Closed)->orWhere('ends_at', '<=', now())));
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function attempt(): HasOne
    {
        return $this->hasOne(ExamAttempt::class);
    }

    public function attemptResets(): HasMany
    {
        return $this->hasMany(ExamAttemptReset::class);
    }
}
