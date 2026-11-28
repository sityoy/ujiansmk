<?php

namespace App\Services\Exams;

use App\Enums\ExamQuestionType;
use Illuminate\Support\Collection;

class StudentQuestionSections
{
    public static function fromQuestions(Collection $questions, array $previousOrder = []): array
    {
        // Keep the numbering shown by older trial sessions.
        if ($previousOrder !== []) {
            $positions = array_flip($previousOrder);
            $questions = $questions->sortBy(fn ($question) => $positions[$question->id] ?? PHP_INT_MAX);
        }

        $byType = $questions->groupBy(fn ($question) => $question->question_type->value);
        $sections = [];
        $offset = 0;
        foreach (ExamQuestionType::cases() as $type) {
            $questionsOfType = $byType->get($type->value);
            if (! $questionsOfType) {
                continue;
            }

            // Keep each shared passage together while retaining the first occurrence's placement.
            $groups = $questionsOfType->groupBy(fn ($question) => $question->question_context_id
                ? 'context:'.$question->question_context_id : 'question:'.$question->id);
            $ordered = $groups->flatMap(fn ($group) => $group->values())->values();
            $sections[] = [
                'type' => $type,
                'questions' => $ordered,
                'contexts' => self::contexts($ordered, $offset),
            ];
            $offset += $ordered->count();
        }

        return $sections;
    }

    public static function numbersFor(Collection $questions, array $questionIds, array $previousOrder = []): array
    {
        if ($questionIds === []) {
            return [];
        }

        $numbers = [];
        $number = 0;
        $selected = array_fill_keys($questionIds, true);
        foreach (self::fromQuestions($questions, $previousOrder) as $section) {
            foreach ($section['questions'] as $question) {
                $number++;
                if (isset($selected[$question->id])) {
                    $numbers[] = $number;
                }
            }
        }

        return $numbers;
    }

    public static function unansweredMessage(array $numbers): string
    {
        return 'Masih ada '.count($numbers).' soal yang belum dijawab. Silakan kerjakan soal nomor '
            .implode(', ', $numbers).' terlebih dahulu.';
    }

    public static function contexts(Collection $questions, ?int $numberOffset = null): array
    {
        $numbers = [];
        foreach ($questions->values() as $index => $question) {
            if ($question->question_context_id) {
                $numbers[$question->question_context_id][] = $numberOffset === null
                    ? $question->position : $numberOffset + $index + 1;
            }
        }
        foreach ($numbers as $id => $values) {
            $first = $values[0];
            $last = end($values);
            $label = count($values) === 1 ? (string) $first : implode(', ', $values);
            if ($last > $first && $last - $first === count($values) - 1) {
                $label = $first.'–'.$last;
            }
            $numbers[$id] = ['first' => $first, 'label' => $label];
        }

        return $numbers;
    }
}
