<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assessment_period_id', 'student_id', 'room_name'])]
class StudentExamRoom extends Model
{
    public function assessmentPeriod(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
