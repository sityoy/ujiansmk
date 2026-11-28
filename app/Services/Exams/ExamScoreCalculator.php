<?php

namespace App\Services\Exams;

use App\Enums\ExamQuestionType;
use Illuminate\Support\Collection;

class ExamScoreCalculator
{
    public const COMBINED_WRITTEN = 'combined_written';

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

    public function multipleChoiceComponentScore(Collection $questions, Collection $answers): float
    {
        $points = $questions->sum(function ($question) use ($answers): float {
            $answer = $answers->get($question->id);
            $awarded = $answer?->points_awarded ?? ($answer?->is_correct ? self::MULTIPLE_CHOICE_POINTS : 0.0);

            return max(0, min(self::MULTIPLE_CHOICE_POINTS, (float) $awarded));
        });

        return $this->multipleChoiceScore($points);
    }

    public function manualComponentScore(Collection $questions, Collection $answers): float
    {
        return $this->writtenGroupScore(
            $questions->filter(fn ($question): bool => $question->question_type->requiresManualGrading()),
            $answers,
        );
    }

    public function writtenGroupIsGraded(Collection $questions, Collection $answers): bool
    {
        return $questions->every(fn ($question): bool => $answers->get($question->id)?->points_awarded !== null);
    }

    public function componentScores(
        Collection $questions,
        Collection $answers,
        ?float $manualScore,
        ?float $finalScore,
    ): array {
        $groups = $questions->groupBy(fn ($question): string => $question->question_type->value);
        $scores = [];
        foreach ($groups as $type => $group) {
            $scores[$type] = match ($type) {
                ExamQuestionType::MultipleChoice->value => $this->multipleChoiceComponentScore($group, $answers),
                default => $this->writtenGroupIsGraded($group, $answers) ? $this->writtenGroupScore($group, $answers) : null,
            };
        }

        if ($answers->isEmpty() && $manualScore === null && $finalScore !== null
            && array_keys($scores) === [ExamQuestionType::MultipleChoice->value]) {
            return [ExamQuestionType::MultipleChoice->value => $finalScore];
        }

        $written = $groups->keys()->reject(fn (string $type): bool => $type === ExamQuestionType::MultipleChoice->value);
        if ($manualScore !== null) {
            if ($written->count() <= 1) {
                $scores[$written->first() ?? ExamQuestionType::Essay->value] = $manualScore;
            } elseif (! $written->every(fn (string $type): bool => $scores[$type] !== null)
                || abs($this->manualComponentScore($questions, $answers) - $manualScore) >= 0.005) {
                // A direct combined score does not say how the points were split.
                $scores[self::COMBINED_WRITTEN] = $manualScore;
            }
        }

        return $scores;
    }

    private function writtenGroupScore(Collection $questions, Collection $answers): float
    {
        $maximum = (float) $questions->sum(fn ($question): float => (float) $question->points);
        if ($maximum <= 0) {
            return 0.0;
        }

        $earned = (float) $questions->sum(function ($question) use ($answers): float {
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
        $pgScore = $this->multipleChoiceComponentScore($multipleChoice, $answers);
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
