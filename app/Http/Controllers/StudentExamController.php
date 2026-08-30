<?php

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Enums\CheckinStatus;
use App\Models\DailyCheckin;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\SecurityIncident;
use App\Models\Student;
use App\Services\Exams\ExamAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentExamController extends Controller
{
    public function index(Request $request): View
    {
        $student = $this->student($request);
        $assignments = $student->examAssignments()
            ->with([
                'assessmentSubject.assessmentPeriod',
                'assessmentSubject.subject',
                'examSession.campus',
                'attempt',
            ])
            ->whereHas('examSession', fn ($query) => $query->orderBy('starts_at'))
            ->get()
            ->sortBy('examSession.starts_at');

        return view('student.exams.index', [
            'student' => $student,
            'assignments' => $assignments,
            'checkin' => $student->dailyCheckins()
                ->whereDate('attendance_date', now()->toDateString())
                ->first(),
        ]);
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

        if ($attempt->status === AttemptStatus::Submitted) {
            return redirect()->route('student.exams.index')->with('status', 'Ujian tersebut sudah selesai.');
        }

        return redirect()->route('student.exams.show', $attempt);
    }

    public function show(Request $request, ExamAttempt $attempt, ExamAttemptService $attempts): View|RedirectResponse
    {
        $student = $this->student($request);
        $this->assertAttemptOwner($attempt, $student);
        $attempts->assertDevice($attempt, $this->deviceHash($request));

        if ($attempt->status === AttemptStatus::Submitted) {
            return redirect()->route('student.exams.index')->with('status', 'Ujian tersebut sudah selesai.');
        }

        if ($attempts->isExpired($attempt)) {
            $attempts->submit($attempt);

            return redirect()->route('student.exams.index')->with('status', 'Waktu habis. Jawaban dikumpulkan otomatis.');
        }

        $attempt->load([
            'assignment.assessmentSubject.assessmentPeriod',
            'assignment.assessmentSubject.subject',
            'assignment.assessmentSubject.questions',
            'answers',
        ]);

        return view('student.exams.show', [
            'attempt' => $attempt,
            'questions' => $attempt->assignment->assessmentSubject->questions,
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
        $validated = $request->validate(['answer' => ['required', Rule::in(['A', 'B', 'C', 'D'])]]);
        $attempts->saveAnswer($attempt, $question, $validated['answer']);

        return response()->json(['saved' => true, 'saved_at' => now()->format('H:i:s')]);
    }

    public function submit(Request $request, ExamAttempt $attempt, ExamAttemptService $attempts): RedirectResponse
    {
        $student = $this->student($request);
        $this->assertAttemptOwner($attempt, $student);
        $attempts->assertDevice($attempt, $this->deviceHash($request));
        $attempts->submit($attempt);

        return redirect()->route('student.exams.index')->with('status', 'Jawaban berhasil dikumpulkan.');
    }

    public function incident(
        Request $request,
        ExamAttempt $attempt,
        ExamAttemptService $attempts,
    ): JsonResponse
    {
        $student = $this->student($request);
        $this->assertAttemptOwner($attempt, $student);
        abort_unless($attempt->status === AttemptStatus::InProgress, 409, 'Ujian sudah tidak berlangsung.');
        $attempts->assertDevice($attempt, $this->deviceHash($request));

        $validated = $request->validate([
            'category' => ['required', Rule::in(['tab_hidden', 'fullscreen_exit'])],
        ]);

        SecurityIncident::create([
            'exam_attempt_id' => $attempt->id,
            'category' => $validated['category'],
            'details' => ['user_agent' => $request->userAgent()],
            'severity' => 1,
            'occurred_at' => now(),
        ]);
        $attempt->increment('violation_count');

        return response()->json(['recorded' => true]);
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
        return hash('sha256', $request->session()->getId());
    }
}
