<?php

namespace App\Http\Controllers;

use App\Enums\CheckinStatus;
use App\Models\AssessmentPeriod;
use App\Models\DailyCheckin;
use App\Models\ExamSession;
use App\Models\SecurityIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceSecurityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'assessment_period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'session_id' => ['nullable', 'integer', 'exists:exam_sessions,id'],
        ]);
        $date = $filters['date'] ?? now()->toDateString();

        $sessionQuery = ExamSession::query()
            ->with(['assessmentSubject.assessmentPeriod.academicYear', 'assessmentSubject.subject', 'assessmentSubject.schoolClass', 'campus'])
            ->withCount(['assignments', 'assignments as attempts_count' => fn ($query) => $query->whereHas('attempt')])
            ->whereDate('starts_at', $date)
            ->when($filters['assessment_period_id'] ?? null, fn ($query, $period) => $query
                ->whereHas('assessmentSubject', fn ($component) => $component->where('assessment_period_id', $period)))
            ->orderBy('starts_at');

        $sessions = (clone $sessionQuery)->paginate(12, ['*'], 'sessions_page')->withQueryString();
        $selectedSession = filled($filters['session_id'] ?? null)
            ? (clone $sessionQuery)->whereKey($filters['session_id'])->first()
            : $sessions->first();

        $checkinQuery = DailyCheckin::query()->whereRaw('1 = 0');
        $incidentQuery = SecurityIncident::query()->whereRaw('1 = 0');

        if ($selectedSession) {
            $checkinQuery = DailyCheckin::query()
                ->with(['student.schoolClass', 'campus', 'verifier'])
                ->whereDate('attendance_date', $selectedSession->starts_at->toDateString())
                ->where('campus_id', $selectedSession->campus_id)
                ->whereHas('student.examAssignments', fn ($assignment) => $assignment
                    ->where('exam_session_id', $selectedSession->id));

            $incidentQuery = SecurityIncident::query()
                ->with(['examAttempt.assignment.student', 'examAttempt.assignment.assessmentSubject.subject'])
                ->whereHas('examAttempt.assignment', fn ($assignment) => $assignment
                    ->where('exam_session_id', $selectedSession->id));
        }

        return view('attendance.index', [
            'filters' => [...$filters, 'date' => $date],
            'periodOptions' => AssessmentPeriod::query()->with('academicYear')->orderByDesc('starts_on')->get(),
            'sessions' => $sessions,
            'selectedSession' => $selectedSession,
            'checkins' => (clone $checkinQuery)->latest('verified_at')->paginate(20, ['*'], 'checkins_page')->withQueryString(),
            'incidents' => (clone $incidentQuery)->latest('occurred_at')->paginate(20, ['*'], 'incidents_page')->withQueryString(),
            'checkinStatuses' => CheckinStatus::cases(),
            'statistics' => [
                'verified' => (clone $checkinQuery)->where('status', CheckinStatus::Verified->value)->count(),
                'review' => (clone $checkinQuery)->where('status', CheckinStatus::Review->value)->count(),
                'rejected' => (clone $checkinQuery)->where('status', CheckinStatus::Rejected->value)->count(),
                'incidents' => (clone $incidentQuery)->count(),
            ],
        ]);
    }

    public function updateStatus(Request $request, DailyCheckin $dailyCheckin): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(CheckinStatus::class)],
        ]);

        $status = CheckinStatus::from($validated['status']);
        $dailyCheckin->update([
            'status' => $status,
            'verified_at' => $status === CheckinStatus::Verified ? now() : null,
            'verified_by' => $status === CheckinStatus::Verified ? $request->user()->id : null,
        ]);

        return back()->with('status', 'Status absensi berhasil diperbarui.');
    }

    public function selfie(DailyCheckin $dailyCheckin): BinaryFileResponse
    {
        abort_unless($dailyCheckin->selfie_path && Storage::exists($dailyCheckin->selfie_path), 404);

        return response()->file(Storage::path($dailyCheckin->selfie_path), [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
