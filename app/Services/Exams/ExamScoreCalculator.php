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

    public function multipleChoicePoints(?string $answer, ?string $key): float
    {
        if ($answer === null || blank($key)) {
            return 0.0;
        }

        $keys = str_split($key);
        $correct = count(array_intersect($keys, str_split($answer)));

        return round(self::MULTIPLE_CHOICE_POINTS * $correct / count($keys), 2);
    }

    public function multipleChoiceScore(float $points): float
    {
        return round(max(0, min(100, $points)), 2);
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
        $points = $multipleChoice->sum(function ($question) use ($answers): float {
            $answer = $answers->get($question->id);
            $awarded = $answer?->points_awarded ?? ($answer?->is_correct ? self::MULTIPLE_CHOICE_POINTS : 0.0);

            return max(0, min(self::MULTIPLE_CHOICE_POINTS, (float) $awarded));
        });
        $pgScore = $this->multipleChoiceScore($points);
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
