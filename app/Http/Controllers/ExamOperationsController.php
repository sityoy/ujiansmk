<?php

namespace App\Http\Controllers;

use App\Enums\SessionStatus;
use App\Enums\AttemptStatus;
use App\Enums\AssignmentStatus;
use App\Models\AcademicYear;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Services\Exams\ExamSessionService;
use App\Services\Exams\ExamSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExamOperationsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(SessionStatus::class)],
        ]);
        $sessions = ExamSession::query()
            ->when($filters['academic_year_id'] ?? null, fn ($query, $year) => $query
                ->whereHas('assessmentSubject.assessmentPeriod', fn ($period) => $period->where('academic_year_id', $year)))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        $attempts = ExamAttempt::query()->whereHas('assignment', fn ($query) => $query
            ->whereIn('exam_session_id', (clone $sessions)->select('id')));

        return view('operations.index', [
            'filters' => $filters,
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'sessions' => (clone $sessions)
                ->with(['assessmentSubject.assessmentPeriod', 'assessmentSubject.subject', 'assessmentSubject.schoolClass', 'campus'])
                ->withCount('assignments')
                ->orderByDesc('starts_at')
                ->paginate(15, ['*'], 'sessions_page')->withQueryString(),
            'attempts' => (clone $attempts)
                ->with(['assignment.student', 'assignment.assessmentSubject.subject', 'assignment.examSession'])
                ->latest('started_at')
                ->orderByDesc('id')
                ->paginate(30, ['*'], 'attempts_page')->withQueryString(),
            'sessionStatuses' => SessionStatus::cases(),
            'statistics' => [
                'activeSessions' => (clone $sessions)->where('status', SessionStatus::Active)->count(),
                'startedAttempts' => (clone $attempts)->where('status', AttemptStatus::InProgress)->count(),
                'submittedAttempts' => (clone $attempts)->where('status', AttemptStatus::Submitted)->count(),
                'violations' => (clone $attempts)->sum('violation_count'),
            ],
        ]);
    }

    public function show(Request $request, ExamSession $examSession): View
    {
        $statusLabels = [
            'scheduled' => 'Belum mulai', 'in_progress' => 'Mengerjakan',
            'submitted' => 'Dikumpulkan', 'terminated' => 'Dihentikan',
            'absent' => 'Tidak hadir ujian', 'cancelled' => 'Dibatalkan',
            'locked' => 'Terkunci (perlu pengawas)',
        ];
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys($statusLabels))],
        ]);
        $examSession->load(['assessmentSubject.assessmentPeriod.academicYear',
            'assessmentSubject.subject', 'assessmentSubject.schoolClass', 'campus']);
        $examSession->assessmentSubject->loadCount('questions');
        $roster = $examSession->assignments();
        $statistics = [
            'total' => (clone $roster)->count(),
            'scheduled' => (clone $roster)->whereDoesntHave('attempt')->where('status', AssignmentStatus::Scheduled)->count(),
            'in_progress' => (clone $roster)->whereHas('attempt', fn ($query) => $query->where('status', AttemptStatus::InProgress))->count(),
            'submitted' => (clone $roster)->whereHas('attempt', fn ($query) => $query->where('status', AttemptStatus::Submitted))->count(),
            'locked' => (clone $roster)->whereHas('attempt', fn ($query) => $query->where('status', AttemptStatus::InProgress)->whereNotNull('security_locked_at'))->count(),
            'absent' => (clone $roster)->whereDoesntHave('attempt')->where('status', AssignmentStatus::Absent)->count(),
        ];
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $roster->whereHas('student', fn ($query) => $query->where(fn ($student) => $student
                ->where('full_name', 'like', '%'.$search.'%')
                ->orWhere('student_number', 'like', '%'.$search.'%')
                ->orWhere('nisn', 'like', '%'.$search.'%')));
        }
        if ($status = $filters['status'] ?? null) {
            if ($status === 'locked') {
                $roster->whereHas('attempt', fn ($query) => $query->where('status', AttemptStatus::InProgress)->whereNotNull('security_locked_at'));
            } elseif (in_array($status, ['in_progress', 'submitted', 'terminated'], true)) {
                $roster->whereHas('attempt', fn ($query) => $query->where('status', $status));
            } else {
                $roster->whereDoesntHave('attempt')->where('status', $status);
            }
        }
        $participants = $roster->with([
            'student.dailyCheckins' => fn ($query) => $query
                ->whereDate('attendance_date', $examSession->starts_at->toDateString())
                ->where('campus_id', $examSession->campus_id),
            'attempt' => fn ($query) => $query->withCount('answers')->with(['securityIncidents' => fn ($events) => $events->orderBy('id')]),
        ])->orderBy('id')->paginate(25)->withQueryString();

        return view('operations.show', [
            'session' => $examSession, 'participants' => $participants,
            'statistics' => $statistics, 'filters' => $filters, 'statusLabels' => $statusLabels,
        ]);
    }

    public function reviewSecurity(Request $request, ExamAttempt $attempt, ExamSecurityService $security): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['resume', 'submit'])],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $security->review($attempt, $request->user(), $validated['action'], $validated['reason'], (int) $validated['lock_version']);

        return back()->with('status', 'Pemeriksaan dicatat. Jawaban, batas waktu, dan riwayat pelanggaran tetap dipertahankan.');
    }

    public function updateSessionStatus(
        Request $request,
        ExamSession $examSession,
        ExamSessionService $sessions,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(SessionStatus::class)],
        ]);

        $sessions->update($examSession, $validated);

        return back()->with('status', 'Status pelaksanaan sesi berhasil diperbarui.');
    }
}
