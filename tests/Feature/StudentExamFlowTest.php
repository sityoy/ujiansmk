<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\AssignmentStatus;
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
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentExamFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_student_can_check_in_start_autosave_and_submit_an_exam(): void
    {
        [$user, $assignment, $questions] = $this->makeExam();
        $this->actingAs($user);

        $this->post(route('student.attendance.store', $assignment), [
                'latitude' => -6.2000000,
                'longitude' => 106.8166660,
                'accuracy_meters' => 10,
                'method' => 'face',
                'selfie' => UploadedFile::fake()->create('selfie.jpg', 100, 'image/jpeg'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->post(route('student.exams.start', $assignment))
            ->assertRedirect();

        $attempt = ExamAttempt::query()->firstOrFail();

        $this->putJson(route('student.exams.answer', [$attempt, $questions[0]]), ['answer' => 'A'])
            ->assertOk()
            ->assertJson(['saved' => true]);
        $this->putJson(route('student.exams.answer', [$attempt, $questions[1]]), ['answer' => 'A'])
            ->assertOk();

        $this->post(route('student.exams.submit', $attempt))
            ->assertRedirect(route('student.exams.index'));

        $this->assertSame('submitted', $attempt->refresh()->status->value);
        $this->assertSame('50.00', $attempt->score);
        $this->assertSame(1, $attempt->correct_answers);
        $this->assertSame(1, $attempt->incorrect_answers);
        $this->assertSame(AssignmentStatus::Completed, $assignment->refresh()->status);
        $this->assertDatabaseCount('exam_answers', 2);
        $this->assertDatabaseCount('daily_checkins', 1);
    }

    public function test_student_cannot_start_without_verified_attendance(): void
    {
        [$user, $assignment] = $this->makeExam();

        $this->actingAs($user)
            ->post(route('student.exams.start', $assignment))
            ->assertSessionHasErrors('attendance');

        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_committee_can_add_questions_to_a_component(): void
    {
        [, $assignment] = $this->makeExam();
        $committee = User::factory()->create(['role' => UserRole::Committee]);

        $this->actingAs($committee)
            ->post(route('scheduling.questions.store', $assignment->assessment_subject_id), [
                'question_text' => 'Protokol untuk membuka halaman web adalah?',
                'option_a' => 'HTTP',
                'option_b' => 'FTP',
                'option_c' => 'SSH',
                'option_d' => 'SMTP',
                'correct_answer' => 'A',
                'points' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('exam_questions', [
            'assessment_subject_id' => $assignment->assessment_subject_id,
            'question_text' => 'Protokol untuk membuka halaman web adalah?',
        ]);
    }

    private function makeExam(): array
    {
        $user = User::factory()->create([
            'role' => UserRole::Student,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->addMonths(10)->toDateString(),
            'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'IX-2',
            'grade_level' => 9,
        ]);
        $student = Student::create([
            'user_id' => $user->id,
            'school_class_id' => $class->id,
            'student_number' => 'S-001',
            'full_name' => 'Peserta Uji',
            'is_active' => true,
        ]);
        $campus = Campus::create([
            'name' => 'Kampus Utama',
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
            'radius_meters' => 100,
            'max_accuracy_meters' => 50,
            'is_active' => true,
        ]);
        $subject = Subject::create(['code' => 'INF', 'name' => 'Informatika', 'is_active' => true]);
        $period = AssessmentPeriod::create([
            'academic_year_id' => $year->id,
            'code' => 'ATS-AKTIF',
            'name' => 'ATS Aktif',
            'type' => AssessmentType::ATS,
            'semester' => Semester::Odd,
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
            'status' => PeriodStatus::Active,
        ]);
        $component = AssessmentSubject::create([
            'assessment_period_id' => $period->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
        ]);
        $session = ExamSession::create([
            'assessment_subject_id' => $component->id,
            'campus_id' => $campus->id,
            'room_name' => 'Lab Komputer',
            'kind' => SessionKind::Regular,
            'status' => SessionStatus::Published,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addMinutes(90),
            'duration_minutes' => 90,
        ]);
        $assignment = ExamAssignment::create([
            'assessment_subject_id' => $component->id,
            'student_id' => $student->id,
            'exam_session_id' => $session->id,
            'status' => AssignmentStatus::Scheduled,
            'assigned_at' => now(),
        ]);
        $questions = collect([
            ['question_text' => 'Dua tambah dua?', 'correct_answer' => 'A', 'position' => 1],
            ['question_text' => 'Ibu kota Indonesia?', 'correct_answer' => 'B', 'position' => 2],
        ])->map(fn (array $data) => ExamQuestion::create([
            'assessment_subject_id' => $component->id,
            'question_text' => $data['question_text'],
            'options' => ['A' => 'Pilihan A', 'B' => 'Pilihan B', 'C' => 'Pilihan C', 'D' => 'Pilihan D'],
            'correct_answer' => $data['correct_answer'],
            'points' => 1,
            'position' => $data['position'],
        ]))->all();

        return [$user, $assignment, $questions];
    }
}
