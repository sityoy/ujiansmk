<?php

namespace App\Services\Attendance;

use App\Models\Campus;
use App\Models\TrialLocation;
use Illuminate\Validation\ValidationException;

class Geofence
{
    public function verify(Campus|TrialLocation $location, float $latitude, float $longitude, float $accuracyMeters): float
    {
        if ($accuracyMeters > $location->max_accuracy_meters) {
            throw ValidationException::withMessages([
                'location' => 'Akurasi lokasi belum memadai. Aktifkan GPS presisi tinggi lalu coba lagi.',
            ]);
        }

        $latitudeDelta = deg2rad($latitude - (float) $location->latitude);
        $longitudeDelta = deg2rad($longitude - (float) $location->longitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad((float) $location->latitude)) * cos(deg2rad($latitude)) * sin($longitudeDelta / 2) ** 2;
        $distanceMeters = 2 * 6_371_000 * asin(sqrt(min(1, $a)));

        if ($distanceMeters > $location->radius_meters) {
            throw ValidationException::withMessages([
                'location' => sprintf(
                    'Posisi berada %.0f meter dari sekolah dan melewati radius %d meter.',
                    $distanceMeters,
                    $location->radius_meters,
                ),
            ]);
        }

        return $distanceMeters;
    }
}
