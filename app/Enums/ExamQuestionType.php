<?php

namespace App\Enums;

enum ExamQuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case ShortAnswer = 'short_answer';
    case Essay = 'essay';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Pilihan Ganda',
            self::ShortAnswer => 'Isian Singkat',
            self::Essay => 'Esai',
        };
    }

    public function requiresManualGrading(): bool
    {
        return $this !== self::MultipleChoice;
    }
}
