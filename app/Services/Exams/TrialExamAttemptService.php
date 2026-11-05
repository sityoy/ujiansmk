<?php

namespace App\Services\Exams;

use App\Models\TrialAnswer;
use App\Models\TrialAttempt;
use App\Models\TrialQuestion;
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
            $totalPoints = (float) $questions->sum('points');
            $earnedPoints = 0.0;
            $correctAnswers = 0;

            foreach ($questions as $question) {
                $raw = array_key_exists($question->id, $submittedAnswers)
                    ? $submittedAnswers[$question->id]
                    : $saved->get($question->id)?->answer;
                $answer = filled($raw) ? Str::upper(trim((string) $raw)) : null;
                $correct = $answer !== null
                    && hash_equals(Str::upper(trim((string) $question->correct_answer)), $answer);
                $awarded = $correct ? (float) $question->points : 0.0;

                TrialAnswer::updateOrCreate(
                    ['trial_attempt_id' => $attempt->id, 'trial_question_id' => $question->id],
                    ['answer' => $answer, 'is_correct' => $correct, 'points_awarded' => $awarded]
                );
                $earnedPoints += $awarded;
                $correctAnswers += $correct ? 1 : 0;
            }

            $attempt->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'last_seen_at' => now(),
                'score' => $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0,
                'correct_answers' => $correctAnswers,
                'total_questions' => $questions->count(),
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

