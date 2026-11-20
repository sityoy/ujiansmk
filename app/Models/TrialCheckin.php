<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trial_exam_id', 'student_id', 'class_name', 'distance_meters', 'accuracy_meters', 'verified_at'])]
class TrialCheckin extends Model
{
    protected function casts(): array
    {
        return [
            'distance_meters' => 'decimal:2',
            'accuracy_meters' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
