<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\PeriodStatus;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Campus;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use App\Models\TrialLocation;
use App\Models\TrialQuestion;
use App\Models\TrialQuestionBank;
use App\Models\User;
use App\Services\Exams\ExamSecurityService;
use App\Services\Exams\StudentOptionOrder;
use App\Services\Exams\TrialExamAttemptService;
use App\Services\Exams\TrialExamSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('data-theme="light"', false)
            ->assertSeeInOrder(['Peringatan sebelum memulai ujian', 'Siapkan NIS atau NISN milik sendiri', 'Jangan tarik bilah notifikasi', 'Pelanggaran kedua', 'Saya sudah membaca dan memahami aturan'])
            ->assertSee('required aria-describedby="trial-exam-rules-title"', false)
            ->assertDontSee('Absensi lokasi sebelum ujian')
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
            ->assertSee('images/logo-ypmi-bahagia.jpeg')->assertSee('2026/2027')
            ->assertSee('Peserta Uji Coba')
            ->assertSee('1234567890')
            ->assertSee('SJR-SMKBHG');
        $this->get(route('tryouts.cards.all'))
            ->assertOk()
            ->assertSee('KARTU SELURUH UJIAN UJI COBA')
            ->assertSee('images/logo-ypmi-bahagia.jpeg')->assertSee('2026/2027')
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
            ->assertSee('Kembali ke Dashboard Siswa')->assertSee(route('dashboard'))
            ->assertDontSee('100,00')
            ->assertDontSee('1 jawaban benar');
        $this->assertSame('2.00', $attempt->fresh()->score);
    }

    public function test_trial_cards_print_the_actual_student_login_and_only_available_passwords(): void
    {
        [$trial] = $this->makeTrial();
        $student = Student::query()->where('nisn', '1234567890')->firstOrFail();
        $user = $student->user;
        $user->username = $student->student_number;
        $user->setStudentPassword('Simulasi-Contoh');
        $user->save();
        $cards = [route('tryouts.cards', $trial), route('tryouts.cards.all')];
        $committee = User::factory()->create(['role' => UserRole::Committee]);

        foreach ($cards as $url) {
            $this->actingAs($committee)->get($url)->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertSeeInOrder(['Username (NIS)', $student->student_number, 'Password', 'Simulasi-Contoh', 'halaman login CBT'])
                ->assertSee(route('login'));
            $this->actingAs($user)->get($url)->assertForbidden();
        }

        $user->update(['username' => $student->nisn, 'password' => $student->nisn]);
        $this->actingAs($committee)->get($cards[0])->assertOk()
            ->assertSeeInOrder(['Username (NISN)', $student->nisn, 'Password', $student->nisn])
            ->assertDontSee('Simulasi-Contoh');

        $user->update(['password' => 'Tidak-tersedia-di-kartu']);
        $this->get($cards[1])->assertOk()
            ->assertSee('Konfirmasi ke panitia')->assertDontSee('Tidak-tersedia-di-kartu')->assertDontSee('Simulasi-Contoh');
    }

    public function test_trial_rejects_missing_or_invalid_answers_before_the_deadline_and_accepts_saved_answers(): void
    {
        [$trial, $question] = $this->makeTrial();
        $student = Student::query()->where('nisn', '1234567890')->firstOrFail();
        $this->actingAs($student->user)->post(route('tryout.start', $trial), ['access_code' => 'SJR-SMKBHG'])->assertSessionHasNoErrors();
        $attempt = $trial->currentAttempts()->sole();
        $show = route('tryout.show', [$trial, $attempt]);
        $submit = route('tryout.submit', [$trial, $attempt]);
        $this->from($show)->post($submit)->assertRedirect($show)->assertSessionHasErrors('submission');
        $this->from($show)->post($submit, ['answers' => [$question->id => 'X']])->assertSessionHasErrors('submission');
        $this->assertSame('in_progress', $attempt->fresh()->status);
        $this->assertSame(0, $attempt->answers()->count());

        $this->putJson(route('tryout.answer', [$trial, $attempt, $question]), ['answer' => 'A'])->assertOk();
        $this->post($submit)->assertRedirect(route('tryout.result', [$trial, $attempt]));
        $this->get(route('dashboard'))->assertRedirect(route('student.exams.index'));
        $this->assertSame('submitted', $attempt->fresh()->status);
    }

    public function test_trial_deadline_allows_automatic_submission_with_unanswered_questions(): void
    {
        [$trial] = $this->makeTrial();
        $student = Student::query()->where('nisn', '1234567890')->firstOrFail();
        $this->actingAs($student->user)->post(route('tryout.start', $trial), ['access_code' => 'SJR-SMKBHG'])->assertSessionHasNoErrors();
        $attempt = $trial->currentAttempts()->sole();

        $this->travel(60)->minutes();
        $this->post(route('tryout.submit', [$trial, $attempt]))->assertRedirect(route('tryout.result', [$trial, $attempt]));
        $this->assertSame('submitted', $attempt->fresh()->status);
        $this->assertNull($attempt->answers()->sole()->answer);
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
        $this->get($showRoute)->assertOk()->assertSeeInOrder($show)->assertSee($optionA)->assertSee('Pilihan setelah A');
    }

    public function test_asas_migration_renames_display_text_without_changing_period_links_or_access_codes(): void
    {
        [$trial] = $this->makeTrial();
        $period = $trial->assessmentSubject->assessmentPeriod;
        $period->update(['name' => 'AAS Ganjil — Asesmen Akhir Semester', 'type' => AssessmentType::ASAS]);
        $trial->update(['title' => 'Simulasi AAS Ganjil']);
        $trial->questionBank->update(['name' => 'Latihan AAS Sejarah']);
        $migration = require database_path('migrations/2026_11_16_000100_rename_aas_display_names_to_asas.php');
        $migration->up();
        $migration->up();
        $this->assertSame('ASAS Ganjil — Asesmen Sumatif Akhir Semester', $period->fresh()->name);
        $this->assertSame('ASAS', $period->fresh()->type->label());
        $this->assertSame('aas', $period->fresh()->getRawOriginal('type'));
        $this->assertSame('Simulasi ASAS Ganjil', $trial->fresh()->title);
        $this->assertSame('Latihan ASAS Sejarah', $trial->questionBank->fresh()->name);
        $this->assertSame($trial->access_code, $trial->fresh()->access_code);
        $this->assertSame($trial->slug, $trial->fresh()->slug);
        $this->assertSame($period->code, $period->fresh()->code);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->get(route('scheduling.index'))->assertOk()->assertSee('ASAS')->assertDontSee('>AAS<', false);
    }

    public function test_trial_options_keep_order_attachments_checked_answer_and_correct_score(): void
    {
        [$trial] = $this->makeTrial();
        $question = $trial->questionBank->questions()->firstOrFail();
        $question->update(['option_file_paths' => ['A' => 'trial-options/benar.png', 'B' => 'trial-options/lain.pdf']]);
        $student = Student::query()->where('nisn', '1234567890')->firstOrFail();
        $this->actingAs($student->user)->post(route('tryout.start', $trial), ['access_code' => 'SJR-SMKBHG'])->assertRedirect();
        $attempt = TrialAttempt::query()->firstOrFail();
        $route = route('tryout.show', [$trial, $attempt]);
        $expected = app(StudentOptionOrder::class)->forQuestion($attempt, $question)->keys()->all();
        $getOptions = function (string $html) use ($question, $expected): array {
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);
            $inputs = $xpath->query('//input[@name="answers['.$question->id.']"]');
            $keys = [];
            foreach ($inputs as $index => $input) {
                $key = $input->getAttribute('value');
                $keys[] = $key;
                $this->assertSame(chr(65 + $index).'.', trim($xpath->query('.//strong', $input->parentNode)->item(0)->textContent));
                $this->assertStringContainsString($question->options[$key], $input->parentNode->textContent);
                if ($question->option_file_paths[$key] ?? null) {
                    $this->assertStringContainsString($question->option_file_paths[$key], $dom->saveHTML($input->parentNode->parentNode));
                }
            }
            $this->assertSame($expected, $keys);

            return [$xpath, $inputs];
        };
        $getOptions($this->get($route)->assertOk()->getContent());
        $this->putJson(route('tryout.answer', [$trial, $attempt, $question]), ['answer' => 'A'])->assertOk();
        [$xpath] = $getOptions($this->get($route)->assertOk()->getContent());
        $this->assertSame('A', $xpath->query('//input[@name="answers['.$question->id.']"][@checked]')->item(0)->getAttribute('value'));
        $this->post(route('tryout.submit', [$trial, $attempt]), ['answers' => [$question->id => 'A']])->assertRedirect();
        $this->assertSame('2.00', $attempt->fresh()->score);
        $this->assertSame(1, $attempt->refresh()->correct_answers);
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], array_keys($question->fresh()->options));
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

    public function test_committee_can_delete_linked_bank_reversibly_without_losing_responses(): void
    {
        [$trial, $question] = $this->makeTrial();
        $attempt = TrialAttempt::create([
            'trial_exam_id' => $trial->id, 'participant_name' => 'Peserta lama',
            'participant_identifier' => 'old-1', 'session_token_hash' => hash('sha256', 'old-token'),
            'status' => 'submitted', 'started_at' => now()->subHour(), 'submitted_at' => now(),
            'total_questions' => 1,
        ]);
        $attempt->answers()->create(['trial_question_id' => $question->id, 'answer' => 'A', 'is_correct' => true, 'points_awarded' => 2]);
        $admin = User::factory()->create(['role' => UserRole::Committee, 'must_change_password' => false, 'is_active' => true]);
        $this->actingAs($admin)->get(route('trial-question-banks.index'))->assertOk()->assertSee('Hapus');
        $this->delete(route('trial-question-banks.destroy', $trial->questionBank))->assertSessionHasNoErrors();
        $this->assertNotNull($trial->questionBank->fresh()->archived_at);
        $this->assertFalse($trial->fresh()->is_active);
        $this->assertDatabaseHas('trial_responses', ['trial_attempt_id' => $attempt->id, 'trial_question_id' => $question->id]);
        $this->assertDatabaseHas('trial_questions', ['id' => $question->id]);
        $this->get(route('trial-question-banks.index'))->assertDontSee('Latihan Sejarah Kelas 10');
        $this->get(route('trial-question-banks.index', ['archived' => 1]))->assertOk()->assertSee('Latihan Sejarah Kelas 10')->assertSee('Pulihkan');
        $this->get(route('tryouts.cards.all'))->assertOk()->assertViewHas('trialExams', fn ($exams) => $exams->isEmpty());
        $this->get(route('tryout.entry', $trial))->assertOk()->assertViewHas('available', false);
        $this->patch(route('tryouts.toggle', $trial))->assertSessionHasErrors('bank');
        $this->post(route('tryout.start', $trial), ['participant_identifier' => '1234567890', 'access_code' => 'SJR-SMKBHG'])->assertSessionHasErrors('access_code');
        $this->get(route('tryouts.participants', $trial))->assertOk();
        $this->patch(route('trial-question-banks.restore', $trial->questionBank))->assertSessionHasNoErrors();
        $this->assertNull($trial->questionBank->fresh()->archived_at);
        $this->assertFalse($trial->fresh()->is_active);
        $this->patch(route('tryouts.toggle', $trial))->assertSessionHasNoErrors();
        $this->assertTrue($trial->fresh()->isAvailable());
    }

    public function test_bank_with_student_in_progress_cannot_be_deleted(): void
    {
        [$trial] = $this->makeTrial();
        $this->post(route('tryout.start', $trial), ['participant_identifier' => '1234567890', 'access_code' => 'SJR-SMKBHG'])->assertRedirect();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'must_change_password' => false, 'is_active' => true]);
        $this->actingAs($admin)->delete(route('trial-question-banks.destroy', $trial->questionBank))->assertSessionHasErrors('bank');
        $this->assertNull($trial->questionBank->fresh()->archived_at);
        $this->assertTrue($trial->fresh()->is_active);
        $this->assertDatabaseCount('trial_questions', 1);
    }

    public function test_pack_picker_imports_only_the_selection_and_does_not_delete_other_banks(): void
    {
        [$trial] = $this->makeTrial();
        $trial->questionBank->update(['grade_level' => 12]);
        $teacher = User::factory()->create(['role' => UserRole::Teacher, 'must_change_password' => false, 'is_active' => true]);
        $this->actingAs($teacher)->post(route('trial-question-banks.import-pack'), ['pack_key' => 'bin-10'])->assertForbidden();
        $this->delete(route('trial-question-banks.destroy', $trial->questionBank))->assertForbidden();
        $this->patch(route('trial-question-banks.restore', $trial->questionBank))->assertForbidden();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'must_change_password' => false, 'is_active' => true]);
        $this->actingAs($admin)->post(route('trial-question-banks.import-pack'))->assertSessionHasErrors('pack_key');
        $this->assertDatabaseCount('trial_questions', 1);
        $this->post(route('trial-question-banks.import-pack'), ['pack_key' => 'unknown-12'])->assertSessionHasErrors('pack_key');
        $this->post(route('trial-question-banks.import-pack'), ['pack_key' => 'bin-10', 'archive_history_12' => 1])->assertSessionHasNoErrors();
        $bank = TrialQuestionBank::query()->where('source_pack_key', 'bin-10')->sole();
        $this->assertSame('Try Out Bahasa Indonesia 10', $bank->name);
        $this->assertDatabaseCount('trial_questions', 21);
        $this->assertDatabaseCount('trial_question_banks', 2);
        $this->assertNull($trial->questionBank->fresh()->archived_at);
        $this->assertTrue($trial->fresh()->is_active);
        $this->post(route('trial-question-banks.import-pack'), ['pack_key' => 'bin-10'])->assertRedirect(route('trial-question-banks.questions.index', $bank));
        $this->assertDatabaseCount('trial_questions', 21);
        $this->get(route('trial-question-banks.index', ['pack_grade_level' => 10]))->assertOk()->assertSee('Buka soal')->assertDontSee('Paket latihan 100 soal');
        $this->delete(route('trial-question-banks.destroy', $bank))->assertSessionHasNoErrors();
        $this->post(route('trial-question-banks.import-pack'), ['pack_key' => 'bin-10'])->assertRedirect(route('trial-question-banks.index', ['archived' => 1]));
        $this->assertNotNull($bank->fresh()->archived_at);
        $this->assertDatabaseCount('trial_questions', 21);
    }

    public function test_simulation_attendance_checks_the_radius_and_gates_start_in_the_same_browser(): void
    {
        [$trial] = $this->makeTrial();
        $location = TrialLocation::create([
            'name' => 'Sekolah Simulasi', 'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meters' => 100, 'max_accuracy_meters' => 50,
        ]);
        $trial->update(['trial_location_id' => $location->id]);
        Student::create([
            'school_class_id' => $trial->assessmentSubject->school_class_id,
            'student_number' => '1234', 'nisn' => '1234567891', 'full_name' => 'Siswa Belum Absen', 'is_active' => true,
        ]);

        $attendance = route('tryout.attendance', $trial);
        $start = route('tryout.start', $trial);
        $credentials = ['participant_identifier' => '1234567890', 'access_code' => $trial->access_code];
        $inside = [...$credentials, 'latitude' => -6.2, 'longitude' => 106.8, 'accuracy_meters' => 12];

        $this->get(route('tryout.entry', $trial))->assertOk()
            ->assertSee('Sekolah Simulasi')->assertSee($attendance)
            ->assertSeeInOrder(['Absensi lokasi sebelum ujian', 'Waktu ujian belum berjalan saat Anda absen', 'Jika absensi gagal', 'Anda berada di lokasi ujian yang benar', 'Panduan jika absensi gagal'])
            ->assertSee('Ambil Lokasi &amp; Absen Simulasi', false)
            ->assertSee('type="submit" disabled', false);
        $this->post($start, $credentials)->assertSessionHasErrors('location');
        $this->postJson($attendance, [...$inside, 'access_code' => 'SALAH'])->assertUnprocessable()
            ->assertJsonValidationErrors('access_code');
        $this->postJson($attendance, [...$inside, 'accuracy_meters' => 80])->assertUnprocessable()
            ->assertJsonValidationErrors('location');
        $this->postJson($attendance, [...$inside, 'latitude' => -6.21])->assertUnprocessable()
            ->assertJsonValidationErrors('location');
        $this->assertDatabaseCount('trial_checkins', 0);

        $this->postJson($attendance, $inside)->assertOk()->assertJsonPath('message', 'Absensi simulasi terverifikasi. Anda bisa mulai ujian.');
        $this->postJson($attendance, $inside)->assertOk();
        $this->assertDatabaseCount('trial_checkins', 1);
        $this->assertDatabaseCount('daily_checkins', 0);
        $this->assertDatabaseCount('trial_attempts', 0);
        $this->assertSame('X-1', $trial->checkins()->sole()->class_name);

        $this->post($start, [...$credentials, 'participant_identifier' => '1234567891'])->assertSessionHasErrors('location');
        $this->flushSession();
        $this->post($start, $credentials)->assertSessionHasErrors('location');
        $this->postJson($attendance, $inside)->assertOk();
        $this->post($start, $credentials)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('trial_attempts', 1);
    }

    public function test_configuration_is_removable_after_participant_restart_and_questions_can_be_reused(): void
    {
        [$trial, $question] = $this->makeTrial();
        $student = Student::query()->sole();
        $this->post(route('tryout.start', $trial), [
            'participant_identifier' => $student->student_number, 'access_code' => $trial->access_code,
        ])->assertSessionHasNoErrors();
        $attempt = $trial->currentAttempts()->sole();
        $delete = route('tryouts.destroy', $trial);
        app(TrialExamAttemptService::class)->saveAnswer($attempt, $question, 'A');
        $trial->checkins()->create([
            'student_id' => $student->id, 'class_name' => 'X-1',
            'distance_meters' => 5, 'accuracy_meters' => 12, 'verified_at' => now(),
        ]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Committee]));
        $this->get(route('tryouts.index'))->assertOk()->assertDontSee('Hapus konfigurasi');
        $this->delete($delete)->assertSessionHasErrors('trial_exam');
        $this->post(route('tryouts.attempts.restart', [$trial, $attempt]))->assertSessionHasNoErrors();
        $this->get(route('tryouts.index'))->assertOk()->assertSee('Hapus konfigurasi');
        $bank = $trial->questionBank;
        $this->delete($delete)->assertSessionHasNoErrors();
        $this->get(route('tryout.entry', $trial))->assertNotFound();
        $this->assertDatabaseCount('trial_attempts', 0);
        $this->assertDatabaseCount('trial_checkins', 0);
        $this->assertDatabaseCount('trial_responses', 0);
        $this->assertDatabaseCount('trial_security_incidents', 0);
        $this->assertSame($question->id, $bank->questions()->sole()->id);
        $this->get(route('tryouts.index'))->assertOk()->assertViewHas('banks', fn ($banks) => $banks->contains($bank));
    }

    public function test_trial_location_settings_and_printed_attendance_are_separate_from_core(): void
    {
        [$trial] = $this->makeTrial();
        $settings = ['name' => 'Kampus Utama', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meters' => 100, 'max_accuracy_meters' => 50];
        Campus::create([...$settings, 'is_active' => true]);
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $report = route('tryouts.attendance.print', $trial);
        $this->actingAs($teacher)->get(route('trial-locations.index'))->assertForbidden();
        $this->get($report)->assertForbidden();

        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $this->actingAs($committee)->post(route('trial-locations.store'), $settings)->assertSessionHasNoErrors();
        $location = TrialLocation::query()->sole();
        $this->get(route('trial-locations.index'))->assertOk()->assertSee('Kampus Utama');
        $this->get(route('tryouts.edit', $trial))->assertOk()->assertSee('GPS wajib');
        $changes = [
            'title' => $trial->title, 'access_code' => $trial->access_code,
            'duration_minutes' => 60, 'is_active' => '1', 'trial_location_id' => $location->id,
        ];
        $this->put(route('tryouts.update', $trial), $changes)->assertSessionHasNoErrors();
        $this->assertSame($location->id, $trial->refresh()->trial_location_id);
        $this->get(route('tryouts.participants', $trial))->assertOk()->assertSee($report);
        $this->get($report)->assertOk()->assertSee('0 hadir terverifikasi · 1 belum absen');

        $trial->checkins()->create([
            'student_id' => Student::query()->sole()->id,
            'class_name' => 'X-1', 'distance_meters' => 5, 'accuracy_meters' => 12, 'verified_at' => now(),
        ]);
        $this->put(route('tryouts.update', $trial), [...$changes, 'trial_location_id' => null])
            ->assertSessionHasErrors('trial_location_id');
        $this->delete(route('tryouts.destroy', $trial))->assertSessionHasErrors('trial_exam');
        $this->actingAs(User::factory()->create(['role' => UserRole::Proctor]))->get($report)->assertOk()
            ->assertSee('Laporan Absensi Simulasi')->assertSee('1 hadir terverifikasi · 0 belum absen')
            ->assertSee('Peserta Uji Coba')->assertSee('5 m / ±12 m')->assertSee('Cetak / Simpan PDF')
            ->assertDontSee('106.8');
        $this->assertDatabaseCount('campuses', 1);
        $this->assertDatabaseCount('trial_attempts', 0);
        $this->assertDatabaseCount('daily_checkins', 0);
    }

    public function test_staff_can_print_and_copy_trial_links_by_grade(): void
    {
        [$trial] = $this->makeTrial();
        $trial->update(['starts_at' => now()->addDay()]);
        $grade11 = TrialQuestionBank::create([
            'subject_id' => $trial->questionBank->subject_id,
            'grade_level' => 11,
            'name' => 'Latihan Sejarah Kelas 11',
        ]);
        $otherTrial = $trial->replicate()->fill([
            'trial_question_bank_id' => $grade11->id,
            'title' => 'Simulasi Sejarah Kelas 11',
            'slug' => 'simulasi-sejarah-11',
            'access_code' => 'SJR-11',
            'is_active' => false,
        ]);
        $otherTrial->save();
        $archivedBank = $grade11->replicate()->fill(['name' => 'Bank arsip']);
        $archivedBank->archived_at = now();
        $archivedBank->save();
        $archivedTrial = $trial->replicate()->fill([
            'trial_question_bank_id' => $archivedBank->id,
            'slug' => 'ujian-sudah-diarsipkan',
            'access_code' => 'JANGAN-BAGIKAN',
        ]);
        $archivedTrial->save();

        $links = route('tryouts.links');
        foreach ([UserRole::Student, UserRole::Teacher, UserRole::Proctor] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get($links)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::Principal]));
        $this->get(route('operations.index'))->assertOk()->assertSee($links);
        $this->get(route('tryouts.participants', $trial))->assertOk()
            ->assertSee(route('tryouts.links', ['grade' => 10]));
        $this->get($links)->assertOk()
            ->assertSeeInOrder(['Tautan Ujian Uji Coba Kelas 10', 'Tautan Ujian Uji Coba Kelas 11'])
            ->assertSee('Cetak / Simpan PDF')
            ->assertSee('Salin Teks')
            ->assertSee('Dibuka 06/11/2026 10:00')
            ->assertSee('Nonaktif')
            ->assertDontSee('JANGAN-BAGIKAN')
            ->assertViewHas('shareText', fn (string $text): bool =>
                str_contains($text, route('tryout.entry', $trial)."\nKode akses SJR-SMKBHG")
                && str_contains($text, route('tryout.entry', $otherTrial)."\nKode akses SJR-11"));
        $this->get(route('tryouts.links', ['grade' => 11]))->assertOk()
            ->assertSee(route('tryout.entry', $otherTrial))
            ->assertDontSee(route('tryout.entry', $trial).'"', false)
            ->assertViewHas('shareText', fn (string $text): bool => ! str_contains($text, 'SJR-SMKBHG'));
        $this->get(route('tryouts.links', ['grade' => 12]))->assertOk()
            ->assertSee('Belum ada tautan Ujian Uji Coba untuk Kelas 12.');
    }

    public function test_illustrated_history_pack_imports_and_displays_in_a_trial_exam(): void
    {
        Storage::fake('public');
        [$existingTrial] = $this->makeTrial();
        $admin = User::factory()->create(['role' => UserRole::Committee, 'must_change_password' => false, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('trial-question-banks.index', ['pack_grade_level' => 10, 'pack_search' => 'Sejarah']))
            ->assertOk()
            ->assertSee('Try Out Sejarah 10 - Campuran Bergambar');
        $this->post(route('trial-question-banks.import-pack'), ['pack_key' => 'sjr-10-campuran'])
            ->assertSessionHasNoErrors();

        $bank = TrialQuestionBank::query()->where('source_pack_key', 'sjr-10-campuran')->sole();
        $questions = $bank->questions()->orderBy('position')->get();
        $images = $questions->whereNotNull('question_image_path')->pluck('question_image_path', 'position');
        $this->assertCount(20, $questions);
        $this->assertSame([4, 9, 13, 17], $images->keys()->all());
        foreach ($images as $path) {
            Storage::disk('public')->assertExists($path);
        }
        $this->assertSame(1, $existingTrial->questionBank->questions()->count());

        $this->post(route('trial-question-banks.import-pack'), ['pack_key' => 'sjr-10-campuran'])
            ->assertRedirect(route('trial-question-banks.questions.index', $bank));
        $this->assertDatabaseCount('trial_questions', 21);
        $this->assertCount(4, Storage::disk('public')->files('trial-questions'));

        $this->post(route('tryouts.store'), [
            'trial_question_bank_id' => $bank->id,
            'title' => 'Simulasi Sejarah Kelas 10 Campuran',
            'access_code' => 'SEJARAH-X-MIX',
            'duration_minutes' => 60,
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $trial = $bank->trialExams()->sole();
        $this->post(route('tryout.start', $trial), [
            'participant_identifier' => '1234567890',
            'access_code' => 'SEJARAH-X-MIX',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $attempt = TrialAttempt::query()->sole();
        $this->get(route('tryout.show', [$trial, $attempt]))
            ->assertOk()
            ->assertSee('Seorang siswa menemukan buku transaksi asli')
            ->assertSee(Storage::disk('public')->url($images->first()))
            ->assertSee('data-exam-image-zoom-in', false)
            ->assertSee('data-exam-image aria-haspopup="dialog"', false);
    }

    public function test_picker_lists_actual_school_subjects_and_filters_by_grade_and_subject(): void
    {
        Subject::create(['code' => 'KOM-BIS', 'name' => 'Komunikasi Bisnis', 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Committee, 'must_change_password' => false, 'is_active' => true]);
        $this->actingAs($admin)->get(route('trial-question-banks.index', ['pack_grade_level' => 11]))
            ->assertOk()->assertSee('Try Out Komunikasi Bisnis 11')->assertSee('Soal belum tersedia')
            ->assertSee('Try Out Bahasa Inggris 11')->assertDontSee('Try Out Bahasa Inggris 12')
            ->assertDontSee('Menampilkan seluruh paket sekaligus');
        $this->get(route('trial-question-banks.index', ['pack_grade_level' => 12, 'pack_search' => 'Matematika']))
            ->assertOk()->assertSee('Try Out Matematika 12')->assertDontSee('Try Out Bahasa Indonesia 12');
        $this->get(route('trial-question-banks.index', ['pack_grade_level' => '', 'pack_search' => 'Bahasa Indonesia']))
            ->assertOk()->assertSee('Try Out Bahasa Indonesia 10')->assertSee('Try Out Bahasa Indonesia 11')->assertSee('Try Out Bahasa Indonesia 12');
        $this->assertDatabaseCount('trial_questions', 0);
    }

    public function test_only_exam_staff_can_print_trial_roster_and_completed_scores(): void
    {
        [$trial, $question] = $this->makeTrial();
        $student = Student::firstOrFail();
        $this->actingAs($student->user)->post(route('tryout.start', $trial), ['access_code' => $trial->access_code])->assertRedirect();
        $attempt = $trial->currentAttempts()->firstOrFail();
        app(TrialExamAttemptService::class)->submit($attempt, [$question->id => 'A']);

        $pendingStudent = Student::create([
            'school_class_id' => $student->school_class_id,
            'full_name' => 'Peserta Belum Mulai',
            'student_number' => 'TEST-BELUM',
            'is_active' => true,
        ]);

        $print = route('tryouts.scores.print', $trial);
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $trial->assessmentSubject->update(['teacher_user_id' => $teacher->id]);
        $this->get($print)->assertForbidden();
        $this->get(route('tryout.result', [$trial, $attempt]))->assertOk()->assertDontSee('2,00');
        $this->actingAs($teacher)->get($print)->assertForbidden();
        $this->get(route('tryouts.participants', $trial))->assertOk()->assertDontSee('Cetak Nilai')->assertDontSee('2,00');
        $this->get(route('operations.index'))->assertOk()->assertDontSee($print);

        foreach ([UserRole::SuperAdmin, UserRole::Committee, UserRole::Principal, UserRole::Proctor] as $role) {
            $response = $this->actingAs(User::factory()->create(['role' => $role]))
                ->get($print)->assertOk()->assertSee('Rekap Nilai Simulasi Ujian')
                ->assertSee('Peserta Uji Coba')->assertSee('2,00')
                ->assertSee('Peserta Belum Mulai')->assertSee('1 belum mengerjakan')
                ->assertDontSee($trial->access_code);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->get(route('tryouts.participants', $trial))->assertOk()->assertSee($print);
            $this->get(route('operations.index'))->assertOk()->assertSee($print);
            $this->get(route('tryouts.progress', $trial))
                ->assertOk()->assertJsonPath('participants.'.$pendingStudent->id.'.status', 'not_started');
            if (in_array($role, [UserRole::SuperAdmin, UserRole::Committee], true)) {
                $this->get(route('tryouts.index'))->assertOk()->assertSee($print);
            }
        }
    }

    public function test_staff_can_print_all_subjects_and_switch_to_another_exam_from_participants(): void
    {
        [$trial, $question] = $this->makeTrial();
        $student = Student::firstOrFail();
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $trial->assessmentSubject->update(['teacher_user_id' => $teacher->id]);
        $this->actingAs($student->user)->post(route('tryout.start', $trial), ['access_code' => $trial->access_code])->assertRedirect();
        app(TrialExamAttemptService::class)->submit($trial->currentAttempts()->sole(), [$question->id => 'A']);

        $otherSubject = Subject::create(['code' => 'MAT', 'name' => 'Matematika', 'is_active' => true]);
        $otherClass = SchoolClass::create([
            'academic_year_id' => $student->schoolClass->academic_year_id,
            'name' => 'XI-1',
            'grade_level' => 11,
        ]);
        Student::create([
            'school_class_id' => $otherClass->id,
            'full_name' => 'Peserta Matematika',
            'student_number' => '221',
            'is_active' => true,
        ]);
        $otherComponent = AssessmentSubject::create([
            'assessment_period_id' => $trial->assessmentSubject->assessment_period_id,
            'subject_id' => $otherSubject->id,
            'school_class_id' => $otherClass->id,
            'teacher_user_id' => User::factory()->create(['role' => UserRole::Teacher])->id,
        ]);
        $otherBank = TrialQuestionBank::create([
            'subject_id' => $otherSubject->id,
            'grade_level' => 11,
            'name' => 'Latihan Matematika',
        ]);
        $otherTrial = TrialExam::create([
            'assessment_subject_id' => $otherComponent->id,
            'trial_question_bank_id' => $otherBank->id,
            'title' => 'Simulasi Matematika',
            'slug' => 'simulasi-matematika',
            'access_code' => 'MAT-PRIVAT',
            'duration_minutes' => 60,
            'is_active' => false,
        ]);
        $print = route('tryouts.scores.all');
        $switch = route('tryouts.participants.switch', ['exam' => $otherTrial->slug]);

        $this->get($print)->assertForbidden();
        $this->get($switch)->assertForbidden();
        $this->actingAs($teacher)->get($print)->assertForbidden();
        $this->get($switch)->assertNotFound();
        $this->get(route('tryouts.participants', $trial))
            ->assertOk()->assertDontSee($otherTrial->slug)->assertDontSee('Cetak Nilai Semua Mapel');

        $this->actingAs(User::factory()->create(['role' => UserRole::Proctor]));
        $this->get(route('operations.index'))->assertOk()->assertSee('Cetak Nilai Semua Mapel')->assertSee($print);
        $this->get(route('tryouts.participants', $trial))
            ->assertOk()->assertSee('Pilih mata pelajaran')->assertSee($otherTrial->slug)->assertSee($print);
        $this->get($switch)->assertRedirect(route('tryouts.participants', $otherTrial));
        $this->get(route('tryouts.participants', $otherTrial))
            ->assertOk()->assertViewHas('participants', fn ($participants) =>
                collect($participants->items())->pluck('name')->all() === ['Peserta Matematika']);

        $response = $this->get($print)->assertOk()
            ->assertSee('Cetak / Simpan PDF')
            ->assertSeeInOrder(['Kelas 10', 'Peserta Uji Coba', '2,00', 'Kelas 11', 'Peserta Matematika', 'Belum mengerjakan'])
            ->assertDontSee($otherTrial->access_code);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $sheets = (new \DOMXPath($dom))->query('//section[@class="score-sheet"]');
        $this->assertCount(2, $sheets);
        $this->assertStringNotContainsString('Peserta Matematika', $sheets->item(0)->textContent);
        $this->assertStringNotContainsString('Peserta Uji Coba', $sheets->item(1)->textContent);
    }

    public function test_score_print_includes_current_and_restarted_violation_history(): void
    {
        [$trial, $question] = $this->makeTrial();
        $student = Student::firstOrFail();
        $reviewer = User::factory()->create(['role' => UserRole::Committee, 'name' => 'Petugas Simulasi']);
        $start = route('tryout.start', $trial);
        $payload = ['access_code' => $trial->access_code];
        $security = app(TrialExamSecurityService::class);
        $attempts = app(TrialExamAttemptService::class);

        $this->actingAs($student->user)->post($start, $payload)->assertRedirect();
        $previous = $trial->currentAttempts()->sole();
        $security->record($previous, 'tab_hidden', (string) Str::uuid());
        $security->record($previous, 'window_blur', (string) Str::uuid());
        $attempts->restart($trial, $previous, $reviewer);

        $this->travel(5)->seconds();
        $this->post($start, $payload)->assertRedirect();
        $current = $trial->currentAttempts()->sole();
        $security->record($current, 'fullscreen_exit', (string) Str::uuid());
        $security->resetViolations($current, $reviewer, 'Perangkat sudah diperiksa');
        $attempts->submit($current, [$question->id => 'A']);

        Student::create([
            'school_class_id' => $student->school_class_id,
            'full_name' => 'Peserta Tanpa Catatan',
            'student_number' => 'NO-INCIDENTS',
            'is_active' => true,
        ]);

        $this->actingAs($reviewer);
        foreach ([route('tryouts.scores.print', $trial), route('tryouts.scores.all')] as $printUrl) {
            $this->get($printUrl)->assertOk()
                ->assertSee('2,00')
                ->assertSee('Peserta Tanpa Catatan')
                ->assertDontSee('Riwayat pelanggaran · Peserta Tanpa Catatan')
                ->assertSeeInOrder([
                    'Riwayat pelanggaran · Peserta Uji Coba',
                    'Percobaan sebelumnya',
                    '05/11/2026 10:00:00 · Berpindah tab/aplikasi',
                    'Sinyal bersamaan, tidak dihitung ulang',
                    'Ujian diulang oleh pengawas',
                    'Hitungan pelanggaran 0/2',
                    'Keluar layar penuh',
                    'Hitungan pelanggaran direset',
                    'Perangkat sudah diperiksa',
                ]);
        }
    }

    public function test_staff_can_edit_trial_settings_with_a_stable_link_and_durations(): void
    {
        [$trial] = $this->makeTrial();
        $firstStudent = Student::firstOrFail();
        $secondUser = User::factory()->create(['role' => UserRole::Student]);
        Student::create([
            'user_id' => $secondUser->id,
            'school_class_id' => $firstStudent->school_class_id,
            'full_name' => 'Peserta Kedua',
            'student_number' => '2222',
            'is_active' => true,
        ]);
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $start = route('tryout.start', $trial);
        $edit = route('tryouts.edit', $trial);
        $update = route('tryouts.update', $trial);
        $oldCode = $trial->access_code;
        $settings = [
            'title' => 'Judul baru simulasi',
            'access_code' => ' kode-baru ',
            'duration_minutes' => 20,
            'starts_at' => '',
            'ends_at' => '2026-11-05T12:00:00',
            'is_active' => '1',
        ];

        $this->actingAs($firstStudent->user)->post($start, ['access_code' => $oldCode])->assertRedirect();
        $first = $trial->currentAttempts()->sole();
        $this->assertSame(60, $first->duration_minutes);
        $this->get($edit)->assertForbidden();
        $this->put($update, $settings)->assertForbidden();

        // Existing attempts also keep the duration recorded during the upgrade.
        $migration = require database_path('migrations/2026_11_19_000100_preserve_trial_attempt_duration.php');
        $migration->down();
        $migration->up();
        $this->assertSame(60, $first->fresh()->duration_minutes);

        $this->actingAs($committee)->get(route('tryouts.index'))->assertOk()->assertSee($edit);
        $this->get($edit)->assertOk()->assertSee($oldCode)->assertSee('Simpan perubahan');
        $this->put($update, [
            ...$settings, 'starts_at' => '2026-11-05T13:00:00',
        ])->assertSessionHasErrors('ends_at');
        $this->put($update, [...$settings, 'access_code' => $oldCode])
            ->assertRedirect($edit)->assertSessionHasNoErrors();
        $this->put($update, $settings)->assertRedirect($edit)->assertSessionHasNoErrors();

        $updated = $trial->fresh();
        $this->assertSame($trial->slug, $updated->slug);
        $this->assertSame($trial->trial_question_bank_id, $updated->trial_question_bank_id);
        $this->assertSame('Judul baru simulasi', $updated->title);
        $this->assertSame('KODE-BARU', $updated->access_code);
        $this->assertNull($updated->starts_at);
        $this->assertSame('2026-11-05 12:00:00', $updated->ends_at->toDateTimeString());

        $this->travel(25)->minutes();
        $this->actingAs($firstStudent->user)->getJson(route('tryout.security', [$trial, $first]))
            ->assertOk()->assertJsonPath('status', 'in_progress');
        $this->assertSame(60, $first->fresh()->duration_minutes);

        $this->actingAs($secondUser)->post($start, ['access_code' => $oldCode])->assertSessionHasErrors('access_code');
        $this->post($start, ['access_code' => 'KODE-BARU'])->assertSessionHasNoErrors();
        $second = $trial->currentAttempts()->where('student_id', $secondUser->student->id)->sole();
        $this->assertSame(20, $second->duration_minutes);
        $this->assertTrue(app(TrialExamAttemptService::class)->deadline($second)->equalTo($second->started_at->copy()->addMinutes(20)));
    }

    public function test_trial_restart_preserves_history_and_allows_fresh_attempt_after_finishing_or_locking(): void
    {
        [$trial, $question] = $this->makeTrial();
        $student = Student::firstOrFail();
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $start = route('tryout.start', $trial);
        $payload = ['access_code' => $trial->access_code];
        $this->actingAs($student->user)->post($start, $payload)->assertRedirect();
        $first = $trial->currentAttempts()->sole();
        app(TrialExamAttemptService::class)->submit($first, [$question->id => 'A']);
        $restart = route('tryouts.attempts.restart', [$trial, $first]);

        $this->post($restart)->assertForbidden();
        $this->actingAs($teacher)->post($restart)->assertForbidden();
        $this->actingAs($committee)->post($restart)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($restart)->assertNotFound();
        $this->assertSame(TrialAttempt::RESET_STATUS, $first->fresh()->status);
        $this->assertSame('2.00', $first->fresh()->score);
        $this->assertSame(1, $first->answers()->count());
        $this->assertSame($committee->id, $first->securityIncidents()->where('category', 'supervisor_restart')->sole()->details['reviewer_id']);
        $this->get(route('tryouts.scores.print', $trial))->assertOk()->assertSee('Belum ada peserta yang menyelesaikan simulasi.');
        $this->get(route('tryouts.progress', ['trialExam' => $trial, 'student_ids' => [$student->id]]))
            ->assertOk()->assertJsonPath('participants.'.$student->id.'.status', 'not_started')
            ->assertJsonPath('participants.'.$student->id.'.restart_url', null);

        $this->actingAs($student->user)->get(route('tryout.result', [$trial, $first]))->assertNotFound();
        $this->get(route('student.exams.index'))->assertOk()->assertViewHas('trialAttempts', fn ($attempts) => $attempts->isEmpty());
        $this->post($start, $payload)->assertRedirect();
        $second = $trial->currentAttempts()->sole();
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(0, $second->answers()->count());
        $second->forceFill(['security_reset_count' => 2, 'violation_count' => 2, 'security_locked_at' => now()])->save();

        $this->actingAs($committee)
            ->post(route('tryouts.attempts.restart', [$trial, $second]))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($student->user)->post($start, $payload)->assertRedirect();
        $third = $trial->currentAttempts()->sole();
        $this->assertSame(0, $third->violation_count);
        $this->assertSame(0, $third->security_reset_count);
        $this->assertNull($third->security_locked_at);
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
            'type' => AssessmentType::ASAS,
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
