<?php

namespace Tests\Concerns;

use App\Models\AssessmentPeriod;

trait CreatesRoomPeriods
{
    private function makeRoomPeriod(int $yearId, string $code = 'ATS'): AssessmentPeriod
    {
        return AssessmentPeriod::create([
            'academic_year_id' => $yearId, 'code' => $code, 'name' => $code.' Ganjil',
            'type' => strtolower($code), 'semester' => 'odd',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-10', 'status' => 'draft',
        ]);
    }
}
