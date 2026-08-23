<?php

namespace App\Models;

use App\Enums\AssessmentType;
use App\Enums\PeriodStatus;
use App\Enums\Semester;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'academic_year_id', 'code', 'name', 'type', 'semester', 'sequence_no',
    'starts_on', 'ends_on', 'status',
])]
class AssessmentPeriod extends Model
{
    protected function casts(): array
    {
        return [
            'type' => AssessmentType::class,
            'semester' => Semester::class,
            'status' => PeriodStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function assessmentSubjects(): HasMany
    {
        return $this->hasMany(AssessmentSubject::class);
    }
}
