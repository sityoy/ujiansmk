<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\TrialAttempt;
use App\Models\TrialCheckin;
use App\Models\TrialExam;
use App\Models\TrialLocation;
use App\Models\TrialQuestion;
use App\Models\TrialQuestionBank;
use App\Services\Attendance\Geofence;
use App\Services\Exams\StudentQuestionSections;
use App\Services\Exams\TrialExamAttemptService;
use App\Services\Exams\TrialExamSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TrialExamPublicController extends Controller
{
    public function entry(TrialExam $trialExam): View
    {
        $trialExam->load(['questionBank.subject', 'location']);

        $counts = $trialExam->questionBank->questions()->reorder()
            ->selectRaw('question_type, count(*) as total')->groupBy('question_type')->pluck('total', 'question_type');

        return view('tryouts.entry', [
            'trialExam' => $trialExam,
            'available' => $trialExam->isAvailable(),
            'questionCount' => $counts->sum(),
            'pgCount' => (int) $counts->get('multiple_choice', 0),
            'shortCount' => (int) $counts->get('short_answer', 0),
            'essayCount' => (int) $counts->get('essay', 0),
        ]);
    }

    public function start(Request $request, TrialExam $trialExam): RedirectResponse
    {
        [$student, $accessCode] = $this->entryParticipant($request, $trialExam);

        return DB::transaction(fn () => $this->startForStudent($request, $trialExam, $student, $accessCode), 3);
    }

    public function checkIn(Request $request, TrialExam $trialExam, Geofence $geofence): JsonResponse
    {
        [$student, $accessCode] = $this->entryParticipant($request, $trialExam);
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['required', 'numeric', 'between:0,5000'],
        ]);

        $checkin = DB::transaction(function () use ($trialExam, $student, $accessCode, $coordinates, $geofence): TrialCheckin {
            $bank = TrialQuestionBank::query()->sharedLock()->findOrFail($trialExam->trial_question_bank_id);
            $exam = TrialExam::query()->sharedLock()->findOrFail($trialExam->id)->setRelation('questionBank', $bank);
            $this->validateEntry($exam, $accessCode);
            if (! $exam->trial_location_id) {
                throw ValidationException::withMessages(['location' => 'Ujian ini tidak menggunakan absensi lokasi. Muat ulang halaman ujian.']);
            }

            $location = TrialLocation::query()->sharedLock()->findOrFail($exam->trial_location_id);
            $distance = $geofence->verify(
                $location,
                (float) $coordinates['latitude'],
                (float) $coordinates['longitude'],
                (float) $coordinates['accuracy_meters'],
            );
            $student = Student::query()->lockForUpdate()->findOrFail($student->id);
            $this->validateGrade($student, $exam);

            return $exam->checkins()->firstOrCreate(['student_id' => $student->id], [
                'class_name' => $student->schoolClass->name,
                'distance_meters' => round($distance, 2),
                'accuracy_meters' => $coordinates['accuracy_meters'],
                'verified_at' => now(),
            ]);
        }, 3);

        $request->session()->put('trial_checkin.'.$trialExam->id, $checkin->id);

        return response()->json(['message' => 'Absensi simulasi terverifikasi. Anda bisa mulai ujian.'])->header('Cache-Control', 'no-store');
    }

    /** @return array{Student, string} */
    private function entryParticipant(Request $request, TrialExam $trialExam): array
    {
        $validated = $request->validate([
            'participant_identifier' => ['nullable', 'string', 'max:50'],
            'access_code' => ['required', 'string', 'max:50'],
        ]);

        $accessCode = Str::upper(trim($validated['access_code']));
        $this->validateEntry($trialExam, $accessCode);

        $student = $request->user()?->student;
        if ($student) {
            $this->validateGrade($student, $trialExam);
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

        return [$student, $accessCode];
    }

    private function validateGrade(Student $student, TrialExam $trialExam): void
    {
        if (! $student->is_active || $student->schoolClass?->grade_level !== $trialExam->questionBank->grade_level) {
            throw ValidationException::withMessages([
                'participant_identifier' => 'Data atau tingkat kelas siswa tidak sesuai dengan ujian ini. Hubungi panitia.',
            ]);
        }
    }

    private function validateEntry(TrialExam $trialExam, string $accessCode): void
    {
        if (! $trialExam->isAvailable()) {
            throw ValidationException::withMessages(['access_code' => 'Ujian uji coba belum dibuka atau sudah ditutup.']);
        }
        if (! hash_equals($trialExam->access_code, $accessCode)) {
            throw ValidationException::withMessages(['access_code' => 'Kode ujian uji coba tidak sesuai.']);
        }
    }

    private function startForStudent(Request $request, TrialExam $trialExam, Student $student, string $accessCode): RedirectResponse
    {
        $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($trialExam->trial_question_bank_id);
        $trialExam->refresh()->setRelation('questionBank', $bank);
        $this->validateEntry($trialExam, $accessCode);

        // Serialize starts for this student, including simultaneous requests on another device.
        $student = Student::query()->lockForUpdate()->findOrFail($student->id);
        $this->validateGrade($student, $trialExam);
        $participantIdentifier = $student->nisn ?: $student->student_number;
        $existingAttempt = $trialExam->currentAttempts()
            ->where(function ($query) use ($student, $participantIdentifier): void {
                $query->where('student_id', $student->id)
                    ->orWhere('participant_identifier', $participantIdentifier);
            })
            ->lockForUpdate()->first();

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

        if ($trialExam->trial_location_id && ! $trialExam->checkins()
            ->whereKey($request->session()->get('trial_checkin.'.$trialExam->id))
            ->where('student_id', $student->id)->exists()) {
            throw ValidationException::withMessages(['location' => 'Selesaikan absensi lokasi simulasi di browser ini sebelum mulai ujian.']);
        }

        $questionCount = $trialExam->questionBank->questions()->count();
        if ($questionCount === 0) {
            throw ValidationException::withMessages(['access_code' => 'Bank soal uji coba belum tersedia.']);
        }

        $token = Str::random(64);
        $attempt = TrialAttempt::create([
            'trial_exam_id' => $trialExam->id,
            'student_id' => $student->id,
            'participant_name' => $student->full_name,
            'participant_identifier' => $participantIdentifier,
            'session_token_hash' => hash('sha256', $token),
            'status' => 'in_progress',
            'duration_minutes' => $trialExam->duration_minutes,
            'started_at' => now(),
            'last_seen_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'total_questions' => $questionCount,
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
            'questionSections' => StudentQuestionSections::fromQuestions($questions),
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
        $validated = $request->validate(['answer' => ['present', 'nullable', 'string', 'max:10000']]);
        $attempts->saveAnswer($trialAttempt, $question, $validated['answer'] ?? '');

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
            'answers.*' => ['nullable', 'string', 'max:10000'],
        ]);
        $attempts->submit($trialAttempt, $validated['answers'] ?? [], requireComplete: true);
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
        $trialAttempt->setRelation('trialExam', $trialExam);
        if ($trialAttempt->status === 'in_progress' && $attempts->isExpired($trialAttempt)) {
            $trialAttempt = $attempts->submit($trialAttempt, [], true)->setRelation('trialExam', $trialExam);
        }

        return response()->json($security->state($trialAttempt))->header('Cache-Control', 'no-store');
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
        abort_unless($trialAttempt->trial_exam_id === $trialExam->id && $trialAttempt->status !== TrialAttempt::RESET_STATUS, 404);

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
