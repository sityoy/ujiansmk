<?php

namespace App\Http\Controllers;

use App\Enums\CheckinStatus;
use App\Models\DailyCheckin;
use App\Models\SecurityIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceSecurityController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();

        return view('attendance.index', [
            'checkins' => DailyCheckin::query()
                ->with(['student.schoolClass', 'campus', 'verifier'])
                ->whereDate('attendance_date', $today)
                ->latest('verified_at')
                ->paginate(20),
            'incidents' => SecurityIncident::query()
                ->with(['examAttempt.assignment.student', 'examAttempt.assignment.assessmentSubject.subject'])
                ->latest('occurred_at')
                ->limit(30)
                ->get(),
            'checkinStatuses' => CheckinStatus::cases(),
            'statistics' => [
                'verified' => DailyCheckin::query()->whereDate('attendance_date', $today)->where('status', CheckinStatus::Verified)->count(),
                'review' => DailyCheckin::query()->whereDate('attendance_date', $today)->where('status', CheckinStatus::Review)->count(),
                'rejected' => DailyCheckin::query()->whereDate('attendance_date', $today)->where('status', CheckinStatus::Rejected)->count(),
                'incidents' => SecurityIncident::query()->whereDate('occurred_at', $today)->count(),
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
