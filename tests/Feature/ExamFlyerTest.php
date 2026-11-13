<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Subject;
use App\Models\TrialExam;
use App\Models\TrialQuestionBank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRoomPeriods;
use Tests\TestCase;

class ExamFlyerTest extends TestCase
{
    use CreatesRoomPeriods;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_core_flyer_uses_selected_period_year_school_name_and_supplied_logo(): void
    {
        $this->year('2026/2027', true);
        $year = $this->year('2025/2026', false);
        $period = $this->makeRoomPeriod($year->id);
        SchoolProfile::create(['name' => 'SMP Islam Bahagia']);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->get(route('exam-flyers.core', ['assessment_period_id' => $period->id]))
            ->assertOk()->assertSee('UJIAN INTI')->assertSee('TAHUN AJARAN 2025/2026')
            ->assertSee('SMP Islam Bahagia')->assertSee('ATS Ganjil')->assertSee('images/logo-ypmi-bahagia.jpeg')
            ->assertSee(route('login'))->assertDontSee('2026/2027')->assertDontSee('Kode uji coba')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertFileExists(public_path('images/logo-ypmi-bahagia.jpeg'));
    }

    public function test_trial_flyer_uses_its_linked_year_and_real_access_details(): void
    {
        $year = $this->year('2026/2027', true);
        $period = $this->makeRoomPeriod($year->id);
        $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'X-1', 'grade_level' => 10]);
        $subject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia']);
        $component = AssessmentSubject::create(['assessment_period_id' => $period->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id]);
        $bank = TrialQuestionBank::create(['name' => 'Latihan Bahasa Indonesia', 'grade_level' => 10, 'subject_id' => $subject->id]);
        $trial = TrialExam::create(['assessment_subject_id' => $component->id, 'trial_question_bank_id' => $bank->id, 'title' => 'Uji Coba Bahasa Indonesia', 'slug' => 'latihan-bin', 'access_code' => 'BIN-COBA', 'duration_minutes' => 60, 'is_active' => true]);
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $this->actingAs($committee)->get(route('exam-flyers.trial', $trial))
            ->assertOk()->assertSee('UJIAN UJI COBA')->assertSee('TAHUN AJARAN 2026/2027')
            ->assertSee('Uji Coba Bahasa Indonesia')->assertSee('Bahasa Indonesia · Kelas 10')
            ->assertSee('BIN-COBA')->assertSee('60 menit')->assertSee(route('tryout.entry', $trial))
            ->assertSee('images/logo-ypmi-bahagia.jpeg');
    }

    public function test_only_organizers_can_print_flyers_and_a_valid_period_is_required(): void
    {
        $year = $this->year('2026/2027', true);
        $period = $this->makeRoomPeriod($year->id);
        foreach ([UserRole::Student, UserRole::Teacher, UserRole::Proctor] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('exam-flyers.core', ['assessment_period_id' => $period->id]))->assertForbidden();
        }
        foreach ([UserRole::SuperAdmin, UserRole::Committee, UserRole::Principal] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('exam-flyers.core', ['assessment_period_id' => $period->id]))->assertOk();
        }
        $this->get(route('exam-flyers.core'))->assertSessionHasErrors('assessment_period_id');
        $this->get(route('exam-flyers.core', ['assessment_period_id' => 999999]))->assertSessionHasErrors('assessment_period_id');
    }

    private function year(string $name, bool $active): AcademicYear
    {
        return AcademicYear::create(['name' => $name, 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => $active]);
    }
}
