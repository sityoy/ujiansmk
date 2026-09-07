<?php

namespace Tests\Feature;

use App\Enums\CheckinMethod;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Attendance\DailyCheckinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DailyCheckinServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_checkin_is_reused_for_the_same_day(): void
    {
        [$student, $campus] = $this->makeAttendanceData();
        $service = app(DailyCheckinService::class);

        $first = $service->checkIn(
            $student,
            $campus,
            -6.2000000,
            106.8166660,
            10,
            CheckinMethod::CardFace,
        );
        $second = $service->checkIn(
            $student,
            $campus,
            -6.2000000,
            106.8166660,
            10,
            CheckinMethod::CardFace,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('daily_checkins', 1);
    }

    public function test_checkin_outside_the_radius_is_rejected(): void
    {
        [$student, $campus] = $this->makeAttendanceData();

        $this->expectException(ValidationException::class);

        app(DailyCheckinService::class)->checkIn(
            $student,
            $campus,
            -6.2100000,
            106.8166660,
            10,
            CheckinMethod::CardFace,
        );
    }

    private function makeAttendanceData(): array
    {
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'XI TKJ 1',
            'grade_level' => 11,
            'major' => 'TKJ',
        ]);
        $student = Student::create([
            'school_class_id' => $class->id,
            'student_number' => 'TEST-001',
            'full_name' => 'Peserta Uji',
        ]);
        $campus = Campus::create([
            'name' => 'Kampus Utama',
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
            'radius_meters' => 100,
            'max_accuracy_meters' => 50,
            'is_active' => true,
        ]);

        return [$student, $campus];
    }
}
