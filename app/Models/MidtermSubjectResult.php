<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'assessment_subject_id', 'student_id', 'score', 'description',
    'achieved_objectives', 'improvement_objectives', 'recorded_by_user_id',
])]
class MidtermSubjectResult extends Model
{
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'achieved_objectives' => 'array',
            'improvement_objectives' => 'array',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
