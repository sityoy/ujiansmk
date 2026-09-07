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
    'starts_on', 'ends_on', 'report_place', 'report_date', 'report_paper_size',
    'report_margin_top_mm', 'report_margin_right_mm', 'report_margin_bottom_mm',
    'report_margin_left_mm', 'report_scale_percent', 'status',
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
            'report_date' => 'date',
            'report_margin_top_mm' => 'integer',
            'report_margin_right_mm' => 'integer',
            'report_margin_bottom_mm' => 'integer',
            'report_margin_left_mm' => 'integer',
            'report_scale_percent' => 'integer',
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

    public function extracurricularGrades(): HasMany
    {
        return $this->hasMany(ExtracurricularGrade::class);
    }

    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(MidtermAttendanceSummary::class);
    }
}
