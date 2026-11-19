<?php

namespace App\Services\Attendance;

use App\Enums\CheckinMethod;
use App\Enums\CheckinStatus;
use App\Models\Campus;
use App\Models\DailyCheckin;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyCheckinService
{
    public function checkIn(
        Student $student,
        Campus $campus,
        float $latitude,
        float $longitude,
        float $accuracyMeters,
        CheckinMethod $method,
        ?string $cardReferenceHash = null,
        ?string $selfiePath = null,
    ): DailyCheckin {
        if (! $campus->is_active) {
            throw ValidationException::withMessages([
                'campus' => 'Lokasi sekolah sedang tidak aktif.',
            ]);
        }

        $distanceMeters = app(Geofence::class)->verify($campus, $latitude, $longitude, $accuracyMeters);

        return DB::transaction(function () use (
            $student,
            $campus,
            $latitude,
            $longitude,
            $accuracyMeters,
            $distanceMeters,
            $method,
            $cardReferenceHash,
            $selfiePath,
        ): DailyCheckin {
            $date = now()->toDateString();
            $existing = DailyCheckin::query()
                ->where('student_id', $student->id)
                ->whereDate('attendance_date', $date)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === CheckinStatus::Verified) {
                return $existing;
            }

            if ($existing) {
                throw ValidationException::withMessages([
                    'attendance' => 'Absensi sudah masuk pemeriksaan pengawas dan tidak dapat diverifikasi ulang oleh siswa.',
                ]);
            }

            $checkin = $existing ?? new DailyCheckin([
                'student_id' => $student->id,
                'attendance_date' => $date,
            ]);

            $checkin->fill([
                'campus_id' => $campus->id,
                'method' => $method,
                'status' => CheckinStatus::Verified,
                'card_reference_hash' => $cardReferenceHash,
                'selfie_path' => $selfiePath,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy_meters' => $accuracyMeters,
                'distance_meters' => round($distanceMeters, 2),
                'verified_at' => now(),
            ])->save();

            return $checkin->refresh();
        });
    }
}
