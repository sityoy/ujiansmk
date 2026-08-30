<?php

namespace App\Http\Controllers;

use App\Enums\CheckinMethod;
use App\Enums\CheckinStatus;
use App\Enums\SessionStatus;
use App\Models\DailyCheckin;
use App\Models\ExamAssignment;
use App\Models\Student;
use App\Services\Attendance\DailyCheckinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

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

        if (! in_array($session->status, [SessionStatus::Published, SessionStatus::Active], true)) {
            throw ValidationException::withMessages(['attendance' => 'Sesi belum diterbitkan oleh panitia.']);
        }

        if (! $session->starts_at->isToday()) {
            throw ValidationException::withMessages(['attendance' => 'Absensi hanya dapat dilakukan pada hari pelaksanaan ujian.']);
        }

        if (now()->lt($session->starts_at->copy()->subMinutes(60)) || now()->gte($session->ends_at)) {
            throw ValidationException::withMessages(['attendance' => 'Absensi dibuka 60 menit sebelum ujian sampai sesi berakhir.']);
        }

        $existing = DailyCheckin::query()
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->first();

        if ($existing?->status === CheckinStatus::Verified) {
            if ($existing->campus_id !== $session->campus_id) {
                throw ValidationException::withMessages([
                    'attendance' => 'Anda sudah tercatat hadir di lokasi ujian lain pada hari ini. Hubungi pengawas.',
                ]);
            }

            return back()->with('status', 'Absensi hari ini sudah terverifikasi dan tidak dibuat ulang.');
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['required', 'numeric', 'between:0,5000'],
            'method' => ['required', Rule::in([CheckinMethod::Face->value, CheckinMethod::CardFace->value])],
            'card_uid' => ['nullable', 'required_if:method,card_face', 'string', 'max:255'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $cardHash = null;
        if ($validated['method'] === CheckinMethod::CardFace->value) {
            if (! $student->card_uid_hash) {
                throw ValidationException::withMessages(['card_uid' => 'Kartu pelajar belum didaftarkan oleh panitia.']);
            }

            $cardHash = hash('sha256', trim($validated['card_uid']));
            if (! hash_equals($student->card_uid_hash, $cardHash)) {
                throw ValidationException::withMessages(['card_uid' => 'Kartu pelajar tidak sesuai dengan akun siswa.']);
            }
        }

        $previousSelfiePath = $existing?->selfie_path;
        $selfiePath = $request->file('selfie')->store('checkins/'.now()->format('Y/m/d'));

        try {
            $checkins->checkIn(
                $student,
                $session->campus,
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) $validated['accuracy_meters'],
                CheckinMethod::from($validated['method']),
                $cardHash,
                $selfiePath,
            );

            if ($previousSelfiePath && $previousSelfiePath !== $selfiePath) {
                Storage::delete($previousSelfiePath);
            }
        } catch (Throwable $exception) {
            Storage::delete($selfiePath);
            throw $exception;
        }

        return back()->with('status', 'Absensi berhasil diverifikasi. Ujian dapat dimulai sesuai jadwal.');
    }
}
