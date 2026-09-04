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
use App\Services\Exams\ExamSecurityService;
use Illuminate\Support\Str;
use App\Services\Exams\ExamSessionService;
use App\Services\Exams\ExamAssignmentService;
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

    public function test_second_security_event_locks_answers_and_hides_questions_after_reload(): void
    {
        [$user, $assignment, $questions] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $this->securityEvent($user, $attempt)->assertOk()->assertJson(['violations' => 1, 'locked' => false]);
        $this->travel(4)->seconds();
        $this->securityEvent($user, $attempt)->assertOk()->assertJson(['violations' => 2, 'locked' => true]);
        $this->withSession(['exam_device_token' => str_repeat('a', 64)])
            ->putJson(route('student.exams.answer', [$attempt, $questions[0]]), ['answer' => 'A'])
            ->assertUnprocessable()->assertJsonValidationErrors('security');
        $this->withSession(['exam_device_token' => str_repeat('a', 64)])
            ->get(route('student.exams.show', $attempt))->assertOk()->assertSee('Hubungi pengawas ujian')
            ->assertDontSee($questions[0]->question_text);
        $this->assertDatabaseCount('exam_answers', 0);
        $this->assertSame(2, (int) $attempt->fresh()->violation_count);
    }

    public function test_security_events_are_idempotent_and_simultaneous_signals_count_once(): void
    {
        [$user, $assignment] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $firstId = (string) Str::uuid();
        $suppressedId = (string) Str::uuid();
        $this->securityEvent($user, $attempt, $firstId)->assertJson(['violations' => 1]);
        $this->securityEvent($user, $attempt, $suppressedId, 'fullscreen_exit')->assertJson(['violations' => 1]);
        $this->travel(10)->seconds();
        $this->securityEvent($user, $attempt, $firstId)->assertJson(['violations' => 1]);
        $this->securityEvent($user, $attempt, $suppressedId)->assertJson(['violations' => 1]);
        $this->assertSame(2, $attempt->securityIncidents()->count());
        $this->securityEvent($user, $attempt)->assertJson(['violations' => 2, 'locked' => true]);
        $this->travel(4)->seconds();
        $this->securityEvent($user, $attempt)->assertJson(['violations' => 2, 'locked' => true]);
    }

    public function test_proctor_can_release_lock_without_reset_and_stale_review_cannot_unlock_again(): void
    {
        [$user, $assignment, $questions] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $service = app(ExamAttemptService::class);
        $deadline = $service->deadline($attempt)->toIso8601String();
        $service->saveAnswer($attempt, $questions[0], 'A');
        $this->lockAttempt($user, $attempt);
        $proctor = User::factory()->create(['role' => UserRole::Proctor]);
        $payload = ['action' => 'resume', 'reason' => 'Gangguan perangkat sudah diperiksa.', 'lock_version' => 1];
        $reviewUrl = route('operations.attempts.security-review', $attempt);
        $this->actingAs($proctor)->post($reviewUrl, $payload)->assertSessionHasNoErrors();
        $this->assertNull($attempt->fresh()->security_locked_at);
        $this->assertSame(2, (int) $attempt->fresh()->violation_count);
        $this->assertSame($deadline, $service->deadline($attempt->fresh())->toIso8601String());
        $this->assertDatabaseCount('exam_answers', 1);
        $audit = $attempt->securityIncidents()->where('category', 'supervisor_resume')->firstOrFail();
        $this->assertSame($proctor->id, $audit->details['reviewer_id']);
        $this->travel(4)->seconds();
        $this->securityEvent($user, $attempt)->assertJson(['violations' => 2, 'locked' => true]);
        $this->assertSame(2, $attempt->fresh()->security_lock_version);
        $this->actingAs($proctor)->post($reviewUrl, $payload)->assertSessionHasErrors('security');
        $this->assertNotNull($attempt->fresh()->security_locked_at);
    }

    public function test_proctor_can_collect_locked_exam_and_roster_shows_audit(): void
    {
        [$user, $assignment, $questions] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        app(ExamAttemptService::class)->saveAnswer($attempt, $questions[0], 'A');
        $this->lockAttempt($user, $attempt);
        $proctor = User::factory()->create(['role' => UserRole::Proctor]);
        $this->actingAs($proctor)->get(route('operations.sessions.show', [$assignment->examSession, 'status' => 'locked']))
            ->assertOk()->assertSee('Simpan keputusan')->assertSee('Halaman tidak terlihat');
        $this->post(route('operations.attempts.security-review', $attempt), [
            'action' => 'submit', 'reason' => 'Pemeriksaan selesai, ujian diakhiri.', 'lock_version' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(AttemptStatus::Submitted, $attempt->fresh()->status);
        $this->assertSame('50.00', $attempt->fresh()->score);
        $this->get(route('operations.sessions.show', $assignment->examSession))->assertOk()
            ->assertSee('Pemeriksaan selesai, ujian diakhiri.')->assertDontSee('Simpan keputusan');
    }

    public function test_security_review_requires_authorized_role_and_reason(): void
    {
        [$user, $assignment] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $this->lockAttempt($user, $attempt);
        $url = route('operations.attempts.security-review', $attempt);
        $payload = ['action' => 'resume', 'reason' => 'Alasan pemeriksaan.', 'lock_version' => 1];
        $this->actingAs($user)->post($url, $payload)->assertForbidden();
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $this->actingAs($teacher)->post($url, $payload)->assertForbidden();
        $proctor = User::factory()->create(['role' => UserRole::Proctor]);
        $this->actingAs($proctor)->post($url, [...$payload, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->assertNotNull($attempt->fresh()->security_locked_at);
    }

    public function test_another_student_or_device_cannot_report_or_inspect_security(): void
    {
        [$user, $assignment] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $other = User::factory()->create(['role' => UserRole::Student]);
        Student::create(['user_id' => $other->id, 'school_class_id' => $assignment->student->school_class_id,
            'student_number' => 'OTHER-SEC', 'full_name' => 'Siswa lain', 'is_active' => true]);
        $this->securityEvent($other, $attempt)->assertForbidden();
        $this->getJson(route('student.exams.security', $attempt))->assertForbidden();
        $this->actingAs($user)->withSession(['exam_device_token' => str_repeat('b', 64)])
            ->getJson(route('student.exams.security', $attempt))->assertUnprocessable()->assertJsonValidationErrors('device');
        $this->assertSame(0, (int) $attempt->fresh()->violation_count);
    }

    public function test_invalid_security_signal_is_rejected_without_changing_count(): void
    {
        [$user, $assignment] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $this->securityEvent($user, $attempt, 'invalid-id', 'blur')->assertUnprocessable()
            ->assertJsonValidationErrors(['event_id', 'category']);
        $this->assertSame(0, (int) $attempt->fresh()->violation_count);
    }

    public function test_locked_exam_still_expires_and_cannot_be_released_after_deadline(): void
    {
        [$user, $assignment] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $this->lockAttempt($user, $attempt);
        $this->travel(91)->minutes();
        $proctor = User::factory()->create(['role' => UserRole::Proctor]);
        $this->actingAs($proctor)->post(route('operations.attempts.security-review', $attempt), [
            'action' => 'resume', 'reason' => 'Pemeriksaan selesai.', 'lock_version' => 1,
        ])->assertSessionHasErrors('security');
        $this->artisan('exams:finalize-expired')->assertSuccessful();
        $this->assertSame(AttemptStatus::Submitted, $attempt->fresh()->status);
        $this->assertSame('0.00', $attempt->fresh()->score);
    }

    public function test_legacy_attempts_keep_monitoring_without_new_automatic_lock(): void
    {
        [$user, $assignment] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $this->assertTrue($attempt->security_enabled);
        $attempt->update(['security_enabled' => false]);
        $this->securityEvent($user, $attempt)->assertJson(['enabled' => false, 'locked' => false]);
        $this->travel(4)->seconds();
        $this->securityEvent($user, $attempt)->assertJson(['violations' => 2, 'locked' => false]);
    }

    public function test_security_state_reports_lock_and_finalizes_at_deadline(): void
    {
        [$user, $assignment] = $this->makeExam();
        $attempt = $this->startAttempt($assignment);
        $this->lockAttempt($user, $attempt);
        $url = route('student.exams.security', $attempt);
        $this->withSession(['exam_device_token' => str_repeat('a', 64)])->getJson($url)
            ->assertOk()->assertJson(['locked' => true, 'violations' => 2, 'status' => 'in_progress']);
        $this->travel(91)->minutes();
        $this->withSession(['exam_device_token' => str_repeat('a', 64)])->getJson($url)
            ->assertOk()->assertJson(['locked' => false, 'status' => 'submitted']);
    }

    private function securityEvent(User $user, ExamAttempt $attempt, ?string $eventId = null, string $category = 'tab_hidden'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->withSession(['exam_device_token' => str_repeat('a', 64)])
            ->postJson(route('student.exams.incident', $attempt), [
                'event_id' => $eventId ?? (string) Str::uuid(), 'category' => $category,
            ]);
    }

    private function lockAttempt(User $user, ExamAttempt $attempt): void
    {
        $this->securityEvent($user, $attempt)->assertOk();
        $this->travel(4)->seconds();
        $this->securityEvent($user, $attempt)->assertOk();
    }

    private function startAttempt(ExamAssignment $assignment): ExamAttempt
    {
        $checkin = app(DailyCheckinService::class)->checkIn(
            $assignment->student, $assignment->examSession->campus,
            -6.2000000, 106.8166660, 10, CheckinMethod::Face, null, 'test/selfie.jpg',
        );

        return app(ExamAttemptService::class)->start($assignment, $checkin, hash('sha256', str_repeat('a', 64)), '127.0.0.1', 'Test');
    }

    public function test_proctor_can_monitor_progress_and_search_by_nisn_without_showing_other_participants(): void
    {
        [, $assignment, $questions] = $this->makeExam();
        $assignment->student->update(['nisn' => '0001234567']);
        $attempt = $this->startAttempt($assignment);
        app(ExamAttemptService::class)->saveAnswer($attempt, $questions[0], 'A');
        $this->additionalParticipant($assignment->examSession, 'S-002', 'Peserta Kedua');
        $proctor = User::factory()->create(['role' => UserRole::Proctor]);

        $this->actingAs($proctor)->get(route('operations.sessions.show', [$assignment->examSession, 'q' => '0001234567']))
            ->assertOk()->assertSee('Peserta Uji')->assertDontSee('Peserta Kedua')
            ->assertSee('1 / 2 jawaban')->assertSee('Terverifikasi')
            ->assertViewHas('statistics', fn ($stats) => $stats['total'] === 2 && $stats['in_progress'] === 1 && $stats['scheduled'] === 1);
    }

    public function test_session_roster_filters_status_and_does_not_include_other_sessions(): void
    {
        [, $assignment] = $this->makeExam();
        $this->startAttempt($assignment);
        $this->additionalParticipant($assignment->examSession, 'S-002', 'Peserta Belum Mulai');
        $otherSession = $assignment->examSession->replicate();
        $otherSession->save();
        $this->additionalParticipant($otherSession, 'S-003', 'Peserta Sesi Lain');
        $proctor = User::factory()->create(['role' => UserRole::Proctor]);

        $this->actingAs($proctor)->get(route('operations.sessions.show', [$assignment->examSession, 'status' => 'scheduled']))
            ->assertOk()->assertSee('Peserta Belum Mulai')->assertDontSee('Peserta Uji')->assertDontSee('Peserta Sesi Lain')
            ->assertViewHas('participants', fn ($participants) => $participants->total() === 1);
    }

    public function test_session_roster_uses_attendance_on_exam_date_not_current_date(): void
    {
        [, $assignment] = $this->makeExam();
        $this->startAttempt($assignment);
        $this->travel(1)->days();
        $proctor = User::factory()->create(['role' => UserRole::Proctor]);
        $this->actingAs($proctor)->get(route('operations.sessions.show', $assignment->examSession))
            ->assertOk()->assertSee('Terverifikasi')->assertDontSee('Belum tercatat');
    }

    public function test_student_and_teacher_cannot_view_session_roster(): void
    {
        [$student, $assignment] = $this->makeExam();
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $route = route('operations.sessions.show', $assignment->examSession);
        $this->actingAs($student)->get($route)->assertForbidden();
        $this->actingAs($teacher)->get($route)->assertForbidden();
    }

    public function test_session_roster_is_paginated_and_filter_parameters_are_validated(): void
    {
        [, $assignment] = $this->makeExam();
        for ($i = 2; $i <= 27; $i++) {
            $this->additionalParticipant($assignment->examSession, 'P-'.$i, 'Peserta Halaman '.$i);
        }
        $proctor = User::factory()->create(['role' => UserRole::Proctor]);
        $this->actingAs($proctor)->get(route('operations.sessions.show', [$assignment->examSession, 'page' => 2, 'status' => 'scheduled']))
            ->assertOk()->assertViewHas('participants', fn ($participants) => $participants->total() === 27 && $participants->count() === 2);
        $this->getJson(route('operations.sessions.show', [$assignment->examSession, 'status' => 'unknown']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_operations_date_and_year_filters_scope_both_statistics_and_attempts(): void
    {
        [, $assignment] = $this->makeExam();
        $this->startAttempt($assignment);
        $proctor = User::factory()->create(['role' => UserRole::Proctor]);
        $year = $assignment->assessmentSubject->assessmentPeriod->academic_year_id;

        $this->actingAs($proctor)->get(route('operations.index', ['academic_year_id' => $year, 'date' => now()->toDateString()]))
            ->assertOk()->assertViewHas('statistics', fn ($stats) => $stats['startedAttempts'] === 1)
            ->assertViewHas('attempts', fn ($attempts) => $attempts->total() === 1);
        $this->get(route('operations.index', ['date' => now()->addDay()->toDateString()]))
            ->assertOk()->assertViewHas('statistics', fn ($stats) => $stats['startedAttempts'] === 0)
            ->assertViewHas('attempts', fn ($attempts) => $attempts->total() === 0);
        $this->getJson(route('operations.index', ['date' => 'invalid']))->assertUnprocessable();
    }

    public function test_closing_marks_only_unstarted_scheduled_participants_absent_and_makeup_reuses_assignment(): void
    {
        [, $assignment] = $this->makeExam();
        $active = $this->additionalParticipant($assignment->examSession, 'S-002', 'Peserta Mengerjakan');
        $this->startAttempt($active);
        $cancelled = $this->additionalParticipant($assignment->examSession, 'S-003', 'Peserta Batal');
        $cancelled->update(['status' => AssignmentStatus::Cancelled]);
        $service = app(ExamSessionService::class);
        $service->update($assignment->examSession, ['status' => SessionStatus::Closed]);
        $service->update($assignment->examSession, ['status' => SessionStatus::Closed]);
        $this->assertSame(AssignmentStatus::Absent, $assignment->fresh()->status);
        $this->assertSame(AssignmentStatus::Completed, $active->fresh()->status);
        $this->assertSame(AssignmentStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertDatabaseCount('exam_attempts', 1);

        $makeup = $assignment->examSession->replicate();
        $makeup->fill(['source_session_id' => $assignment->exam_session_id, 'kind' => SessionKind::Makeup,
            'status' => SessionStatus::Published, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(90)]);
        $makeup->save();
        $moved = app(ExamAssignmentService::class)->moveToMakeup($assignment->fresh(), $makeup);
        $this->assertSame($assignment->id, $moved->id);
        $this->assertSame(AssignmentStatus::Scheduled, $moved->status);
        $this->assertSame($makeup->id, $moved->exam_session_id);
        $this->assertDatabaseCount('exam_assignments', 3);
    }

    public function test_closing_before_start_does_not_mark_students_absent(): void
    {
        [, $assignment] = $this->makeExam();
        $assignment->examSession->update(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(90)]);
        app(ExamSessionService::class)->update($assignment->examSession, ['status' => SessionStatus::Closed]);
        $this->assertSame(AssignmentStatus::Scheduled, $assignment->fresh()->status);
    }

    public function test_cancelled_assignment_and_closed_destination_cannot_be_used_for_makeup(): void
    {
        [, $assignment] = $this->makeExam();
        $assignment->update(['status' => AssignmentStatus::Cancelled]);
        $makeup = $assignment->examSession->replicate();
        $makeup->fill(['kind' => SessionKind::Makeup, 'source_session_id' => $assignment->exam_session_id]);
        $makeup->save();
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $data = ['assignment_id' => $assignment->id, 'makeup_session_id' => $makeup->id];
        $this->actingAs($committee)->post(route('scheduling.makeup.move'), $data)->assertSessionHasErrors('assignment');
        $assignment->update(['status' => AssignmentStatus::Scheduled]);
        $makeup->update(['status' => SessionStatus::Closed]);
        $this->post(route('scheduling.makeup.move'), $data)->assertSessionHasErrors('exam_session');
        $this->assertSame($assignment->exam_session_id, $assignment->fresh()->exam_session_id);
    }

    private function additionalParticipant(ExamSession $session, string $number, string $name): ExamAssignment
    {
        $student = Student::create(['school_class_id' => $session->assessmentSubject->school_class_id,
            'student_number' => $number, 'full_name' => $name, 'is_active' => true]);

        return ExamAssignment::create(['assessment_subject_id' => $session->assessment_subject_id,
            'student_id' => $student->id, 'exam_session_id' => $session->id,
            'status' => AssignmentStatus::Scheduled, 'assigned_at' => now()]);
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
