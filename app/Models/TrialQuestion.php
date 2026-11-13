<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['trial_question_bank_id', 'question_text', 'text_format', 'statement', 'question_image_path', 'options', 'option_file_paths', 'correct_answer', 'points', 'position'])]
#[Hidden(['correct_answer'])]
class TrialQuestion extends Model
{
    protected $attributes = ['text_format' => 'plain'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'option_file_paths' => 'array',
            'points' => 'decimal:2',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(TrialQuestionBank::class, 'trial_question_bank_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TrialAnswer::class);
    }
}
