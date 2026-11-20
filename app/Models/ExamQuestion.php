<?php

namespace App\Models;

use App\Enums\ExamQuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_subject_id', 'question_text', 'text_format', 'statement', 'question_image_path', 'question_type', 'options', 'option_file_paths', 'correct_answer', 'points', 'position'])]
#[Hidden(['correct_answer'])]
class ExamQuestion extends Model
{
    protected $attributes = ['question_type' => 'multiple_choice', 'text_format' => 'plain'];

    protected function casts(): array
    {
        return [
            'question_type' => ExamQuestionType::class,
            'options' => 'array',
            'option_file_paths' => 'array',
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
