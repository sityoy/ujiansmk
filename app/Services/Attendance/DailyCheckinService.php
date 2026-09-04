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

        if ($accuracyMeters > $campus->max_accuracy_meters) {
            throw ValidationException::withMessages([
                'location' => 'Akurasi lokasi belum memadai. Aktifkan GPS presisi tinggi lalu coba lagi.',
            ]);
        }

        $distanceMeters = $this->distanceInMeters(
            (float) $campus->latitude,
            (float) $campus->longitude,
            $latitude,
            $longitude,
        );

        if ($distanceMeters > $campus->radius_meters) {
            throw ValidationException::withMessages([
                'location' => sprintf(
                    'Posisi berada %.0f meter dari sekolah dan melewati radius %d meter.',
                    $distanceMeters,
                    $campus->radius_meters,
                ),
            ]);
        }

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

    public function distanceInMeters(
        float $originLatitude,
        float $originLongitude,
        float $targetLatitude,
        float $targetLongitude,
    ): float {
        $earthRadiusMeters = 6_371_000;
        $latitudeDelta = deg2rad($targetLatitude - $originLatitude);
        $longitudeDelta = deg2rad($targetLongitude - $originLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($originLatitude))
            * cos(deg2rad($targetLatitude))
            * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
