<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\Semester;
use App\Enums\SessionKind;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Campus;
use App\Models\ExamAssignment;
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\Exams\ExamAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_the_same_component_twice_does_not_duplicate_data(): void
    {
        [$student, $assessmentSubject, $regularSession] = $this->makeExamData();
        $service = app(ExamAssignmentService::class);

        $first = $service->assign($student, $assessmentSubject, $regularSession);
        $second = $service->assign($student, $assessmentSubject, $regularSession);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('exam_assignments', 1);
    }

    public function test_moving_to_makeup_reuses_the_existing_assignment(): void
    {
        [$student, $assessmentSubject, $regularSession, $campus] = $this->makeExamData();
        $service = app(ExamAssignmentService::class);
        $assignment = $service->assign($student, $assessmentSubject, $regularSession);

        $makeupSession = ExamSession::create([
            'assessment_subject_id' => $assessmentSubject->id,
            'campus_id' => $campus->id,
            'source_session_id' => $regularSession->id,
            'kind' => SessionKind::Makeup,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'duration_minutes' => 90,
        ]);

        $moved = $service->moveToMakeup($assignment, $makeupSession);

        $this->assertSame($assignment->id, $moved->id);
        $this->assertSame($makeupSession->id, $moved->exam_session_id);
        $this->assertDatabaseCount('exam_assignments', 1);
    }

    private function makeExamData(): array
    {
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        $campus = Campus::create([
            'name' => 'Kampus Utama',
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
        ]);
        $subject = Subject::create(['code' => 'INF', 'name' => 'Informatika']);
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
        $period = AssessmentPeriod::create([
            'academic_year_id' => $academicYear->id,
            'code' => 'ATS-GANJIL-2026',
            'name' => 'ATS Ganjil 2026/2027',
            'type' => AssessmentType::ATS,
            'semester' => Semester::Odd,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-10',
        ]);
        $assessmentSubject = AssessmentSubject::create([
            'assessment_period_id' => $period->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
        ]);
        $regularSession = ExamSession::create([
            'assessment_subject_id' => $assessmentSubject->id,
            'campus_id' => $campus->id,
            'kind' => SessionKind::Regular,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
            'duration_minutes' => 90,
        ]);

        return [$student, $assessmentSubject, $regularSession, $campus];
    }
}
