<?php

namespace App\Models;

use App\Enums\ExamQuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['trial_question_bank_id', 'question_type', 'question_text', 'text_format', 'statement', 'question_image_path', 'question_image_caption', 'question_context_id', 'options', 'option_file_paths', 'option_file_captions', 'correct_answer', 'points', 'position'])]
#[Hidden(['correct_answer'])]
class TrialQuestion extends Model
{
    protected $attributes = ['text_format' => 'plain', 'question_type' => 'multiple_choice'];

    protected function casts(): array
    {
        return [
            'question_type' => ExamQuestionType::class,
            'question_context_id' => 'integer',
            'retired_after_attempt_id' => 'integer',
            'options' => 'array',
            'option_file_paths' => 'array',
            'option_file_captions' => 'array',
            'points' => 'decimal:2',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(TrialQuestionBank::class, 'trial_question_bank_id');
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(QuestionContext::class, 'question_context_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TrialAnswer::class);
    }
}
