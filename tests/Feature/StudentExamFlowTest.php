<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\CheckinMethod;
use App\Enums\CheckinStatus;
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
use App\Services\Attendance\DailyCheckinService;
use App\Services\Exams\ExamAttemptService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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
        $this->travelTo(Carbon::parse('2026-09-04 10:00:00'));
    }

    public function test_student_can_check_in_start_autosave_and_submit_an_exam(): void
    {
        [$user, $assignment, $questions] = $this->makeExam();
        $deviceToken = str_repeat('a', 64);
        $this->actingAs($user);

        $this->withSession(['exam_device_token' => $deviceToken])
            ->post(route('student.attendance.store', $assignment), [
                'latitude' => -6.2000000,
                'longitude' => 106.8166660,
                'accuracy_meters' => 10,
                'method' => 'face',
                'selfie' => UploadedFile::fake()->create('selfie.jpg', 100, 'image/jpeg'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->withSession(['exam_device_token' => $deviceToken])
            ->post(route('student.exams.start', $assignment))
            ->assertRedirect();

        $attempt = ExamAttempt::query()->firstOrFail();

        $this->withSession(['exam_device_token' => $deviceToken])
            ->putJson(route('student.exams.answer', [$attempt, $questions[0]]), ['answer' => 'A'])
            ->assertOk()
            ->assertJson(['saved' => true]);
        $this->withSession(['exam_device_token' => $deviceToken])
            ->putJson(route('student.exams.answer', [$attempt, $questions[1]]), ['answer' => 'A'])
            ->assertOk();

        $this->withSession(['exam_device_token' => $deviceToken])
            ->post(route('student.exams.submit', $attempt))
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

    public function test_started_session_schedule_cannot_be_changed(): void
    {
        [, $assignment] = $this->makeExam();
        $this->startAttempt($assignment);
        $session = $assignment->examSession;
        $committee = User::factory()->create(['role' => UserRole::Committee]);

        $this->actingAs($committee)->patch(route('scheduling.sessions.update', $session), [
            ...$this->sessionData($session), 'duration_minutes' => 120,
        ])->assertSessionHasErrors('session');

        $this->assertSame(90, (int) $session->refresh()->duration_minutes);
    }

    public function test_closing_from_scheduling_submits_answers_and_cannot_be_reopened(): void
    {
        [, $assignment, $questions] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        app(ExamAttemptService::class)->saveAnswer($attempt, $questions[0], 'A');
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $session = $assignment->examSession;

        $this->actingAs($committee)->patch(route('scheduling.sessions.update', $session), [
            ...$this->sessionData($session), 'status' => 'closed',
        ])->assertSessionHasNoErrors();

        $this->assertSame(AttemptStatus::Submitted, $attempt->refresh()->status);
        $this->assertSame('50.00', $attempt->score);
        $this->patch(route('operations.sessions.status', $session), ['status' => 'active'])
            ->assertSessionHasErrors('status');
        $this->assertSame(SessionStatus::Closed, $session->refresh()->status);
    }

    public function test_operations_cannot_return_started_session_to_draft_and_closes_consistently(): void
    {
        [, $assignment] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $route = route('operations.sessions.status', $assignment->examSession);

        $this->actingAs($committee)->patch($route, ['status' => 'draft'])->assertSessionHasErrors('status');
        $this->patch($route, ['status' => 'closed'])->assertRedirect();
        $this->assertSame(AttemptStatus::Submitted, $attempt->refresh()->status);
        $this->assertSame(AssignmentStatus::Completed, $assignment->refresh()->status);
    }

    public function test_question_bank_is_frozen_after_first_attempt_even_for_unanswered_questions(): void
    {
        [, $assignment, $questions] = $this->makeExam();
        $this->startAttempt($assignment);
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $this->actingAs($committee)->post(route('scheduling.questions.store', $assignment->assessment_subject_id), [
            'question_text' => 'Soal baru', 'option_a' => 'A', 'option_b' => 'B',
            'option_c' => 'C', 'option_d' => 'D', 'correct_answer' => 'A', 'points' => 1,
        ])->assertSessionHasErrors('question');
        $this->delete(route('scheduling.questions.destroy', [$assignment->assessment_subject_id, $questions[1]]))
            ->assertSessionHasErrors('question');
        $this->get(route('scheduling.questions.index', $assignment->assessment_subject_id))
            ->assertOk()->assertSee('Bank soal terkunci');
        $this->assertDatabaseCount('exam_questions', 2);
    }

    public function test_stale_attempt_cannot_save_an_answer_after_submission(): void
    {
        [, $assignment, $questions] = $this->makeExam();
        $staleAttempt = $this->startAttempt($assignment);
        $service = app(ExamAttemptService::class);
        $service->submit($staleAttempt);
        try {
            $service->saveAnswer($staleAttempt, $questions[0], 'A');
            $this->fail('A submitted attempt accepted a late answer.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('exam_answers', 0);
            $this->assertSame('0.00', $staleAttempt->fresh()->score);
        }
    }

    public function test_expired_autosave_commits_submission_before_returning_validation_error(): void
    {
        [, $assignment, $questions] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $this->travel(91)->minutes();
        try {
            app(ExamAttemptService::class)->saveAnswer($attempt, $questions[0], 'A');
            $this->fail('Expired answer was accepted.');
        } catch (ValidationException) {
            $this->assertSame(AttemptStatus::Submitted, $attempt->fresh()->status);
            $this->assertDatabaseCount('exam_answers', 0);
        }
    }

    public function test_expired_command_is_idempotent_and_leaves_unexpired_attempts_alone(): void
    {
        [, $assignment, $questions] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        app(ExamAttemptService::class)->saveAnswer($attempt, $questions[0], 'A');
        $this->artisan('exams:finalize-expired')->assertSuccessful();
        $this->assertSame(AttemptStatus::InProgress, $attempt->fresh()->status);
        $this->travel(91)->minutes();
        $this->artisan('exams:finalize-expired')->assertSuccessful();
        $submittedAt = $attempt->fresh()->submitted_at->toDateTimeString();
        $this->travel(1)->minutes();
        $this->artisan('exams:finalize-expired')->assertSuccessful();
        $this->assertSame(AttemptStatus::Submitted, $attempt->fresh()->status);
        $this->assertSame('50.00', $attempt->fresh()->score);
        $this->assertSame($submittedAt, $attempt->fresh()->submitted_at->toDateTimeString());
    }

    public function test_terminated_attempt_is_not_converted_to_submitted(): void
    {
        [, $assignment] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $attempt->update(['status' => AttemptStatus::Terminated]);
        app(ExamAttemptService::class)->submit($attempt);
        $this->assertSame(AttemptStatus::Terminated, $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->score);
    }

    public function test_portal_explains_attendance_window_and_rejects_inactive_assignments(): void
    {
        [$user, $assignment] = $this->makeExam();
        $assignment->examSession->update(['starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(3)]);
        $this->actingAs($user)->get(route('student.exams.index'))
            ->assertOk()->assertSee('Absensi dibuka pukul 11:00')->assertDontSee('Ambil lokasi &amp; kirim absensi', false);
        $assignment->update(['status' => AssignmentStatus::Cancelled]);
        $this->post(route('student.attendance.store', $assignment))->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('daily_checkins', 0);
    }

    public function test_rejected_attendance_cannot_be_self_verified_again(): void
    {
        [$user, $assignment] = $this->makeExam();
        $this->startAttempt($assignment);
        $checkin = $assignment->student->dailyCheckins()->firstOrFail();
        $checkin->update(['status' => CheckinStatus::Rejected]);
        $this->actingAs($user)->post(route('student.attendance.store', $assignment))
            ->assertSessionHasErrors('attendance');
        $this->assertSame(CheckinStatus::Rejected, $checkin->fresh()->status);
    }

    public function test_active_exam_page_renders_with_retry_and_server_based_timer(): void
    {
        [$user, $assignment] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $this->actingAs($user)->withSession(['exam_device_token' => str_repeat('a', 64)])
            ->get(route('student.exams.show', $attempt))->assertOk()
            ->assertSee('Coba simpan lagi')->assertSee('remainingAtLoad', false);
    }

    private function startAttempt(ExamAssignment $assignment): ExamAttempt
    {
        $checkin = app(DailyCheckinService::class)->checkIn(
            $assignment->student, $assignment->examSession->campus,
            -6.2000000, 106.8166660, 10, CheckinMethod::Face, null, 'test/selfie.jpg',
        );

        return app(ExamAttemptService::class)->start($assignment, $checkin, hash('sha256', str_repeat('a', 64)), '127.0.0.1', 'Test');
    }

    private function sessionData(ExamSession $session): array
    {
        return [
            'campus_id' => $session->campus_id, 'kind' => $session->kind->value,
            'status' => $session->status->value, 'room_name' => $session->room_name,
            'starts_at' => $session->starts_at->toDateTimeString(),
            'duration_minutes' => $session->duration_minutes,
        ];
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
            'ends_at' => now()->addMinutes(85),
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
