<?php

namespace App\Services\Exams;

use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Subject;
use App\Models\TrialAttempt;
use App\Models\TrialQuestion;
use Illuminate\Support\Collection;

class StudentOptionOrder
{
    public function shouldShuffle(?Subject $subject): bool
    {
        $code = strtoupper(trim($subject?->code ?? ''));
        $name = trim($subject?->name ?? '');

        return ! in_array($code, ['BIG', 'BING', 'ENG'], true)
            && preg_match('/^(?:bahasa\s+inggris|b(?:hs)?\.?\s*inggris|english)(?=$|[\s\p{Pd}\/(])/iu', $name) !== 1;
    }

    /**
     * Keep the original option keys when saving and grading answers.
     * A keyed hash provides a stable permutation per attempt without database writes
     * or session state, including after refresh or an authorized device change.
     */
    public function forQuestion(
        ExamAttempt|TrialAttempt $attempt,
        ExamQuestion|TrialQuestion $question,
        bool $shuffle = true,
    ): Collection {
        $seed = implode('|', [$attempt->getTable(), $attempt->id, $attempt->started_at?->getTimestamp(), $question->id]);

        return $this->orderedOptions($question, $seed, $shuffle);
    }

    public function forPreview(ExamQuestion|TrialQuestion $question, bool $shuffle): Collection
    {
        $seed = implode('|', ['preview', $question->getTable(), $question->id]);

        return $this->orderedOptions($question, $seed, $shuffle);
    }

    private function orderedOptions(ExamQuestion|TrialQuestion $question, string $seed, bool $shuffle): Collection
    {
        $options = collect($question->options ?? []);
        if (! $shuffle) {
            return $options->sortKeys();
        }

        return $options->sortBy(
            fn ($text, $key): string => hash_hmac('sha256', $seed.'|'.$key, (string) config('app.key')),
        );
    }
}
