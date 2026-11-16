<?php

namespace App\Services\Exams;

use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\TrialAttempt;
use App\Models\TrialQuestion;
use Illuminate\Support\Collection;

class StudentOptionOrder
{
    /**
     * Keep original keys for saving/scoring; the view labels rows A–E by position.
     * A keyed hash provides a stable permutation per attempt without database writes
     * or session state, including after refresh or an authorized device change.
     */
    public function forQuestion(ExamAttempt|TrialAttempt $attempt, ExamQuestion|TrialQuestion $question): Collection
    {
        $seed = implode('|', [$attempt->getTable(), $attempt->id, $attempt->started_at?->getTimestamp(), $question->id]);

        return collect($question->options ?? [])->sortBy(
            fn ($text, $key): string => hash_hmac('sha256', $seed.'|'.$key, (string) config('app.key')),
        );
    }
}
