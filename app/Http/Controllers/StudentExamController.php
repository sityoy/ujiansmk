<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\CheckinStatus;
use App\Enums\ExamQuestionType;
use App\Enums\SessionStatus;
use App\Models\DailyCheckin;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Student;
use App\Services\Attendance\AttendanceAvailability;
use App\Services\Exams\ExamAttemptService;
use App\Services\Exams\ExamSecurityService;
use App\Services\Exams\StudentQuestionSections;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentExamController extends Controller
{
    public function index(Request $request, AttendanceAvailability $attendance, ExamAttemptService $attempts): View
    {
        $student = $this->student($request);
        $student->loadMissing('schoolClass');
        $checkin = $student->dailyCheckins()->whereDate('attendance_date', now()->toDateString())->first();
        $order = ['working' => 0, 'ready' => 0, 'checkin' => 0, 'locked' => 0, 'waiting' => 1];

        $exams = $student->examAssignments()
            ->with([
                'assessmentSubject' => fn ($component) => $component->with(['assessmentPeriod', 'subject'])
                    ->withExists([
                        'questions as has_multiple_choice' => fn ($questions) => $questions->where('question_type', ExamQuestionType::MultipleChoice),
                    ]),
                'examSession.campus',
                'attempt',
            ])
            ->get()
            ->map(function (ExamAssignment $assignment) use ($attendance, $checkin, $attempts): array {
                $reason = $attendance->reason($assignment, $checkin);

                return ['assignment' => $assignment, 'attendanceReason' => $reason,
                    'state' => $this->portalState($assignment, $checkin, $reason, $attempts)];
            })
            ->sortBy([
                fn (array $a, array $b) => ($order[$a['state']] ?? 2) <=> ($order[$b['state']] ?? 2),
                fn (array $a, array $b) => $a['assignment']->examSession->starts_at <=> $b['assignment']->examSession->starts_at,
            ]);

        return view('student.exams.index', compact('student', 'exams', 'checkin'));
    }

    private function portalState(ExamAssignment $assignment, ?DailyCheckin $checkin, ?string $attendanceReason, ExamAttemptService $attempts): string
    {
        $session = $assignment->examSession;
        $attempt = $assignment->attempt;
        if ($attempt?->status === AttemptStatus::InProgress) {
            $attempt->setRelation('assignment', $assignment);

            return match (true) {
                $attempts->isExpired($attempt) => 'expired',
                $attempt->security_locked_at !== null => 'locked',
                default => 'working',
            };
        }

        return match (true) {
            $attempt?->status === AttemptStatus::Submitted || $assignment->status === AssignmentStatus::Completed => 'done',
            $attempt?->status === AttemptStatus::Terminated => 'terminated',
            $assignment->status === AssignmentStatus::Cancelled => 'cancelled',
            $assignment->status === AssignmentStatus::Absent => 'absent',
            $session->status === SessionStatus::Closed || now()->gte($session->ends_at) => 'closed',
            $session->status === SessionStatus::Draft || now()->lt($session->starts_at) => 'waiting',
            $attendanceReason !== null => 'blocked',
            $checkin === null => 'checkin',
            default => 'ready',
        };
    }

    public function start(Request $request, ExamAssignment $assignment, ExamAttemptService $attempts): RedirectResponse
    {
        $student = $this->student($request);
        $this->assertAssignmentOwner($assignment, $student);
        $assignment->load('examSession');
        $checkin = DailyCheckin::query()
            ->where('student_id', $student->id)
            ->where('campus_id', $assignment->examSession->campus_id)
            ->whereDate('attendance_date', now()->toDateString())
            ->where('status', CheckinStatus::Verified)
            ->first();

        if (! $checkin) {
            return back()->withErrors(['attendance' => 'Lakukan absensi terverifikasi terlebih dahulu.']);
        }

        $attempt = $attempts->start(
            $assignment,
            $checkin,
            $this->deviceHash($request),
            $request->ip(),
            $request->userAgent(),
        );

        if ($attempt->status !== AttemptStatus::InProgress) {
            return redirect()->route('student.exams.index')->with('status', 'Ujian tersebut sudah selesai.');
        }

        return redirect()->route('student.exams.show', $attempt);
    }

    public function show(Request $request, ExamAttempt $attempt, ExamAttemptService $attempts): View|RedirectResponse
    {
        $student = $this->student($request);
        $this->assertAttemptOwner($attempt, $student);
        $attempts->assertDevice($attempt, $this->deviceHash($request));
        $student->loadMissing('schoolClass');

        if ($attempt->status !== AttemptStatus::InProgress) {
            if ($attempt->status === AttemptStatus::Submitted) {
                return redirect()->route('student.exams.result', $attempt);
            }

            return redirect()->route('student.exams.index')->with('status', 'Ujian tersebut sudah selesai.');
        }

        if ($attempts->isExpired($attempt)) {
            $attempts->submit($attempt);

            return redirect()->route('student.exams.result', $attempt);
        }

        $attempt->load([
            'assignment.assessmentSubject.assessmentPeriod',
            'assignment.assessmentSubject.subject',
            'assignment.assessmentSubject.questions',
            'answers',
        ]);

        $questions = $attempt->assignment->assessmentSubject->questions;

        return view($attempt->security_locked_at ? 'student.exams.locked' : 'student.exams.show', [
            'student' => $student,
            'attempt' => $attempt,
            'questions' => $questions,
            'questionSections' => StudentQuestionSections::fromQuestions($questions),
            'answers' => $attempt->answers->keyBy('exam_question_id'),
            'deadline' => $attempts->deadline($attempt),
        ]);
    }

    public function answer(
        Request $request,
        ExamAttempt $attempt,
        ExamQuestion $question,
        ExamAttemptService $attempts,
    ): JsonResponse {
        $student = $this->student($request);
        $this->assertAttemptOwner($attempt, $student);
        $attempts->assertDevice($attempt, $this->deviceHash($request));
        $validated = $request->validate(['answer' => ['required', 'string', 'max:10000']]);
        $attempts->saveAnswer($attempt, $question, $validated['answer']);

        return response()->json(['saved' => true, 'saved_at' => now()->format('H:i:s')]);
    }

    public function submit(Request $request, ExamAttempt $attempt, ExamAttemptService $attempts): RedirectResponse
    {
        $student = $this->student($request);
        $this->assertAttemptOwner($attempt, $student);
        $attempts->assertDevice($attempt, $this->deviceHash($request));
        $attempts->submit($attempt, requireComplete: true);

        return redirect()->route('student.exams.result', $attempt);
    }

    public function result(Request $request, ExamAttempt $attempt): View|RedirectResponse
    {
        $student = $this->student($request);
        $this->assertAttemptOwner($attempt, $student);

        if ($attempt->status === AttemptStatus::InProgress) {
            return redirect()->route('student.exams.show', $attempt);
        }
        if ($attempt->status !== AttemptStatus::Submitted) {
            return redirect()->route('student.exams.index')->with('status', 'Ujian tersebut sudah berakhir.');
        }

        $attempt->loadMissing('assignment.assessmentSubject.subject');

        return view('student.exams.result', compact('student', 'attempt'));
    }

    public function incident(
        Request $request,
        ExamAttempt $attempt,
        ExamAttemptService $attempts,
        ExamSecurityService $security,
    ): JsonResponse
    {
        $student = $this->student($request);
        $this->assertAttemptOwner($attempt, $student);
        $attempts->assertDevice($attempt, $this->deviceHash($request));

        $validated = $request->validate([
            'category' => ['required', Rule::in(ExamSecurityService::CLIENT_CATEGORIES)],
            'event_id' => ['nullable', 'uuid'],
            'context' => ['nullable', 'array:viewport_width,viewport_height,screen_width,screen_height,pixel_ratio'],
            'context.viewport_width' => ['nullable', 'integer', 'between:0,10000'],
            'context.viewport_height' => ['nullable', 'integer', 'between:0,10000'],
            'context.screen_width' => ['nullable', 'integer', 'between:0,10000'],
            'context.screen_height' => ['nullable', 'integer', 'between:0,10000'],
            'context.pixel_ratio' => ['nullable', 'numeric', 'between:0.1,20'],
        ]);

        $state = $security->record(
            $attempt,
            $validated['category'],
            $validated['event_id'] ?? (string) Str::uuid(),
            $validated['context'] ?? [],
        );

        return response()->json(['recorded' => true, ...$state])->header('Cache-Control', 'no-store');
    }

    public function securityState(Request $request, ExamAttempt $attempt, ExamAttemptService $attempts, ExamSecurityService $security): JsonResponse
    {
        $this->assertAttemptOwner($attempt, $this->student($request));
        $attempts->assertDevice($attempt, $this->deviceHash($request));
        if ($attempt->status === AttemptStatus::InProgress && $attempts->isExpired($attempt)) {
            $attempt = $attempts->submit($attempt);
        }

        return response()->json($security->state($attempt))->header('Cache-Control', 'no-store');
    }

    private function student(Request $request): Student
    {
        return $request->user()->student ?: abort(403, 'Akun siswa belum terhubung dengan data peserta.');
    }

    private function assertAssignmentOwner(ExamAssignment $assignment, Student $student): void
    {
        abort_unless($assignment->student_id === $student->id, 403);
    }

    private function assertAttemptOwner(ExamAttempt $attempt, Student $student): void
    {
        $attempt->loadMissing('assignment');
        abort_unless($attempt->assignment->student_id === $student->id, 403);
    }

    private function deviceHash(Request $request): string
    {
        $token = $request->session()->get('exam_device_token');

        if (! is_string($token) || strlen($token) < 32) {
            $token = Str::random(64);
            $request->session()->put('exam_device_token', $token);
        }

        return hash('sha256', $token);
    }
}
