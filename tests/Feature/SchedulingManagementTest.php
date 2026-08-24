<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\PeriodStatus;
use App\Enums\Semester;
use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Campus;
use App\Models\ExamAssignment;
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_assignment_is_idempotent_and_moves_to_makeup_without_duplication(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        [$component, $regular, $makeup] = $this->makeSchedule();

        Student::create([
            'school_class_id' => $component->school_class_id,
            'student_number' => 'S-001',
            'full_name' => 'Siswa Satu',
            'is_active' => true,
        ]);
        Student::create([
            'school_class_id' => $component->school_class_id,
            'student_number' => 'S-002',
            'full_name' => 'Siswa Dua',
            'is_active' => true,
        ]);

        $route = route('scheduling.assign-class', [$component, $regular]);

        $this->actingAs($admin)->post($route)->assertRedirect();
        $this->actingAs($admin)->post($route)->assertRedirect();

        $this->assertDatabaseCount('exam_assignments', 2);

        $assignment = ExamAssignment::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('scheduling.makeup.move'), [
                'assignment_id' => $assignment->id,
                'makeup_session_id' => $makeup->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('exam_assignments', 2);
        $this->assertSame($makeup->id, $assignment->refresh()->exam_session_id);
    }

    public function test_committee_can_open_scheduling_but_teacher_cannot(): void
    {
        $this->withoutVite();

        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);

        $this->actingAs($committee)->get(route('scheduling.index'))->assertOk();
        $this->actingAs($teacher)->get(route('scheduling.index'))->assertForbidden();
    }

    private function makeSchedule(): array
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        $campus = Campus::create([
            'name' => 'Kampus Utama',
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'radius_meters' => 100,
            'max_accuracy_meters' => 50,
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'code' => 'INF',
            'name' => 'Informatika',
            'is_active' => true,
        ]);
        $schoolClass = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'IX-2',
            'grade_level' => 9,
        ]);
        $period = AssessmentPeriod::create([
            'academic_year_id' => $year->id,
            'code' => 'ATS-GANJIL',
            'name' => 'Asesmen Tengah Semester Ganjil',
            'type' => AssessmentType::ATS,
            'semester' => Semester::Odd,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-10',
            'status' => PeriodStatus::Published,
        ]);
        $component = AssessmentSubject::create([
            'assessment_period_id' => $period->id,
            'subject_id' => $subject->id,
            'school_class_id' => $schoolClass->id,
        ]);
        $regular = ExamSession::create([
            'assessment_subject_id' => $component->id,
            'campus_id' => $campus->id,
            'kind' => SessionKind::Regular,
            'status' => SessionStatus::Published,
            'starts_at' => '2026-09-01 08:00:00',
            'ends_at' => '2026-09-01 10:00:00',
            'duration_minutes' => 90,
        ]);
        $makeup = ExamSession::create([
            'assessment_subject_id' => $component->id,
            'campus_id' => $campus->id,
            'source_session_id' => $regular->id,
            'kind' => SessionKind::Makeup,
            'status' => SessionStatus::Published,
            'starts_at' => '2026-09-12 08:00:00',
            'ends_at' => '2026-09-12 10:00:00',
            'duration_minutes' => 90,
        ]);

        return [$component, $regular, $makeup];
    }
}
