<?php

namespace App\Services\Exams;

use App\Enums\ExamQuestionType;
use Illuminate\Support\Collection;

class ExamScoreCalculator
{
    public const MULTIPLE_CHOICE_MAX = 70.0;

    public const MANUAL_MAX_WITH_MULTIPLE_CHOICE = 30.0;

    public function multipleChoiceWeight(int $questionCount): float
    {
        return $questionCount > 0 ? self::MULTIPLE_CHOICE_MAX / $questionCount : 0.0;
    }

    public function multipleChoiceScore(int $correctAnswers, int $questionCount): float
    {
        if ($questionCount <= 0) {
            return 0.0;
        }

        return round((max(0, min($correctAnswers, $questionCount)) / $questionCount) * self::MULTIPLE_CHOICE_MAX, 2);
    }

    public function score(Collection $questions, Collection $answers): float
    {
        $multipleChoice = $questions->filter(
            fn ($question): bool => $question->question_type === ExamQuestionType::MultipleChoice,
        );
        $manual = $questions->filter(
            fn ($question): bool => $question->question_type->requiresManualGrading(),
        );
        $correct = $multipleChoice->filter(
            fn ($question): bool => (bool) $answers->get($question->id)?->is_correct,
        )->count();
        $score = $this->multipleChoiceScore($correct, $multipleChoice->count());

        if ($manual->isEmpty()) {
            return $score;
        }

        $maximum = (float) $manual->sum(fn ($question): float => (float) $question->points);
        $earned = (float) $manual->sum(function ($question) use ($answers): float {
            $awarded = (float) ($answers->get($question->id)?->points_awarded ?? 0);

            return max(0, min($awarded, (float) $question->points));
        });
        $manualMaximum = $multipleChoice->isNotEmpty()
            ? self::MANUAL_MAX_WITH_MULTIPLE_CHOICE
            : 100.0;

        return round(min(100, $score + ($maximum > 0 ? ($earned / $maximum) * $manualMaximum : 0)), 2);
    }
}
