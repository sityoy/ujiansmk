<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'assessment_period_id', 'student_id', 'sick_days', 'excused_days',
    'unexcused_days', 'notes', 'recorded_by_user_id',
])]
class MidtermAttendanceSummary extends Model
{
    protected function casts(): array
    {
        return [
            'sick_days' => 'integer',
            'excused_days' => 'integer',
            'unexcused_days' => 'integer',
        ];
    }

    public function assessmentPeriod(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
