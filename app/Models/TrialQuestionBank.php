<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_id', 'teacher_user_id', 'grade_level', 'name'])]
class TrialQuestionBank extends Model
{
    protected function casts(): array
    {
        return ['grade_level' => 'integer'];
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
