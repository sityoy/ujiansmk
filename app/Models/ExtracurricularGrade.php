<?php

namespace App\Models;

use App\Enums\ExtracurricularRating;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assessment_period_id', 'extracurricular_id', 'student_id', 'rating', 'description', 'graded_by_user_id'])]
class ExtracurricularGrade extends Model
{
    protected function casts(): array
    {
        return ['rating' => ExtracurricularRating::class];
    }

    public function assessmentPeriod(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }

    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(Extracurricular::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by_user_id');
    }
}
