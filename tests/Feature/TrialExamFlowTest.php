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
use App\Models\Subject;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use App\Models\TrialQuestion;
use App\Models\TrialQuestionBank;
use App\Models\User;
use App\Services\Exams\ExamSecurityService;
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
            ->assertOk()->assertSee('Bobot maksimal PG adalah 70 poin');

        $this->post(route('tryout.start', $trial), [
            'participant_name' => 'Peserta Uji Coba',
            'participant_identifier' => '1234567890',
            'access_code' => 'SJR-SMKBHG',
        ])->assertRedirect();

        $attempt = TrialAttempt::query()->firstOrFail();
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
        $this->assertSame('70.00', $attempt->fresh()->score);
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

    public function test_trial_and_core_exam_keep_the_same_strict_security_policy(): void
    {
        $this->assertSame(2, ExamSecurityService::LIMIT);
        $this->assertSame(ExamSecurityService::LIMIT, TrialExamSecurityService::LIMIT);
        $this->assertSame(ExamSecurityService::CLIENT_CATEGORIES, TrialExamSecurityService::CLIENT_CATEGORIES);
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

