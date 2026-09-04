<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['assessment_subject_id', 'student_id', 'exam_session_id', 'status', 'assigned_at'])]
class ExamAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'assigned_at' => 'datetime',
        ];
    }

    public function assessmentSubject(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubject::class);
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
}
