<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\Campus;
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Subject;
use App\Models\TrialExam;
use App\Models\TrialLocation;
use App\Models\TrialQuestionBank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $period = $this->makeRoomPeriod($year->id, 'AAS');
        SchoolProfile::create(['name' => 'SMP Islam Bahagia']);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->get(route('exam-flyers.core', ['assessment_period_id' => $period->id]))
            ->assertOk()->assertSee('Asesmen Sumatif Akhir Semester Ganjil')->assertSee('TAHUN AJARAN 2025/2026')
            ->assertSee('FLYER ASESMEN')->assertSee('Cek koneksi internet dan sinyal di HP Anda sebelum mulai.')
            ->assertSee('SMK ISLAM BAHAGIA')->assertDontSee('SMP Islam Bahagia')->assertSee('AAS Ganjil')->assertSee('images/logo-ypmi-bahagia.jpeg')
            ->assertSee(route('login'))->assertDontSee('2026/2027')->assertDontSee('Kode uji coba')
            ->assertDontSee('YPMI BAHAGIA')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertFileExists(public_path('images/logo-ypmi-bahagia.jpeg'));
    }

    public function test_trial_flyer_uses_its_linked_year_and_real_access_details(): void
    {
        $year = $this->year('2026/2027', true);
        $period = $this->makeRoomPeriod($year->id, 'AAS');
        $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'X-1', 'grade_level' => 10]);
        $subject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia']);
        $component = AssessmentSubject::create(['assessment_period_id' => $period->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id]);
        $bank = TrialQuestionBank::create(['name' => 'Latihan Bahasa Indonesia', 'grade_level' => 10, 'subject_id' => $subject->id]);
        $trial = TrialExam::create(['assessment_subject_id' => $component->id, 'trial_question_bank_id' => $bank->id, 'title' => 'Uji Coba Bahasa Indonesia', 'slug' => 'latihan-bin', 'access_code' => 'BIN-COBA', 'duration_minutes' => 60, 'is_active' => true]);
        $location = TrialLocation::create(['name' => 'Kampus Simulasi', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meters' => 100, 'max_accuracy_meters' => 50, 'is_active' => true]);
        $trial->update(['starts_at' => $this->wib('2026-09-03 22:00'), 'ends_at' => $this->wib('2026-09-04 01:00'), 'trial_location_id' => $location->id]);
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $this->actingAs($committee)->get(route('exam-flyers.trial', $trial))
            ->assertOk()->assertSee('Simulasi Asesmen Sumatif Akhir Semester Ganjil')->assertSee('TAHUN AJARAN 2026/2027')
            ->assertSee('FLYER SIMULASI UJIAN')->assertSee('Cek koneksi internet dan sinyal di HP Anda sebelum mulai.')
            ->assertSee('Uji Coba Bahasa Indonesia')->assertSee('Bahasa Indonesia · Kelas 10')
            ->assertSee('BIN-COBA')->assertSee('60 menit')->assertSee(route('tryout.entry', $trial))
            ->assertSee('03/09/2026 22:00 – 04/09/2026 01:00 WIB')->assertSee('Kampus Simulasi')->assertSee('lokasi simulasi')
            ->assertSee('Tata tertib peserta')->assertSee('Peringatan pelanggaran')
            ->assertSee('images/logo-ypmi-bahagia.jpeg');
        $trial->update(['starts_at' => null, 'ends_at' => $this->wib('2026-09-04 01:00'), 'trial_location_id' => null]);
        $this->get(route('exam-flyers.trial', $trial))->assertOk()->assertSee('Sampai 04/09/2026 01:00 WIB')->assertDontSee('absensi GPS');
    }

    public function test_group_print_includes_all_simulations_across_grades_and_periods_without_pagination(): void
    {
        $year = $this->year('2026/2027', true);
        $asas = $this->makeRoomPeriod($year->id, 'AAS');
        $ats = $this->makeRoomPeriod($year->id);
        $classX = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'X-1', 'grade_level' => 10]);
        $classXI = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'XI-1', 'grade_level' => 11]);
        $trials = collect(range(1, 13))->map(function (int $number) use ($classX, $classXI, $asas, $ats): TrialExam {
            $class = $number <= 10 ? $classX : $classXI;
            $period = $number <= 10 ? $asas : $ats;
            $subject = Subject::create(['code' => 'MAP-'.$number, 'name' => sprintf('Mapel %02d', $number)]);
            $component = AssessmentSubject::create(['assessment_period_id' => $period->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id]);
            $bank = TrialQuestionBank::create(['name' => $subject->name, 'grade_level' => $class->grade_level, 'subject_id' => $subject->id]);
            if ($number === 13) {
                $bank->forceFill(['archived_at' => now()])->save();
            }

            return TrialExam::create([
                'assessment_subject_id' => $component->id, 'trial_question_bank_id' => $bank->id,
                'title' => 'Simulasi '.$subject->name, 'slug' => 'simulasi-'.$number, 'access_code' => 'COBA-'.$number,
                'duration_minutes' => 60, 'is_active' => $number !== 12,
                'starts_at' => $this->wib(sprintf('2026-09-%02d 08:00', $number)),
            ]);
        });
        $bulk = route('exam-flyers.trials');
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $response = $this->actingAs($committee)->get(route('exam-flyers.trials', ['page' => 2, 'per_page' => 1]))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Flyer Semua Simulasi Ujian · 12 ujian')->assertDontSee('COBA-13');

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $pages = $xpath->query('//article[@class="flyer"]');
        $this->assertCount(12, $pages);
        foreach ($pages as $index => $page) {
            $exam = $trials[$index];
            $this->assertStringContainsString($exam->title, $page->textContent);
            $this->assertStringContainsString($exam->access_code, $page->textContent);
            $this->assertStringContainsString($exam->starts_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB', $page->textContent);
            $this->assertSame(route('tryout.entry', $exam), $xpath->query('.//a[@class="url"]', $page)->item(0)->getAttribute('href'));
            $this->assertSame(1, $xpath->query('//*[@id="rules-title-'.($index + 1).'"]')->length);
        }
        $this->assertStringContainsString('Simulasi nonaktif.', $pages->item(11)->textContent);
        $this->get(route('tryouts.index'))->assertOk()->assertSee($bulk);
        $this->get(route('operations.index'))->assertOk()->assertSee($bulk);
        $this->actingAs(User::factory()->create(['role' => UserRole::Teacher]))->get($bulk)->assertForbidden();
    }

    public function test_group_print_shows_an_empty_state_without_a_print_button(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Principal]))
            ->get(route('exam-flyers.trials'))->assertOk()->assertSee('Belum ada simulasi yang bisa dicetak.')
            ->assertDontSee('Cetak / Simpan PDF');
    }

    public function test_core_flyer_uses_published_schedule_and_each_session_can_be_printed_separately(): void
    {
        $year = $this->year('2026/2027', true);
        $period = $this->makeRoomPeriod($year->id);
        $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'XI-3', 'grade_level' => 11]);
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika']);
        $component = AssessmentSubject::create(['assessment_period_id' => $period->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id]);
        $campus = Campus::create(['name' => 'Gedung Utama', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meters' => 100, 'max_accuracy_meters' => 50, 'is_active' => true]);
        $settings = ['assessment_subject_id' => $component->id, 'campus_id' => $campus->id, 'room_name' => 'Lab 1', 'status' => 'published', 'duration_minutes' => 75];
        $regular = ExamSession::create([...$settings, 'kind' => 'regular', 'starts_at' => $this->wib('2026-09-03 07:30'), 'ends_at' => $this->wib('2026-09-03 08:45')]);
        $makeup = ExamSession::create([...$settings, 'kind' => 'makeup', 'starts_at' => $this->wib('2026-09-05 10:00'), 'ends_at' => $this->wib('2026-09-05 11:15')]);
        $draft = ExamSession::create([...$settings, 'status' => 'draft', 'kind' => 'regular', 'starts_at' => $this->wib('2026-09-08 09:00'), 'ends_at' => $this->wib('2026-09-08 10:15')]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Committee]));

        $this->get(route('exam-flyers.core', ['assessment_period_id' => $period->id]))->assertOk()
            ->assertSee('Jadwal per mapel dan rombel')->assertSee('03/09/2026 07:30 – 08:45 WIB')
            ->assertSee('05/09/2026 10:00 – 11:15 WIB')->assertDontSee('08/09/2026 09:00');
        $this->get(route('operations.index'))->assertOk()->assertSee(route('exam-flyers.core-session', $regular))
            ->assertSee('Cetak Flyer Asesmen')->assertSee('Pilih Flyer Simulasi Ujian')->assertDontSee('Cetak flyer peringatan');
        $this->get(route('exam-flyers.core-session', $makeup))->assertOk()
            ->assertSee('Matematika · XI-3')->assertSee('Susulan')->assertSee('75 menit')->assertSee('Gedung Utama · Lab 1')
            ->assertSee('05/09/2026 10:00 – 11:15 WIB')->assertDontSee('03/09/2026 07:30')
            ->assertSee('Tata tertib peserta')->assertSee('Peringatan pelanggaran');
        $this->get(route('exam-flyers.core-session', $draft))->assertOk()->assertSee('Draf jadwal');
        $this->actingAs(User::factory()->create(['role' => UserRole::Student]))
            ->get(route('exam-flyers.core-session', $makeup))->assertForbidden();
    }

    public function test_only_organizers_can_print_flyers_and_a_valid_period_is_required(): void
    {
        $year = $this->year('2026/2027', true);
        $period = $this->makeRoomPeriod($year->id, 'AAS');
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

    public function test_legacy_warning_link_uses_the_assessment_flyer_for_the_selected_period(): void
    {
        $year = $this->year('2026/2027', true);
        $period = $this->makeRoomPeriod($year->id, 'AAS');
        $principal = User::factory()->create(['role' => UserRole::Principal]);
        $this->actingAs($principal)->get(route('exam-flyers.warnings', ['assessment_period_id' => $period->id]))
            ->assertOk()->assertSee('SMK ISLAM BAHAGIA')->assertSee('FLYER ASESMEN')->assertSee('Peringatan pelanggaran')
            ->assertSee('Asesmen Sumatif Akhir Semester Ganjil')->assertSee('TAHUN AJARAN 2026/2027')
            ->assertSee('Pelanggaran pertama: peringatan. Kedua: ujian terkunci.')
            ->assertSee('Waktu ujian tetap berjalan saat terkunci.')->assertSee('maksimal 2 kali')
            ->assertSee('images/logo-ypmi-bahagia.jpeg')->assertDontSee('Kode uji coba');
        $this->get(route('exam-flyers.warnings'))->assertSessionHasErrors('assessment_period_id');
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $this->actingAs($teacher)->get(route('exam-flyers.warnings', ['assessment_period_id' => $period->id]))->assertForbidden();
        $period->update(['semester' => 'even']);
        $this->actingAs($principal)->get(route('exam-flyers.core', ['assessment_period_id' => $period->id]))
            ->assertOk()->assertSee('Asesmen Sumatif Akhir Semester Genap');
    }

    private function wib(string $value): Carbon
    {
        return Carbon::parse($value, 'Asia/Jakarta')->timezone(config('app.timezone'));
    }

    private function year(string $name, bool $active): AcademicYear
    {
        return AcademicYear::create(['name' => $name, 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => $active]);
    }
}
