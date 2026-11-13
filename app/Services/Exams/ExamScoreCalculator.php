<?php

namespace App\Services\Exams;

use App\Enums\ExamQuestionType;
use Illuminate\Support\Collection;

class ExamScoreCalculator
{
    public const MULTIPLE_CHOICE_POINTS = 2.0;

    public function multipleChoiceWeight(int $questionCount): float
    {
        return $questionCount > 0 ? self::MULTIPLE_CHOICE_POINTS : 0.0;
    }

    public function multipleChoiceScore(int $correctAnswers, int $questionCount): float
    {
        if ($questionCount <= 0) {
            return 0.0;
        }

        $correct = max(0, min($correctAnswers, $questionCount));

        return round(min(100, $correct * self::MULTIPLE_CHOICE_POINTS), 2);
    }

    public function manualComponentScore(Collection $questions, Collection $answers): float
    {
        $manual = $questions->filter(
            fn ($question): bool => $question->question_type->requiresManualGrading(),
        );
        $maximum = (float) $manual->sum(fn ($question): float => (float) $question->points);
        if ($maximum <= 0) {
            return 0.0;
        }

        $earned = (float) $manual->sum(function ($question) use ($answers): float {
            $awarded = (float) ($answers->get($question->id)?->points_awarded ?? 0);

            return max(0, min($awarded, (float) $question->points));
        });

        return round(min(100, ($earned / $maximum) * 100), 2);
    }

    public function score(Collection $questions, Collection $answers, ?float $manualScore = null): float
    {
        $multipleChoice = $questions->filter(
            fn ($question): bool => $question->question_type === ExamQuestionType::MultipleChoice,
        );
        $manualQuestions = $questions->filter(
            fn ($question): bool => $question->question_type->requiresManualGrading(),
        );
        $correct = $multipleChoice->filter(
            fn ($question): bool => (bool) $answers->get($question->id)?->is_correct,
        )->count();
        $pgScore = $this->multipleChoiceScore($correct, $multipleChoice->count());
        $hasManual = $manualQuestions->isNotEmpty() || $manualScore !== null;
        $manualComponent = $manualScore !== null
            ? max(0, min(100, $manualScore))
            : $this->manualComponentScore($questions, $answers);

        if ($multipleChoice->isNotEmpty() && $hasManual) {
            return round(min(100, ($pgScore + $manualComponent) / 2), 2);
        }
        if ($hasManual) {
            return round(min(100, $manualComponent), 2);
        }

        return round(min(100, $pgScore), 2);
    }
}
