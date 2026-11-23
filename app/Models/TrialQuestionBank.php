<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_id', 'teacher_user_id', 'grade_level', 'name', 'source_pack_key'])]
class TrialQuestionBank extends Model
{
    public const QUESTION_TIMEZONE = 'Asia/Jakarta';

    protected function casts(): array
    {
        return ['grade_level' => 'integer', 'archived_at' => 'datetime', 'question_deadline_at' => 'datetime'];
    }

    public function questionDeadlineLabel(): ?string
    {
        $deadline = $this->question_deadline_at?->timezone(self::QUESTION_TIMEZONE);

        return $deadline ? $deadline->format('d/m/Y H:i').' WIB' : null;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TrialQuestion::class)->orderBy('position');
    }

    public function trialExams(): HasMany
    {
        return $this->hasMany(TrialExam::class);
    }
}
