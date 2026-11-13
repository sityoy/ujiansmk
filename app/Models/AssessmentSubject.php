<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_period_id', 'subject_id', 'school_class_id', 'teacher_user_id', 'learning_objective'])]
class AssessmentSubject extends Model
{
    public function assessmentPeriod(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    public function questionBankGroup(): Builder
    {
        $query = self::query();
        if ($this->teacher_user_id === null) {
            return $query->whereKey($this->id);
        }

        $this->loadMissing('schoolClass');

        return $query->where('assessment_period_id', $this->assessment_period_id)
            ->where('subject_id', $this->subject_id)
            ->where('teacher_user_id', $this->teacher_user_id)
            ->whereHas('schoolClass', fn ($classes) => $classes->where('grade_level', $this->schoolClass->grade_level));
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('position');
    }

    public function midtermResults(): HasMany
    {
        return $this->hasMany(MidtermSubjectResult::class);
    }
}
