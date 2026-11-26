<?php

namespace App\Models;

use App\Enums\ExamQuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_subject_id', 'question_text', 'text_format', 'statement', 'question_image_path', 'question_image_caption', 'question_context_id', 'question_type', 'options', 'option_file_paths', 'option_file_captions', 'correct_answer', 'points', 'position'])]
#[Hidden(['correct_answer'])]
class ExamQuestion extends Model
{
    protected $attributes = ['question_type' => 'multiple_choice', 'text_format' => 'plain'];

    protected function casts(): array
    {
        return [
            'question_type' => ExamQuestionType::class,
            'question_context_id' => 'integer',
            'options' => 'array',
            'option_file_paths' => 'array',
            'option_file_captions' => 'array',
            'points' => 'decimal:2',
        ];
    }

    public function assessmentSubject(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubject::class);
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(QuestionContext::class, 'question_context_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }
}
