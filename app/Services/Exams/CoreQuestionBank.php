<?php

namespace App\Services\Exams;

use App\Models\AssessmentSubject;
use App\Models\ExamQuestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CoreQuestionBank
{
    public function readableComponent(AssessmentSubject $component): AssessmentSubject
    {
        return $component->questions()->exists() ? $component : ($this->findSource($component) ?? $component);
    }

    public function synchronize(Collection $group, string $errorKey = 'question'): void
    {
        if ($group->count() < 2) {
            return;
        }

        $group->load(['questions' => fn ($questions) => $questions->lockForUpdate()]);
        $source = $group->first(fn (AssessmentSubject $component): bool => $component->questions->isNotEmpty());
        if (! $source) {
            return;
        }

        $signature = $this->signature($source);
        foreach ($group as $component) {
            if ($component->questions->isNotEmpty() && $this->signature($component) !== $signature) {
                throw ValidationException::withMessages([
                    $errorKey => 'Isi bank soal antarrombel pada guru ini berbeda. Periksa penugasan guru dan soal setiap rombel sebelum menggabungkan atau mengubahnya.',
                ]);
            }
        }

        foreach ($group as $component) {
            if ($component->questions->isEmpty() && ! $component->assignments()->whereHas('attempt')->exists()) {
                $this->copy($source, $component);
            }
        }
    }

    public function copyToNewAttempt(AssessmentSubject $component): void
    {
        if ($component->questions->isNotEmpty()) {
            return;
        }

        $source = $this->findSource($component);
        if ($source) {
            $this->copy($source, $component);
            $component->load('questions');
        }
    }

    private function findSource(AssessmentSubject $component): ?AssessmentSubject
    {
        return $component->questionBankGroup()->whereHas('questions')->withCount('questions')
            ->orderByDesc('questions_count')->orderBy('id')->first();
    }

    private function copy(AssessmentSubject $source, AssessmentSubject $destination): void
    {
        foreach ($source->questions as $question) {
            $destination->questions()->save($question->replicate());
        }
    }

    private function signature(AssessmentSubject $component): array
    {
        return $component->questions->map(fn (ExamQuestion $question): array => $question->only([
            'position', 'question_type', 'text_format', 'statement', 'question_text', 'question_image_path',
            'options', 'option_file_paths', 'correct_answer', 'points',
        ]))->all();
    }
}
