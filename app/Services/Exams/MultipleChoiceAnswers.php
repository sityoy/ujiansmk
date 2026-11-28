<?php

namespace App\Services\Exams;

use Illuminate\Validation\Rule;

class MultipleChoiceAnswers
{
    public const OPTIONS = ['A', 'B', 'C', 'D', 'E'];

    public static function bankRules(): array
    {
        return [
            'correct_answer' => ['exclude_unless:question_type,multiple_choice', 'required_without:correct_answers', 'nullable', Rule::in(self::OPTIONS)],
            'correct_answers' => ['exclude_unless:question_type,multiple_choice', 'required_without:correct_answer', 'array', 'min:1', 'max:5'],
            'correct_answers.*' => ['required', 'distinct', Rule::in(self::OPTIONS)],
        ];
    }

    public static function key(array $choices): string
    {
        // Existing keys are one letter. A sorted set still fits the same five-character column.
        sort($choices, SORT_STRING);

        return implode('', $choices);
    }

    public static function allowsMultiple(?string $key): bool
    {
        return self::selectionLimit($key) > 1;
    }

    public static function selectionLimit(?string $key): int
    {
        return max(1, strlen($key ?? ''));
    }

    public static function limitMessage(int $limit): string
    {
        return "Pilih maksimal {$limit} jawaban untuk soal ini.";
    }

    public static function normalize(string $answer, array $options, int $limit): ?string
    {
        if (strlen($answer) > $limit || ! preg_match('/\A[A-E]{1,5}\z/', $answer)) {
            return null;
        }

        $choices = str_split($answer);
        if (count(array_unique($choices)) !== count($choices) || array_diff($choices, array_keys($options)) !== []) {
            return null;
        }

        return self::key($choices);
    }

    public static function label(?string $key): string
    {
        return implode(', ', str_split($key ?? ''));
    }
}
