<?php

namespace App\Services\Exams;

use App\Enums\ExamQuestionType;
use Illuminate\Support\Collection;

class StudentQuestionSections
{
    public static function fromQuestions(Collection $questions): array
    {
        $byType = $questions->groupBy(fn ($question) => $question->question_type->value);
        $sections = [];
        foreach (ExamQuestionType::cases() as $type) {
            if ($byType->has($type->value)) {
                $sections[] = ['type' => $type, 'questions' => $byType->get($type->value)];
            }
        }

        return $sections;
    }
}
