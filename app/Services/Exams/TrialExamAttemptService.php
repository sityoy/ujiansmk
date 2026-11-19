<?php

namespace App\Services\Exams;

use App\Models\TrialAnswer;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use App\Models\TrialQuestion;
use App\Models\TrialQuestionBank;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrialExamAttemptService
{
    public function deadline(TrialAttempt $attempt): Carbon
    {
        $attempt->loadMissing('trialExam');

        return $attempt->started_at->copy()->addMinutes($attempt->trialExam->duration_minutes);
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

            TrialAnswer::updateOrCreate(
                ['trial_attempt_id' => $attempt->id, 'trial_question_id' => $question->id],
                ['answer' => Str::upper(trim($answer))]
            );
            $attempt->update(['last_seen_at' => now()]);
        });
    }

    /** @param array<int|string, mixed> $submittedAnswers */
    public function submit(TrialAttempt $attempt, array $submittedAnswers = [], bool $allowLocked = false): TrialAttempt
    {
        return DB::transaction(function () use ($attempt, $submittedAnswers, $allowLocked): TrialAttempt {
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
            $questionCount = $questions->count();
            $questionWeight = ExamScoreCalculator::MULTIPLE_CHOICE_POINTS;
            $correctAnswers = 0;
            $responses = [];

            foreach ($questions as $question) {
                $raw = array_key_exists($question->id, $submittedAnswers)
                    ? $submittedAnswers[$question->id]
                    : $saved->get($question->id)?->answer;
                $answer = filled($raw) ? Str::upper(trim((string) $raw)) : null;
                $correct = $answer !== null
                    && hash_equals(Str::upper(trim((string) $question->correct_answer)), $answer);
                $awarded = $correct ? $questionWeight : 0.0;

                $responses[] = [
                    'trial_attempt_id' => $attempt->id,
                    'trial_question_id' => $question->id,
                    'answer' => $answer,
                    'is_correct' => $correct,
                    'points_awarded' => $awarded,
                ];
                $correctAnswers += $correct ? 1 : 0;
            }

            foreach (array_chunk($responses, 100) as $batch) {
                TrialAnswer::query()->upsert($batch, ['trial_attempt_id', 'trial_question_id'], ['answer', 'is_correct', 'points_awarded']);
            }

            $attempt->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'last_seen_at' => now(),
                'score' => app(ExamScoreCalculator::class)->multipleChoiceScore($correctAnswers, $questionCount),
                'correct_answers' => $correctAnswers,
                'total_questions' => $questionCount,
            ]);

            return $attempt->fresh();
        });
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
