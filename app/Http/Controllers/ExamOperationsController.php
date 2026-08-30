<?php

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Enums\SessionStatus;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Services\Exams\ExamAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExamOperationsController extends Controller
{
    public function index(): View
    {
        return view('operations.index', [
            'sessions' => ExamSession::query()
                ->with(['assessmentSubject.assessmentPeriod', 'assessmentSubject.subject', 'assessmentSubject.schoolClass', 'campus'])
                ->withCount('assignments')
                ->orderByDesc('starts_at')
                ->paginate(15),
            'attempts' => ExamAttempt::query()
                ->with(['assignment.student', 'assignment.assessmentSubject.subject', 'assignment.examSession'])
                ->latest('started_at')
                ->limit(30)
                ->get(),
            'sessionStatuses' => SessionStatus::cases(),
            'statistics' => [
                'activeSessions' => ExamSession::query()->where('status', SessionStatus::Active)->count(),
                'startedAttempts' => ExamAttempt::query()->where('status', 'in_progress')->count(),
                'submittedAttempts' => ExamAttempt::query()->where('status', 'submitted')->count(),
                'violations' => ExamAttempt::query()->sum('violation_count'),
            ],
        ]);
    }

    public function updateSessionStatus(
        Request $request,
        ExamSession $examSession,
        ExamAttemptService $attempts,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(SessionStatus::class)],
        ]);

        $examSession->update($validated);

        if ($examSession->status === SessionStatus::Closed) {
            ExamAttempt::query()
                ->whereHas('assignment', fn ($query) => $query->where('exam_session_id', $examSession->id))
                ->where('status', AttemptStatus::InProgress)
                ->each(fn (ExamAttempt $attempt) => $attempts->submit($attempt));
        }

        return back()->with('status', 'Status pelaksanaan sesi berhasil diperbarui.');
    }
}
