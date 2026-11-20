<?php

namespace App\Http\Controllers;

use App\Enums\CheckinMethod;
use App\Enums\CheckinStatus;
use App\Models\DailyCheckin;
use App\Models\ExamAssignment;
use App\Services\Attendance\AttendanceAvailability;
use App\Services\Attendance\DailyCheckinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentAttendanceController extends Controller
{
    public function store(
        Request $request,
        ExamAssignment $assignment,
        DailyCheckinService $checkins,
    ): RedirectResponse {
        $student = $request->user()->student ?: abort(403);
        abort_unless($assignment->student_id === $student->id, 403);
        $assignment->load('examSession.campus');
        $session = $assignment->examSession;

        $existing = DailyCheckin::query()
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->first();

        if ($reason = app(AttendanceAvailability::class)->reason($assignment, $existing)) {
            throw ValidationException::withMessages(['attendance' => $reason]);
        }

        if ($existing?->status === CheckinStatus::Verified) {
            if ($existing->campus_id !== $session->campus_id) {
                throw ValidationException::withMessages([
                    'attendance' => 'Anda sudah tercatat hadir di lokasi ujian lain pada hari ini. Hubungi pengawas.',
                ]);
            }

            return back()->with('status', 'Absensi lokasi hari ini sudah terverifikasi dan tidak dibuat ulang.');
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['required', 'numeric', 'between:0,5000'],
        ]);

        $checkins->checkIn(
            $student,
            $session->campus,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $validated['accuracy_meters'],
            CheckinMethod::Location,
        );

        return back()->with('status', 'Absensi lokasi berhasil diverifikasi. Ujian dapat dimulai sesuai jadwal.');
    }
}
