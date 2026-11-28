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
     * Keep original keys for saving/scoring; the view labels rows A–E by position.
     * A keyed hash provides a stable permutation per attempt without database writes
     * or session state, including after refresh or an authorized device change.
     */
    public function forQuestion(
        ExamAttempt|TrialAttempt $attempt,
        ExamQuestion|TrialQuestion $question,
        bool $shuffle = true,
    ): Collection {
        $options = collect($question->options ?? []);
        if (! $shuffle) {
            return $options->sortKeys();
        }

        $seed = implode('|', [$attempt->getTable(), $attempt->id, $attempt->started_at?->getTimestamp(), $question->id]);

        return $options->sortBy(
            fn ($text, $key): string => hash_hmac('sha256', $seed.'|'.$key, (string) config('app.key')),
        );
    }
}
