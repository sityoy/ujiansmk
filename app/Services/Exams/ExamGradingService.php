<?php

namespace App\Services\Exams;

use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamGradingService
{
    public function grade(ExamAttempt $attempt, User $grader, array $scores, ?float $manualScore = null): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $grader, $scores, $manualScore): ExamAttempt {
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status !== AttemptStatus::Submitted) {
                throw ValidationException::withMessages(['grading' => 'Jawaban hanya dapat dikoreksi setelah ujian dikumpulkan.']);
            }

            $attempt->load(['assignment.assessmentSubject.questions', 'answers']);
            $questions = $attempt->assignment->assessmentSubject->questions;
            $manualQuestions = $questions->filter(
                fn (ExamQuestion $question): bool => $question->question_type->requiresManualGrading(),
            );

            if ($manualQuestions->isEmpty() && $manualScore === null) {
                throw ValidationException::withMessages([
                    'manual_score' => 'Masukkan nilai Essay/Isian manual untuk mengubah nilai ujian ini.',
                ]);
            }

            foreach ($manualQuestions as $question) {
                if (! array_key_exists($question->id, $scores)) {
                    if ($manualScore === null) {
                        throw ValidationException::withMessages([
                            'scores.'.$question->id => 'Nilai soal '.$question->position.' wajib diisi.',
                        ]);
                    }
                    continue;
                }

                $value = $scores[$question->id];
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
            $calculator = app(ExamScoreCalculator::class);
            $manualComponent = $manualScore !== null
                ? max(0, min(100, $manualScore))
                : $calculator->manualComponentScore($questions, $answers);
            $calculatedScore = $calculator->score($questions, $answers, $manualComponent);

            $attempt->update([
                'score' => min(100, $calculatedScore),
                'manual_score' => $manualComponent,
                'grading_status' => GradingStatus::Graded,
                'graded_at' => now(),
            ]);

            return $attempt->refresh();
        });
    }
}
