<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\PeriodStatus;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use App\Models\TrialQuestion;
use App\Models\TrialQuestionBank;
use App\Models\User;
use App\Services\Exams\ExamSecurityService;
use App\Services\Exams\TrialExamAttemptService;
use App\Services\Exams\TrialExamSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrialExamFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00'));
    }

    public function test_trial_exam_uses_strict_security_lists_participants_and_hides_scores(): void
    {
        [$trial, $question] = $this->makeTrial();

        $this->get(route('tryout.entry', $trial))
            ->assertOk()
            ->assertSee('Setiap jawaban PG benar bernilai tetap 2 poin')
            ->assertSee('Nama peserta diambil otomatis dari data siswa');

        $this->post(route('tryout.start', $trial), [
            'participant_name' => 'Nama Tidak Boleh Dipakai',
            'participant_identifier' => '1234567890',
            'access_code' => 'SJR-SMKBHG',
        ])->assertRedirect();

        $attempt = TrialAttempt::query()->firstOrFail();
        $student = Student::query()->where('nisn', '1234567890')->firstOrFail();
        $this->assertSame($student->id, $attempt->student_id);
        $this->assertSame('Peserta Uji Coba', $attempt->participant_name);
        $this->assertSame('1234567890', $attempt->participant_identifier);
        $this->get(route('tryout.show', [$trial, $attempt]))
            ->assertOk()
            ->assertSee('Pengamanan sama dengan Ujian Inti')
            ->assertSee('Pelanggaran 0/2')
            ->assertSee('Peserta Uji Coba')
            ->assertSee('1234567890')
            ->assertSee('Sejarah')
            ->assertSee('data-theme-toggle', false);

        $this->postJson(route('tryout.incident', [$trial, $attempt]), [
            'event_id' => (string) Str::uuid(),
            'category' => 'tab_hidden',
        ])->assertOk()->assertJson(['violations' => 1, 'locked' => false]);

        $this->travel(4)->seconds();
        $this->postJson(route('tryout.incident', [$trial, $attempt]), [
            'event_id' => (string) Str::uuid(),
            'category' => 'window_blur',
        ])->assertOk()->assertJson(['violations' => 2, 'locked' => true]);

        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $this->actingAs($admin)
            ->get(route('tryouts.participants', $trial))
            ->assertOk()
            ->assertSee('Peserta Uji Coba')
            ->assertSee('Terkunci')
            ->assertDontSee('100,00');
        $this->get(route('tryouts.progress', [
            'trialExam' => $trial,
            'student_ids' => [$student->id],
        ]))->assertOk()
            ->assertJsonPath('statistics.locked', 1)
            ->assertJsonPath('participants.'.$student->id.'.status', 'locked')
            ->assertJsonPath('participants.'.$student->id.'.violations', 2);
        $this->get(route('trial-question-banks.index'))
            ->assertOk()
            ->assertSee('Latihan Sejarah Kelas 10');
        $this->get(route('trial-question-banks.questions.index', $trial->questionBank))
            ->assertOk()
            ->assertSee('Proklamasi Indonesia terjadi pada?');
        $this->get(route('trial-question-banks.questions.export', $trial->questionBank))
            ->assertOk()->assertDownload();
        $this->get(route('tryouts.index'))
            ->assertOk()
            ->assertSee('Uji Coba Sejarah')
            ->assertSee('Kelas 10');
        $this->get(route('tryouts.cards', $trial))
            ->assertOk()
            ->assertSee('KARTU PESERTA UJIAN UJI COBA')
            ->assertSee('Peserta Uji Coba')
            ->assertSee('1234567890')
            ->assertSee('SJR-SMKBHG');
        $this->get(route('tryouts.cards.all'))
            ->assertOk()
            ->assertSee('KARTU SELURUH UJIAN UJI COBA')
            ->assertSee('Peserta Uji Coba')
            ->assertSee('Uji Coba Sejarah')
            ->assertSee('SJR-SMKBHG')
            ->assertSee(route('tryout.entry', $trial));

        $this->post(route('tryouts.attempts.reset-violations', [$trial, $attempt]), [
            'reason' => 'Gangguan teknis perangkat peserta.',
        ])->assertRedirect();
        $this->assertSame(0, $attempt->fresh()->violation_count);
        $this->assertNull($attempt->fresh()->security_locked_at);

        $this->post(route('tryout.submit', [$trial, $attempt]), [
            'answers' => [$question->id => 'A'],
        ])->assertRedirect(route('tryout.result', [$trial, $attempt]));

        $this->get(route('tryout.result', [$trial, $attempt]))
            ->assertOk()
            ->assertSee('Jawaban berhasil dikumpulkan')
            ->assertDontSee('100,00')
            ->assertDontSee('1 jawaban benar');
        $this->assertSame('2.00', $attempt->fresh()->score);
    }

    public function test_trial_displays_bank_order_when_started_and_resumed(): void
    {
        [$trial] = $this->makeTrial();
        $bank = $trial->questionBank;
        $statement = "Perhatikan pernyataan berikut.\n(1) Pernyataan pertama\n(2) Pernyataan kedua\n(3) Pernyataan ketiga";
        $questionText = 'Pilih kombinasi yang tepat.';
        $attachment = 'trial-questions/diagram.png';
        $optionA = "(1) Jawaban pertama\n(2) Jawaban kedua";
        $bank->questions()->create([
            'question_text' => 'Soal posisi ketiga',
            'options' => ['A' => 'Jawaban ketiga'],
            'correct_answer' => 'A',
            'points' => 2,
            'position' => 3,
        ]);
        $bank->questions()->create([
            'statement' => $statement,
            'question_image_path' => $attachment,
            'question_text' => $questionText,
            'options' => ['B' => 'Pilihan setelah A', 'A' => $optionA],
            'correct_answer' => 'A',
            'points' => 2,
            'position' => 2,
        ]);
        $student = Student::query()->where('nisn', '1234567890')->firstOrFail();
        $show = [
            'Proklamasi Indonesia terjadi pada?',
            $statement,
            $attachment,
            $questionText,
            $optionA,
            'Pilihan setelah A',
            'Soal posisi ketiga',
        ];

        $this->actingAs($student->user)
            ->post(route('tryout.start', $trial), ['access_code' => 'SJR-SMKBHG'])
            ->assertRedirect();

        $attempt = TrialAttempt::query()->firstOrFail();
        $showRoute = route('tryout.show', [$trial, $attempt]);
        $this->get($showRoute)->assertOk()->assertSeeInOrder($show)
            ->assertSessionMissing('trial_question_order_'.$attempt->id);

        $this->post(route('tryout.start', $trial), ['access_code' => 'SJR-SMKBHG'])
            ->assertRedirect($showRoute)
            ->assertSessionMissing('trial_question_order_'.$attempt->id);
        $this->get($showRoute)->assertOk()->assertSeeInOrder($show);
    }

    public function test_trial_formatting_is_saved_and_rendered_for_the_participant(): void
    {
        [$trial] = $this->makeTrial();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $payload = [
            'text_format' => 'html',
            'question_text' => '<p>Choose the <em>correct tense</em>.</p>',
            'statement' => '<p>Read the <strong>following passage</strong>.</p>',
            'option_a' => '<p><u>She has arrived.</u></p>',
            'option_b' => 'She have arrived.', 'option_c' => 'She arriving.',
            'option_d' => 'She arrive yesterday.', 'option_e' => 'She are here.',
            'correct_answer' => 'A',
        ];
        $this->actingAs($admin)->post(route('trial-question-banks.questions.store', $trial->questionBank), $payload)
            ->assertSessionHasNoErrors();
        $question = $trial->questionBank->questions()->reorder('id', 'desc')->firstOrFail();
        $this->assertSame('html', $question->text_format);
        $this->put(route('trial-question-banks.questions.update', [$trial->questionBank, $question]), $payload)
            ->assertSessionHasNoErrors();
        $student = Student::query()->where('nisn', '1234567890')->firstOrFail();
        $this->actingAs($student->user)->post(route('tryout.start', $trial), ['access_code' => 'SJR-SMKBHG'])
            ->assertRedirect();
        $attempt = TrialAttempt::query()->firstOrFail();
        $this->get(route('tryout.show', [$trial, $attempt]))->assertOk()
            ->assertSee('<strong>following passage</strong>', false)
            ->assertSee('<em>correct tense</em>', false)
            ->assertSee('<u>She has arrived.</u>', false);
    }

    public function test_trial_question_bank_cannot_create_more_than_one_trial_exam(): void
    {
        [$trial] = $this->makeTrial();
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('tryouts.store'), [
                'trial_question_bank_id' => $trial->trial_question_bank_id,
                'title' => 'Uji Coba Sejarah Duplikat',
                'access_code' => 'SJR-DUPLIKAT',
                'duration_minutes' => 60,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('trial_question_bank_id');

        $this->assertSame(1, TrialExam::query()->count());
        $this->get(route('tryouts.index'))
            ->assertOk()
            ->assertViewHas('banks', fn ($banks): bool => $banks->doesntContain('id', $trial->trial_question_bank_id));
    }

    public function test_trial_exam_can_use_same_subject_component_when_grade_is_not_scheduled(): void
    {
        [$existingTrial] = $this->makeTrial();
        $bank = TrialQuestionBank::create([
            'subject_id' => $existingTrial->questionBank->subject_id,
            'grade_level' => 11,
            'name' => 'Latihan Sejarah Kelas 11',
        ]);
        TrialQuestion::create([
            'trial_question_bank_id' => $bank->id,
            'question_text' => 'Contoh pertanyaan Sejarah kelas 11?',
            'options' => ['A' => 'Jawaban A', 'B' => 'Jawaban B', 'C' => 'Jawaban C', 'D' => 'Jawaban D', 'E' => 'Jawaban E'],
            'correct_answer' => 'A',
            'points' => 1,
            'position' => 1,
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('tryouts.store'), [
                'trial_question_bank_id' => $bank->id,
                'title' => 'Uji Coba Sejarah Kelas 11',
                'access_code' => 'SJR-KELAS-11',
                'duration_minutes' => 60,
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $created = TrialExam::query()
            ->where('trial_question_bank_id', $bank->id)
            ->firstOrFail();

        $this->assertSame($existingTrial->assessment_subject_id, $created->assessment_subject_id);
        $this->assertTrue($created->is_active);
    }

    public function test_trial_and_core_exam_keep_the_same_strict_security_policy(): void
    {
        $this->assertSame(2, ExamSecurityService::LIMIT);
        $this->assertSame(ExamSecurityService::LIMIT, TrialExamSecurityService::LIMIT);
        $this->assertSame(ExamSecurityService::CLIENT_CATEGORIES, TrialExamSecurityService::CLIENT_CATEGORIES);
    }

    public function test_trial_resets_share_a_two_action_limit_across_roles_without_losing_answers_or_audit(): void
    {
        [$trial, $question] = $this->makeTrial();
        $this->post(route('tryout.start', $trial), ['participant_identifier' => '1234567890', 'access_code' => 'SJR-SMKBHG'])->assertRedirect();
        $attempt = TrialAttempt::firstOrFail();
        $this->putJson(route('tryout.answer', [$trial, $attempt, $question]), ['answer' => 'A'])->assertOk();
        $this->assertSame(1, $attempt->answers()->count());
        $deadline = app(TrialExamAttemptService::class)->deadline($attempt)->toIso8601String();
        foreach ([UserRole::Principal, UserRole::SuperAdmin] as $index => $role) {
            $this->postJson(route('tryout.incident', [$trial, $attempt]), ['category' => 'tab_hidden', 'event_id' => (string) Str::uuid()])->assertJson(['violations' => 1]);
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->post(route('tryouts.attempts.reset-violations', [$trial, $attempt]), ['reason' => 'Gangguan perangkat peserta telah diperiksa.'])->assertSessionHasNoErrors();
            $this->assertSame($index + 1, $attempt->fresh()->security_reset_count);
        }
        $this->travel(4)->seconds();
        $this->postJson(route('tryout.incident', [$trial, $attempt]), ['category' => 'tab_hidden', 'event_id' => (string) Str::uuid()])->assertOk();
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $this->actingAs($committee)->post(route('tryouts.attempts.reset-violations', [$trial, $attempt]), ['reason' => 'Mencoba reset ketiga oleh petugas lain.'])->assertSessionHasErrors('security');
        $this->assertSame(2, $attempt->fresh()->security_reset_count);
        $this->assertSame(1, $attempt->fresh()->violation_count);
        $this->assertSame(2, $attempt->securityIncidents()->where('category', 'supervisor_reset')->count());
        $this->assertSame($deadline, app(TrialExamAttemptService::class)->deadline($attempt->fresh())->toIso8601String());
        $this->assertSame(1, $attempt->answers()->count());
    }

    public function test_trial_teacher_sees_incident_history_for_their_classes_and_cannot_reset(): void
    {
        [$trial] = $this->makeTrial();
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $trial->assessmentSubject->update(['teacher_user_id' => $teacher->id]);
        $otherClass = SchoolClass::create(['academic_year_id' => $trial->assessmentSubject->assessmentPeriod->academic_year_id, 'name' => 'X-2', 'grade_level' => 10]);
        $other = Student::create(['school_class_id' => $otherClass->id, 'student_number' => 'OTHER', 'full_name' => 'Siswa Di Luar Kelas Guru', 'is_active' => true]);
        $this->post(route('tryout.start', $trial), ['participant_identifier' => '1234567890', 'access_code' => 'SJR-SMKBHG'])->assertRedirect();
        $attempt = TrialAttempt::firstOrFail();
        $this->postJson(route('tryout.incident', [$trial, $attempt]), ['category' => 'tab_hidden', 'event_id' => (string) Str::uuid()])->assertOk();
        $this->actingAs($teacher)->get(route('tryouts.participants', $trial))->assertOk()->assertSee('Berpindah tab/aplikasi')->assertDontSee('Siswa Di Luar Kelas Guru')->assertDontSee('Reset ke 0/2');
        $this->get(route('tryouts.progress', [$trial, 'student_ids' => [$attempt->student_id, $other->id]]))->assertOk()->assertJsonMissingPath('participants.'.$other->id);
        $this->post(route('tryouts.attempts.reset-violations', [$trial, $attempt]), ['reason' => 'Guru mencoba mengubah pelanggaran.'])->assertForbidden();
        $otherTeacher = User::factory()->create(['role' => UserRole::Teacher]);
        $this->actingAs($otherTeacher)->get(route('tryouts.participants', $trial))->assertForbidden();
        $this->assertSame(1, $attempt->fresh()->violation_count);
    }

    public function test_trial_legacy_reset_actions_are_counted_by_the_migration(): void
    {
        [$trial] = $this->makeTrial();
        $this->post(route('tryout.start', $trial), ['participant_identifier' => '1234567890', 'access_code' => 'SJR-SMKBHG'])->assertRedirect();
        $attempt = TrialAttempt::firstOrFail();
        foreach ([1, 2] as $number) {
            $attempt->securityIncidents()->create(['category' => 'supervisor_reset', 'severity' => 0, 'occurred_at' => now(), 'details' => []]);
        }
        $migration = require database_path('migrations/2026_11_13_000100_limit_exam_security_resets.php');
        $migration->down();
        $migration->up();
        $this->assertSame(2, $attempt->fresh()->security_reset_count);
    }

    public function test_trial_reentry_reuses_the_attempt_and_preserves_used_reset_allowance(): void
    {
        [$trial] = $this->makeTrial();
        $student = Student::where('nisn', '1234567890')->firstOrFail();
        $this->post(route('tryout.start', $trial), ['participant_identifier' => $student->nisn, 'access_code' => 'SJR-SMKBHG'])->assertRedirect();
        $attempt = TrialAttempt::firstOrFail();
        $token = $attempt->session_token_hash;
        $attempt->forceFill(['security_reset_count' => 2, 'violation_count' => 2, 'security_locked_at' => now()])->save();
        $deadline = app(TrialExamAttemptService::class)->deadline($attempt)->toIso8601String();
        $this->actingAs($student->user)->post(route('tryout.start', $trial), ['access_code' => 'SJR-SMKBHG'])->assertRedirect(route('tryout.show', [$trial, $attempt]));
        $this->assertDatabaseCount('trial_attempts', 1);
        $this->assertSame(2, $attempt->fresh()->security_reset_count);
        $this->assertSame(2, $attempt->fresh()->violation_count);
        $this->assertNotNull($attempt->fresh()->security_locked_at);
        $this->assertNotSame($token, $attempt->fresh()->session_token_hash);
        $this->assertSame($deadline, app(TrialExamAttemptService::class)->deadline($attempt->fresh())->toIso8601String());
    }

    private function makeTrial(): array
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->addMonths(10)->toDateString(),
            'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'X-1',
            'grade_level' => 10,
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        Student::create([
            'user_id' => $studentUser->id,
            'school_class_id' => $class->id,
            'student_number' => '1233',
            'nisn' => '1234567890',
            'full_name' => 'Peserta Uji Coba',
            'is_active' => true,
        ]);
        $subject = Subject::create(['code' => 'SJR', 'name' => 'Sejarah', 'is_active' => true]);
        $period = AssessmentPeriod::create([
            'academic_year_id' => $year->id,
            'code' => 'AAS-UJI',
            'name' => 'AAS Uji Coba',
            'type' => AssessmentType::AAS,
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
        $bank = TrialQuestionBank::create([
            'subject_id' => $subject->id,
            'grade_level' => 10,
            'name' => 'Latihan Sejarah Kelas 10',
        ]);
        $question = TrialQuestion::create([
            'trial_question_bank_id' => $bank->id,
            'question_text' => 'Proklamasi Indonesia terjadi pada?',
            'options' => ['A' => '17 Agustus 1945', 'B' => '18 Agustus 1945', 'C' => '1 Juni 1945', 'D' => '20 Mei 1908', 'E' => '28 Oktober 1928'],
            'correct_answer' => 'A',
            'points' => 1,
            'position' => 1,
        ]);
        $trial = TrialExam::create([
            'assessment_subject_id' => $component->id,
            'trial_question_bank_id' => $bank->id,
            'title' => 'Uji Coba Sejarah',
            'slug' => 'uji-coba-sejarah',
            'access_code' => 'SJR-SMKBHG',
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        return [$trial, $question];
    }
}
