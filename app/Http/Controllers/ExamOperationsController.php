<?php

namespace App\Http\Controllers;

use App\Enums\SessionStatus;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Services\Exams\ExamSessionService;
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
