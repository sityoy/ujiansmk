<?php

namespace App\Services\Exams;

use App\Enums\AttemptStatus;
use App\Enums\ExamQuestionType;
use App\Enums\GradingStatus;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamGradingService
{
    public function grade(ExamAttempt $attempt, User $grader, array $scores): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $grader, $scores): ExamAttempt {
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status !== AttemptStatus::Submitted) {
                throw ValidationException::withMessages(['grading' => 'Jawaban hanya dapat dikoreksi setelah ujian dikumpulkan.']);
            }

            $attempt->load(['assignment.assessmentSubject.questions', 'answers']);
            $questions = $attempt->assignment->assessmentSubject->questions;
            $manualQuestions = $questions->filter(
                fn (ExamQuestion $question): bool => $question->question_type->requiresManualGrading(),
            );
            if ($manualQuestions->isEmpty()) {
                throw ValidationException::withMessages(['grading' => 'Ujian ini tidak memiliki isian singkat atau esai.']);
            }

            foreach ($manualQuestions as $question) {
                $value = $scores[$question->id] ?? null;
                if (! is_numeric($value) || (float) $value < 0 || (float) $value > (float) $question->points) {
                    throw ValidationException::withMessages([
                        'scores.'.$question->id => 'Nilai soal '.$question->position.' harus antara 0 dan '.$question->points.'.',
                    ]);
                }

                ExamAnswer::query()->updateOrCreate(
                    ['exam_attempt_id' => $attempt->id, 'exam_question_id' => $question->id],
                    ['points_awarded' => (float) $value, 'graded_by_user_id' => $grader->id, 'graded_at' => now()],
                );
            }

            $attempt->load('answers');
            $answers = $attempt->answers->keyBy('exam_question_id');
            $totalPoints = (float) $questions->sum(fn (ExamQuestion $question): float => (float) $question->points);
            $earnedPoints = (float) $questions->sum(function (ExamQuestion $question) use ($answers): float {
                $answer = $answers->get($question->id);
                if ($question->question_type === ExamQuestionType::MultipleChoice) {
                    return $answer?->is_correct ? (float) $question->points : 0.0;
                }

                return (float) ($answer?->points_awarded ?? 0);
            });

            $attempt->update([
                'score' => $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0,
                'grading_status' => GradingStatus::Graded,
                'graded_at' => now(),
            ]);

            return $attempt->refresh();
        });
    }
}
