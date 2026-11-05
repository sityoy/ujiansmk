<?php

namespace App\Http\Controllers;

use App\Enums\ExamQuestionType;
use App\Models\TrialAnswer;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TrialExamPublicController extends Controller
{
    public function entry(TrialExam $trialExam): View
    {
        $trialExam->load(['assessmentSubject.subject', 'assessmentSubject.schoolClass', 'assessmentSubject.assessmentPeriod']);

        return view('tryouts.entry', [
            'trialExam' => $trialExam,
            'available' => $trialExam->isAvailable(),
            'questionCount' => $this->questions($trialExam)->count(),
        ]);
    }

    public function start(Request $request, TrialExam $trialExam): RedirectResponse
    {
        $validated = $request->validate([
            'participant_name' => ['required', 'string', 'max:120'],
            'participant_identifier' => ['nullable', 'string', 'max:50'],
            'access_code' => ['required', 'string', 'max:50'],
        ]);

        if (! $trialExam->isAvailable()) {
            throw ValidationException::withMessages(['access_code' => 'Ujian uji coba belum dibuka atau sudah ditutup.']);
        }

        if (! hash_equals($trialExam->access_code, Str::upper(trim($validated['access_code'])))) {
            throw ValidationException::withMessages(['access_code' => 'Kode ujian uji coba tidak sesuai.']);
        }

        $questions = $this->questions($trialExam);
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['access_code' => 'Bank soal pilihan ganda belum tersedia.']);
        }

        $token = Str::random(64);
        $attempt = TrialAttempt::create([
            'trial_exam_id' => $trialExam->id,
            'participant_name' => $validated['participant_name'],
            'participant_identifier' => $validated['participant_identifier'] ?? null,
            'session_token_hash' => hash('sha256', $token),
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => $questions->count(),
        ]);

        session()->put($this->tokenKey($attempt), $token);
        session()->put($this->orderKey($attempt), $questions->pluck('id')->shuffle()->all());

        return redirect()->route('tryout.show', [$trialExam, $attempt]);
    }

    public function show(TrialExam $trialExam, TrialAttempt $trialAttempt): View|RedirectResponse
    {
        $this->authorizeAttempt($trialExam, $trialAttempt);

        if ($trialAttempt->status === 'submitted') {
            return redirect()->route('tryout.result', [$trialExam, $trialAttempt]);
        }

        $order = session()->get($this->orderKey($trialAttempt), []);
        $positions = array_flip($order);
        $questions = $this->questions($trialExam)
            ->sortBy(fn ($question) => $positions[$question->id] ?? PHP_INT_MAX)
            ->values();

        return view('tryouts.show', [
            'trialExam' => $trialExam,
            'attempt' => $trialAttempt,
            'questions' => $questions,
            'remainingSeconds' => max(0, now()->diffInSeconds(
                $trialAttempt->started_at->copy()->addMinutes($trialExam->duration_minutes),
                false
            )),
        ]);
    }

    public function submit(Request $request, TrialExam $trialExam, TrialAttempt $trialAttempt): RedirectResponse
    {
        $this->authorizeAttempt($trialExam, $trialAttempt);

        if ($trialAttempt->status === 'submitted') {
            return redirect()->route('tryout.result', [$trialExam, $trialAttempt]);
        }

        $answers = $request->input('answers', []);
        $questions = $this->questions($trialExam);
        $totalPoints = (float) $questions->sum('points');
        $earnedPoints = 0.0;
        $correctAnswers = 0;

        DB::transaction(function () use ($questions, $answers, $trialAttempt, $totalPoints, &$earnedPoints, &$correctAnswers): void {
            foreach ($questions as $question) {
                $answer = isset($answers[$question->id]) ? Str::upper(trim((string) $answers[$question->id])) : null;
                $correct = $answer !== null
                    && hash_equals(Str::upper(trim((string) $question->correct_answer)), $answer);
                $awarded = $correct ? (float) $question->points : 0.0;

                TrialAnswer::updateOrCreate(
                    ['trial_attempt_id' => $trialAttempt->id, 'exam_question_id' => $question->id],
                    ['answer' => $answer, 'is_correct' => $correct, 'points_awarded' => $awarded]
                );

                $earnedPoints += $awarded;
                $correctAnswers += $correct ? 1 : 0;
            }

            $trialAttempt->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'score' => $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0,
                'correct_answers' => $correctAnswers,
                'total_questions' => $questions->count(),
            ]);
        });

        session()->forget($this->orderKey($trialAttempt));

        return redirect()->route('tryout.result', [$trialExam, $trialAttempt]);
    }

    public function result(TrialExam $trialExam, TrialAttempt $trialAttempt): View
    {
        $this->authorizeAttempt($trialExam, $trialAttempt);
        abort_unless($trialAttempt->status === 'submitted', 404);

        return view('tryouts.result', compact('trialExam', 'trialAttempt'));
    }

    private function questions(TrialExam $trialExam)
    {
        return $trialExam->assessmentSubject->questions()
            ->where('question_type', ExamQuestionType::MultipleChoice->value)
            ->orderBy('position')
            ->get();
    }

    private function authorizeAttempt(TrialExam $trialExam, TrialAttempt $trialAttempt): void
    {
        abort_unless($trialAttempt->trial_exam_id === $trialExam->id, 404);
        $token = (string) session()->get($this->tokenKey($trialAttempt));
        abort_unless($token !== '' && hash_equals($trialAttempt->session_token_hash, hash('sha256', $token)), 403);
    }

    private function tokenKey(TrialAttempt $attempt): string
    {
        return 'trial_attempt_token_'.$attempt->id;
    }

    private function orderKey(TrialAttempt $attempt): string
    {
        return 'trial_question_order_'.$attempt->id;
    }
}
