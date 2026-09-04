<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_subject_id', 'question_text', 'options', 'correct_answer', 'points', 'position'])]
#[Hidden(['correct_answer'])]
class ExamQuestion extends Model
{
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'points' => 'decimal:2',
        ];
    }

    public function assessmentSubject(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubject::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }
}
