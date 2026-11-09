<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use App\Models\TrialQuestion;
use App\Services\Exams\TrialExamAttemptService;
use App\Services\Exams\TrialExamSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TrialExamPublicController extends Controller
{
    public function entry(TrialExam $trialExam): View
    {
        $trialExam->load(['questionBank.subject']);

        return view('tryouts.entry', [
            'trialExam' => $trialExam,
            'available' => $trialExam->isAvailable(),
            'questionCount' => $this->questions($trialExam)->count(),
        ]);
    }

    public function start(Request $request, TrialExam $trialExam): RedirectResponse
    {
        $validated = $request->validate([
            'participant_identifier' => ['nullable', 'string', 'max:50'],
            'access_code' => ['required', 'string', 'max:50'],
        ]);

        if (! $trialExam->isAvailable()) {
            throw ValidationException::withMessages(['access_code' => 'Ujian uji coba belum dibuka atau sudah ditutup.']);
        }
        if (! hash_equals($trialExam->access_code, Str::upper(trim($validated['access_code'])))) {
            throw ValidationException::withMessages(['access_code' => 'Kode ujian uji coba tidak sesuai.']);
        }

        $student = $request->user()?->student;
        if ($student) {
            $student->loadMissing('schoolClass');
            if (! $student->is_active || $student->schoolClass?->grade_level !== $trialExam->questionBank->grade_level) {
                throw ValidationException::withMessages([
                    'participant_identifier' => 'Tingkat kelas akun siswa tidak sesuai dengan Ujian Uji Coba ini.',
                ]);
            }
        } else {
            $identifier = trim((string) ($validated['participant_identifier'] ?? ''));
            if ($identifier === '') {
                throw ValidationException::withMessages([
                    'participant_identifier' => 'NIS/NISN wajib diisi.',
                ]);
            }
            $student = Student::query()
                ->with('schoolClass')
                ->where('is_active', true)
                ->whereHas('schoolClass', fn ($query) => $query->where('grade_level', $trialExam->questionBank->grade_level))
                ->where(function ($query) use ($identifier): void {
                    $query->where('student_number', $identifier)
                        ->orWhere('nisn', $identifier);
                })
                ->first();

            if (! $student) {
                throw ValidationException::withMessages([
                    'participant_identifier' => 'NIS/NISN tidak ditemukan sebagai siswa aktif pada tingkat kelas ujian ini.',
                ]);
            }
        }

        $participantIdentifier = $student->nisn ?: $student->student_number;
        $existingAttempt = TrialAttempt::query()
            ->where('trial_exam_id', $trialExam->id)
            ->where(function ($query) use ($student, $participantIdentifier): void {
                $query->where('student_id', $student->id)
                    ->orWhere('participant_identifier', $participantIdentifier);
            })
            ->first();

        if ($existingAttempt) {
            if ($request->user()?->student?->id === $student->id && $existingAttempt->status === 'in_progress') {
                $token = Str::random(64);
                $existingAttempt->update([
                    'student_id' => $student->id,
                    'session_token_hash' => hash('sha256', $token),
                    'last_seen_at' => now(),
                ]);
                session()->put($this->tokenKey($existingAttempt), $token);

                return redirect()->route('tryout.show', [$trialExam, $existingAttempt]);
            }

            throw ValidationException::withMessages([
                'participant_identifier' => 'Siswa ini sudah pernah memulai Ujian Uji Coba tersebut.',
            ]);
        }

        $questions = $this->questions($trialExam);
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['access_code' => 'Bank soal pilihan ganda belum tersedia.']);
        }

        $token = Str::random(64);
        $attempt = TrialAttempt::create([
            'trial_exam_id' => $trialExam->id,
            'student_id' => $student->id,
            'participant_name' => $student->full_name,
            'participant_identifier' => $participantIdentifier,
            'session_token_hash' => hash('sha256', $token),
            'status' => 'in_progress',
            'started_at' => now(),
            'last_seen_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'total_questions' => $questions->count(),
        ]);

        session()->put($this->tokenKey($attempt), $token);

        return redirect()->route('tryout.show', [$trialExam, $attempt]);
    }

    public function show(
        TrialExam $trialExam,
        TrialAttempt $trialAttempt,
        TrialExamAttemptService $attempts,
    ): View|RedirectResponse {
        $this->authorizeAttempt($trialExam, $trialAttempt);
        $trialExam->loadMissing('questionBank.subject');

        if ($trialAttempt->status === 'in_progress' && $attempts->isExpired($trialAttempt)) {
            $attempts->submit($trialAttempt, [], true);
        }
        if ($trialAttempt->fresh()->status === 'submitted') {
            return redirect()->route('tryout.result', [$trialExam, $trialAttempt]);
        }

        $trialAttempt->load('answers');
        $questions = $this->questions($trialExam);
        // Attempts started before fixed ordering keep the numbering already shown to students.
        $order = session()->get($this->orderKey($trialAttempt), []);
        if ($order !== []) {
            $positions = array_flip($order);
            $questions = $questions->sortBy(fn ($question) => $positions[$question->id] ?? PHP_INT_MAX)->values();
        }

        return view('tryouts.show', [
            'trialExam' => $trialExam,
            'attempt' => $trialAttempt,
            'questions' => $questions,
            'answers' => $trialAttempt->answers->keyBy('trial_question_id'),
            'deadline' => $attempts->deadline($trialAttempt),
        ]);
    }

    public function answer(
        Request $request,
        TrialExam $trialExam,
        TrialAttempt $trialAttempt,
        TrialQuestion $question,
        TrialExamAttemptService $attempts,
    ): JsonResponse {
        $this->authorizeAttempt($trialExam, $trialAttempt);
        $validated = $request->validate(['answer' => ['required', 'string', 'max:10']]);
        $attempts->saveAnswer($trialAttempt, $question, $validated['answer']);

        return response()->json(['saved' => true, 'saved_at' => now()->format('H:i:s')]);
    }

    public function submit(
        Request $request,
        TrialExam $trialExam,
        TrialAttempt $trialAttempt,
        TrialExamAttemptService $attempts,
    ): RedirectResponse {
        $this->authorizeAttempt($trialExam, $trialAttempt);
        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:10'],
        ]);
        $attempts->submit($trialAttempt, $validated['answers'] ?? []);
        session()->forget($this->orderKey($trialAttempt));

        return redirect()->route('tryout.result', [$trialExam, $trialAttempt]);
    }

    public function incident(
        Request $request,
        TrialExam $trialExam,
        TrialAttempt $trialAttempt,
        TrialExamSecurityService $security,
    ): JsonResponse {
        $this->authorizeAttempt($trialExam, $trialAttempt);
        $validated = $request->validate([
            'category' => ['required', Rule::in(TrialExamSecurityService::CLIENT_CATEGORIES)],
            'event_id' => ['nullable', 'uuid'],
            'context' => ['nullable', 'array:viewport_width,viewport_height,screen_width,screen_height,pixel_ratio'],
            'context.viewport_width' => ['nullable', 'integer', 'between:0,10000'],
            'context.viewport_height' => ['nullable', 'integer', 'between:0,10000'],
            'context.screen_width' => ['nullable', 'integer', 'between:0,10000'],
            'context.screen_height' => ['nullable', 'integer', 'between:0,10000'],
            'context.pixel_ratio' => ['nullable', 'numeric', 'between:0.1,20'],
        ]);
        $state = $security->record(
            $trialAttempt,
            $validated['category'],
            $validated['event_id'] ?? (string) Str::uuid(),
            $validated['context'] ?? [],
        );

        return response()->json(['recorded' => true, ...$state])->header('Cache-Control', 'no-store');
    }

    public function securityState(
        TrialExam $trialExam,
        TrialAttempt $trialAttempt,
        TrialExamAttemptService $attempts,
        TrialExamSecurityService $security,
    ): JsonResponse {
        $this->authorizeAttempt($trialExam, $trialAttempt);
        if ($trialAttempt->status === 'in_progress' && $attempts->isExpired($trialAttempt)) {
            $trialAttempt = $attempts->submit($trialAttempt, [], true);
        }

        return response()->json($security->state($trialAttempt->fresh()))->header('Cache-Control', 'no-store');
    }

    public function result(TrialExam $trialExam, TrialAttempt $trialAttempt): View
    {
        $this->authorizeAttempt($trialExam, $trialAttempt);
        abort_unless($trialAttempt->status === 'submitted', 404);

        return view('tryouts.result', compact('trialExam', 'trialAttempt'));
    }

    private function questions(TrialExam $trialExam)
    {
        return $trialExam->questionBank->questions()->get();
    }

    private function authorizeAttempt(TrialExam $trialExam, TrialAttempt $trialAttempt): void
    {
        abort_unless($trialAttempt->trial_exam_id === $trialExam->id, 404);

        $authenticatedStudent = auth()->user()?->student;
        if ($authenticatedStudent && (
            $trialAttempt->student_id === $authenticatedStudent->id
            || in_array($trialAttempt->participant_identifier, array_filter([
                $authenticatedStudent->nisn,
                $authenticatedStudent->student_number,
            ]), true)
        )) {
            if (! $trialAttempt->student_id) {
                $trialAttempt->update(['student_id' => $authenticatedStudent->id]);
            }

            return;
        }

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

