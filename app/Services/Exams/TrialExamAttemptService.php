<?php

namespace App\Services\Exams;

use App\Enums\GradingStatus;
use App\Models\TrialAnswer;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use App\Models\TrialQuestion;
use App\Models\TrialQuestionBank;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TrialExamAttemptService
{
    public function deadline(TrialAttempt $attempt): Carbon
    {
        $duration = $attempt->duration_minutes ?? $attempt->loadMissing('trialExam')->trialExam->duration_minutes;

        return $attempt->started_at->copy()->addMinutes($duration);
    }

    public function isExpired(TrialAttempt $attempt): bool
    {
        return now()->greaterThanOrEqualTo($this->deadline($attempt));
    }

    public function restart(TrialExam $exam, TrialAttempt $attempt, User $reviewer): void
    {
        DB::transaction(function () use ($exam, $attempt, $reviewer): void {
            // Match the bank lock used by the student start endpoint.
            TrialQuestionBank::query()->lockForUpdate()->findOrFail($exam->trial_question_bank_id);
            $attempt = $exam->currentAttempts()->lockForUpdate()->findOrFail($attempt->id);

            $attempt->securityIncidents()->create([
                'category' => 'supervisor_restart',
                'severity' => 0,
                'occurred_at' => now(),
                'details' => [
                    'reviewer_id' => $reviewer->id,
                    'reviewer_name' => $reviewer->name,
                    'previous_status' => $attempt->status,
                ],
            ]);
            $attempt->update(['status' => TrialAttempt::RESET_STATUS]);
        }, 3);
    }

    public function saveAnswer(TrialAttempt $attempt, TrialQuestion $question, string $answer): void
    {
        if ($attempt->status === 'in_progress' && $this->isExpired($attempt)) {
            $this->submit($attempt, [], true);
            throw ValidationException::withMessages(['attempt' => 'Waktu ujian sudah habis.']);
        }

        DB::transaction(function () use ($attempt, $question, $answer): void {
            $attempt = TrialAttempt::query()->lockForUpdate()->with('trialExam')->findOrFail($attempt->id);
            $this->assertWritable($attempt);

            abort_unless(
                $question->trial_question_bank_id === $attempt->trialExam->trial_question_bank_id,
                404
            );

            $lookup = ['trial_attempt_id' => $attempt->id, 'trial_question_id' => $question->id];
            if (trim($answer) === '' && MultipleChoiceAnswers::allowsMultiple($question->correct_answer)) {
                TrialAnswer::query()->where($lookup)->delete();
            } else {
                if (! $question->question_type->requiresManualGrading()) {
                    $limit = MultipleChoiceAnswers::selectionLimit($question->correct_answer);
                    if (strlen(trim($answer)) > $limit) {
                        throw ValidationException::withMessages(['answer' => MultipleChoiceAnswers::limitMessage($limit)]);
                    }
                }

                $normalized = $this->normalizeAnswer($question, $answer);
                if ($normalized === null && ! $question->question_type->requiresManualGrading()) {
                    throw ValidationException::withMessages(['answer' => 'Pilihan jawaban tidak tersedia untuk soal ini.']);
                }

                TrialAnswer::updateOrCreate($lookup, ['answer' => $normalized]);
            }
            $attempt->update(['last_seen_at' => now()]);
        });
    }

    /** @param array<int|string, mixed> $submittedAnswers */
    public function submit(
        TrialAttempt $attempt,
        array $submittedAnswers = [],
        bool $allowLocked = false,
        bool $requireComplete = false,
    ): TrialAttempt {
        return DB::transaction(function () use ($attempt, $submittedAnswers, $allowLocked, $requireComplete): TrialAttempt {
            $attempt = TrialAttempt::query()->lockForUpdate()
                ->with('trialExam.questionBank')->findOrFail($attempt->id);

            if ($attempt->status === 'submitted') {
                return $attempt;
            }
            if ($attempt->status !== 'in_progress') {
                throw ValidationException::withMessages(['attempt' => 'Ujian uji coba sudah tidak aktif.']);
            }

            $expired = $this->isExpired($attempt);
            if ($attempt->security_locked_at && ! $allowLocked && ! $expired) {
                throw ValidationException::withMessages(['security' => 'Ujian terkunci. Hubungi panitia untuk pemeriksaan.']);
            }

            $questions = $attempt->trialExam->questionBank->questions()->orderBy('position')->get();
            $saved = $attempt->answers()->get()->keyBy('trial_question_id');
            $pgCount = 0;
            $correctAnswers = 0;
            $pgPoints = 0.0;
            $calculator = app(ExamScoreCalculator::class);
            $responses = [];
            $unanswered = 0;

            foreach ($questions as $question) {
                $raw = array_key_exists($question->id, $submittedAnswers)
                    ? $submittedAnswers[$question->id]
                    : $saved->get($question->id)?->answer;
                $answer = $this->normalizeAnswer($question, $raw);
                if ($requireComplete && ! $expired && $answer === null) {
                    $unanswered++;
                }
                $manual = $question->question_type->requiresManualGrading();
                $pgCount += $manual ? 0 : 1;
                $correct = $manual ? null : ($answer !== null && $answer === $question->correct_answer);
                $awarded = $manual ? null : $calculator->multipleChoicePoints($answer, $question->correct_answer);
                $pgPoints += $awarded ?? 0.0;

                $responses[] = [
                    'trial_attempt_id' => $attempt->id,
                    'trial_question_id' => $question->id,
                    'answer' => $answer,
                    'is_correct' => $correct,
                    'points_awarded' => $awarded,
                ];
                $correctAnswers += $correct ? 1 : 0;
            }

            if ($unanswered > 0) {
                throw ValidationException::withMessages(['submission' => "Masih ada {$unanswered} soal yang belum terjawab. Lengkapi semua jawaban sebelum mengumpulkan."]);
            }

            foreach (array_chunk($responses, 100) as $batch) {
                TrialAnswer::query()->upsert($batch, ['trial_attempt_id', 'trial_question_id'], ['answer', 'is_correct', 'points_awarded']);
            }

            $hasManual = $pgCount < $questions->count();
            $attempt->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'last_seen_at' => now(),
                'score' => $hasManual ? null : $calculator->multipleChoiceScore($pgPoints),
                'grading_status' => $hasManual ? GradingStatus::Pending : GradingStatus::Automatic,
                'correct_answers' => $correctAnswers,
                'total_questions' => $questions->count(),
                'multiple_choice_questions' => $pgCount,
            ]);

            return $attempt->fresh();
        });
    }

    public function gradeWrittenAnswers(TrialExam $exam, TrialAttempt $attempt, mixed $scores): void
    {
        DB::transaction(function () use ($exam, $attempt, $scores): void {
            $attempt = $exam->currentAttempts()->lockForUpdate()->where('status', 'submitted')->findOrFail($attempt->id);
            $questions = $exam->questionBank->questions()->get();
            $manualQuestions = $questions->filter(fn (TrialQuestion $question) => $question->question_type->requiresManualGrading());
            abort_if($manualQuestions->isEmpty(), 404);

            $rules = ['scores' => ['required', 'array']];
            foreach ($manualQuestions as $question) {
                $rules['scores.'.$question->id] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$question->points];
            }
            $validated = Validator::make(['scores' => $scores], $rules)->validate();
            $answers = $attempt->answers()->get()->keyBy('trial_question_id');
            foreach ($manualQuestions as $question) {
                $answer = $answers->get($question->id);
                $answer->update(['points_awarded' => $validated['scores'][$question->id]]);
            }

            $attempt->update([
                'score' => app(ExamScoreCalculator::class)->score($questions, $answers),
                'grading_status' => GradingStatus::Graded,
            ]);
        }, 3);
    }

    private function normalizeAnswer(TrialQuestion $question, ?string $answer): ?string
    {
        $answer = trim($answer ?? '');
        if ($question->question_type->requiresManualGrading()) {
            return $answer === '' ? null : $answer;
        }

        return MultipleChoiceAnswers::normalize(
            strtoupper($answer), $question->options ?? [], MultipleChoiceAnswers::selectionLimit($question->correct_answer),
        );
    }

    private function assertWritable(TrialAttempt $attempt): void
    {
        if ($attempt->status !== 'in_progress') {
            throw ValidationException::withMessages(['attempt' => 'Ujian uji coba sudah selesai.']);
        }
        if ($this->isExpired($attempt)) {
            throw ValidationException::withMessages(['attempt' => 'Waktu ujian sudah habis.']);
        }
        if ($attempt->security_locked_at) {
            throw ValidationException::withMessages(['security' => 'Ujian terkunci karena mencapai batas pelanggaran.']);
        }
    }
}
