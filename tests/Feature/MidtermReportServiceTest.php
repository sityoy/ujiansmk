<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\Semester;
use App\Enums\SessionKind;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Campus;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\Reports\MidtermReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MidtermReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_subject_scores_average_and_class_rank(): void
    {
        [$period, $class, $students, $assessmentSubjects] = $this->makeReportData();

        $this->submitScores($students[0], $assessmentSubjects, [90, 80]);
        $this->submitScores($students[1], $assessmentSubjects, [75, 70]);

        $report = app(MidtermReportService::class)->build($period, $class);

        $this->assertCount(2, $report['subjects']);
        $this->assertCount(2, $report['rows']);
        $this->assertSame($students[0]->id, $report['rows'][0]['student']->id);
        $this->assertSame(170.0, $report['rows'][0]['total']);
        $this->assertSame(85.0, $report['rows'][0]['average']);
        $this->assertSame(1, $report['rows'][0]['rank']);
        $this->assertSame(2, $report['rows'][1]['rank']);
        $this->assertTrue($report['is_complete']);
    }

    public function test_report_is_rejected_for_a_non_midterm_period(): void
    {
        [$period, $class] = $this->makeReportData();
        $period->update(['type' => AssessmentType::AAS]);

        $this->expectException(InvalidArgumentException::class);

        app(MidtermReportService::class)->build($period->refresh(), $class);
    }

    private function makeReportData(): array
    {
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'IX-2',
            'grade_level' => 9,
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
        $campus = Campus::create([
            'name' => 'Kampus Utama',
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
            'is_active' => true,
        ]);
        $students = [
            Student::create([
                'school_class_id' => $class->id,
                'student_number' => 'S-001',
                'full_name' => 'Alya Peringkat Satu',
                'is_active' => true,
            ]),
            Student::create([
                'school_class_id' => $class->id,
                'student_number' => 'S-002',
                'full_name' => 'Bima Peringkat Dua',
                'is_active' => true,
            ]),
        ];

        $assessmentSubjects = collect([
            ['code' => 'BIN', 'name' => 'Bahasa Indonesia'],
            ['code' => 'MTK', 'name' => 'Matematika'],
        ])->map(function (array $subjectData) use ($period, $class, $campus): AssessmentSubject {
            $subject = Subject::create([...$subjectData, 'is_active' => true]);
            $assessmentSubject = AssessmentSubject::create([
                'assessment_period_id' => $period->id,
                'subject_id' => $subject->id,
                'school_class_id' => $class->id,
            ]);
            ExamSession::create([
                'assessment_subject_id' => $assessmentSubject->id,
                'campus_id' => $campus->id,
                'kind' => SessionKind::Regular,
                'starts_at' => now(),
                'ends_at' => now()->addHours(2),
                'duration_minutes' => 90,
            ]);

            return $assessmentSubject;
        })->values();

        return [$period, $class, $students, $assessmentSubjects];
    }

    private function submitScores(Student $student, $assessmentSubjects, array $scores): void
    {
        foreach ($assessmentSubjects as $index => $assessmentSubject) {
            $session = $assessmentSubject->examSessions()->firstOrFail();
            $assignment = ExamAssignment::create([
                'assessment_subject_id' => $assessmentSubject->id,
                'student_id' => $student->id,
                'exam_session_id' => $session->id,
                'status' => AssignmentStatus::Completed,
                'assigned_at' => now(),
            ]);

            ExamAttempt::create([
                'exam_assignment_id' => $assignment->id,
                'status' => AttemptStatus::Submitted,
                'started_at' => now()->subHour(),
                'submitted_at' => now(),
                'score' => $scores[$index],
                'device_session_hash' => hash('sha256', 'test-device-'.$assignment->id),
            ]);
        }
    }
}
