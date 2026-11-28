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
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SchedulingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_assignment_is_idempotent_and_moves_to_makeup_without_duplication(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        [$component, $regular, $makeup] = $this->makeSchedule();

        Student::create([
            'school_class_id' => $component->school_class_id,
            'student_number' => 'S-001',
            'full_name' => 'Siswa Satu',
            'is_active' => true,
        ]);
        Student::create([
            'school_class_id' => $component->school_class_id,
            'student_number' => 'S-002',
            'full_name' => 'Siswa Dua',
            'is_active' => true,
        ]);

        $route = route('scheduling.assign-class', [$component, $regular]);

        $this->actingAs($admin)->post($route)->assertRedirect();
        $this->actingAs($admin)->post($route)->assertRedirect();

        $this->assertDatabaseCount('exam_assignments', 2);

        $assignment = ExamAssignment::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('scheduling.makeup.move'), [
                'assignment_id' => $assignment->id,
                'makeup_session_id' => $makeup->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('exam_assignments', 2);
        $this->assertSame($makeup->id, $assignment->refresh()->exam_session_id);
    }

    public function test_committee_can_open_scheduling_but_teacher_cannot(): void
    {
        $this->withoutVite();

        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);

        $this->actingAs($committee)->get(route('scheduling.index'))->assertOk()->assertSee('Rekap Jadwal Ujian');
        $this->get(route('scheduling.recap'))->assertOk()->assertSee('Belum ada sesi ujian sesuai filter.');
        $this->actingAs($teacher)->get(route('scheduling.index'))->assertForbidden();
        $this->get(route('scheduling.recap'))->assertForbidden();
    }

    public function test_schedule_recap_filters_and_exports_the_same_session_details_without_student_names(): void
    {
        $this->withoutVite();
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        [$component, $regular, $makeup] = $this->makeSchedule();
        $regular->update(['room_name' => 'Lab 1', 'status' => SessionStatus::Draft]);
        $makeup->update([
            'room_name' => 'Lab 2',
            'starts_at' => '2026-09-12 23:30:00',
            'ends_at' => '2026-09-13 01:00:00',
        ]);
        $otherClass = SchoolClass::create([
            'academic_year_id' => $component->schoolClass->academic_year_id,
            'name' => 'IX-3',
            'grade_level' => 9,
        ]);
        $otherComponent = $component->replicate()->fill(['school_class_id' => $otherClass->id]);
        $otherComponent->save();
        $otherSession = $regular->replicate()->fill([
            'assessment_subject_id' => $otherComponent->id,
            'room_name' => 'Lab Rombel Lain',
            'status' => SessionStatus::Active,
        ]);
        $otherSession->save();
        foreach ([AssignmentStatus::Scheduled, AssignmentStatus::Cancelled] as $status) {
            $student = Student::create([
                'school_class_id' => $component->school_class_id,
                'student_number' => 'S-'.$status->value,
                'full_name' => 'Nama Peserta Privat',
                'is_active' => true,
            ]);
            ExamAssignment::create([
                'assessment_subject_id' => $component->id,
                'student_id' => $student->id,
                'exam_session_id' => $makeup->id,
                'status' => $status,
                'assigned_at' => now(),
            ]);
        }

        $this->actingAs($committee)->get(route('scheduling.recap'))->assertOk()
            ->assertSeeInOrder(['Lab 1', 'Lab Rombel Lain', 'Lab 2'])
            ->assertSee('Rekap memuat jadwal draf.');

        $this->get(route('scheduling.recap', [
            'session_period_id' => $component->assessment_period_id,
            'session_class_id' => $component->school_class_id,
            'session_search' => 'INF',
            'session_status' => 'ready',
            'session_kind' => SessionKind::Makeup->value,
            'session_page' => 20,
        ]))->assertOk()
            ->assertViewHas('groups', fn ($groups) => $groups->first()['rows']->sole()['participants'] === 1)
            ->assertViewHas('shareText', fn (string $text) => str_contains($text, '23:30 – 13/09/2026 01:00 WIB'))
            ->assertSee('Sabtu, 12 September 2026')
            ->assertSee('Kampus Utama')
            ->assertSee('Lab 2')
            ->assertSee('Unduh gambar PNG')
            ->assertDontSee('Rekap memuat jadwal draf.')
            ->assertDontSee('Lab 1')
            ->assertDontSee('Lab Rombel Lain')
            ->assertDontSee('Nama Peserta Privat');

        $this->get(route('scheduling.recap', ['session_search' => 'Matematika']))
            ->assertOk()->assertSee('Belum ada sesi ujian sesuai filter.');
    }

    public function test_schedule_recap_filters_the_table_and_share_text_by_start_day_in_wib(): void
    {
        $this->withoutVite();
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        [, $regular, $makeup] = $this->makeSchedule();
        $regular->update(['room_name' => 'Lab Selasa']);
        $makeup->update([
            'room_name' => 'Lab Senin Satu',
            'starts_at' => Carbon::parse('2026-09-06 17:30:00', 'UTC')->timezone(config('app.timezone')),
            'ends_at' => Carbon::parse('2026-09-06 19:00:00', 'UTC')->timezone(config('app.timezone')),
        ]);
        $makeup->replicate()->fill([
            'room_name' => 'Lab Senin Dua',
            'starts_at' => '2026-09-14 08:00:00',
            'ends_at' => '2026-09-14 10:00:00',
        ])->save();

        $this->actingAs($committee)->get(route('scheduling.recap', ['session_weekday' => 1]))
            ->assertOk()
            ->assertViewHas('sessionCount', 2)
            ->assertViewHas('filename', 'rekap-jadwal-ujian-senin')
            ->assertViewHas('shareText', fn (string $text) => str_contains($text, '00:30 – 02:00 WIB')
                && str_contains($text, 'Lab Senin Dua') && ! str_contains($text, 'Lab Selasa'))
            ->assertSee('Hari Senin')
            ->assertSeeInOrder(['Senin, 7 September 2026', 'Lab Senin Satu', 'Senin, 14 September 2026', 'Lab Senin Dua'])
            ->assertDontSee('Lab Selasa');

        $this->get(route('scheduling.recap', ['session_weekday' => 3]))
            ->assertOk()
            ->assertSee('Belum ada sesi ujian sesuai filter.');
    }

    public function test_principal_can_manage_locations_without_full_scheduling_access(): void
    {
        $this->withoutVite();
        $principal = User::factory()->create(['role' => UserRole::Principal]);
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $campus = Campus::create([
            'name' => 'Gedung Lama',
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'radius_meters' => 100,
            'max_accuracy_meters' => 50,
            'is_active' => true,
        ]);

        $this->actingAs($principal)
            ->get(route('scheduling.campuses.index'))
            ->assertOk()
            ->assertSee('Lokasi dan Radius Ujian')
            ->assertSee('Gedung Lama');
        $this->get(route('scheduling.index'))->assertForbidden();

        $this->put(route('scheduling.campuses.update', $campus), [
            'name' => 'Gedung Utama',
            'latitude' => -6.2010000,
            'longitude' => 106.8170000,
            'radius_meters' => 175,
            'max_accuracy_meters' => 35,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $campus->refresh();
        $this->assertSame('Gedung Utama', $campus->name);
        $this->assertSame(175, $campus->radius_meters);
        $this->assertSame(35, $campus->max_accuracy_meters);

        $this->actingAs($teacher)->get(route('scheduling.campuses.index'))->assertForbidden();
    }

    public function test_unused_location_can_be_deleted_but_used_location_is_preserved(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        [, , , $usedCampus] = $this->makeSchedule();
        $unusedCampus = Campus::create([
            'name' => 'Lokasi Sementara',
            'latitude' => -6.2100000,
            'longitude' => 106.8200000,
            'radius_meters' => 100,
            'max_accuracy_meters' => 50,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('scheduling.campuses.destroy', $unusedCampus))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('campuses', ['id' => $unusedCampus->id]);

        $this->delete(route('scheduling.campuses.destroy', $usedCampus))
            ->assertRedirect()
            ->assertSessionHasErrors('campus');
        $this->assertDatabaseHas('campuses', ['id' => $usedCampus->id]);
    }

    public function test_session_end_is_calculated_and_class_time_conflicts_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        [$component, $regular, , $campus] = $this->makeSchedule();
        $otherSubject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'is_active' => true]);
        $otherComponent = AssessmentSubject::create([
            'assessment_period_id' => $component->assessment_period_id,
            'subject_id' => $otherSubject->id,
            'school_class_id' => $component->school_class_id,
        ]);

        $this->actingAs($admin)
            ->post(route('scheduling.sessions.store'), [
                'assessment_subject_id' => $otherComponent->id,
                'campus_id' => $campus->id,
                'kind' => SessionKind::Regular->value,
                'status' => SessionStatus::Published->value,
                'starts_at' => '2026-09-01 09:00:00',
                'duration_minutes' => 60,
                'room_name' => 'Lab 2',
            ])
            ->assertSessionHasErrors('starts_at');

        $this->actingAs($admin)
            ->post(route('scheduling.sessions.store'), [
                'assessment_subject_id' => $otherComponent->id,
                'campus_id' => $campus->id,
                'kind' => SessionKind::Regular->value,
                'status' => SessionStatus::Published->value,
                'starts_at' => '2026-09-01 11:00:00',
                'duration_minutes' => 45,
                'room_name' => 'Lab 2',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $created = ExamSession::query()->where('assessment_subject_id', $otherComponent->id)->firstOrFail();
        $this->assertSame('2026-09-01 11:45:00', $created->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('Lab 2', $created->room_name);
        $this->assertSame($regular->id, ExamSession::query()->where('assessment_subject_id', $component->id)->value('id'));
    }

    public function test_existing_session_can_be_edited_and_end_time_is_recalculated(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        [, $regular, , $campus] = $this->makeSchedule();

        $update = route('scheduling.sessions.update', $regular);
        $data = [
            'campus_id' => $campus->id,
            'kind' => SessionKind::Regular->value,
            'status' => SessionStatus::Active->value,
            'starts_at' => '2026-09-02T09:00',
            'room_name' => 'Ruang 9A',
        ];
        $this->actingAs($admin)->patch($update, $data)->assertSessionHasErrors('ends_at');
        foreach (['2026-09-02T08:59', '2026-09-02T09:05', '2026-09-02T19:01', '2026-09-03T09:00', '2026-09-02T10:00:30'] as $invalidEnd) {
            $this->patch($update, $data + ['ends_at' => $invalidEnd])->assertSessionHasErrors('ends_at');
        }
        $this->assertSame('2026-09-01 10:00:00', $regular->fresh()->ends_at->toDateTimeString());
        $this->patch($update, $data + ['ends_at' => '2026-09-02T10:00'])->assertSessionHasNoErrors();

        $regular->refresh();
        $this->assertSame('2026-09-02 10:00:00', $regular->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame(60, (int) $regular->duration_minutes);
        $this->assertSame('Ruang 9A', $regular->room_name);
        $this->assertSame(SessionStatus::Active, $regular->status);
    }

    public function test_unused_component_can_be_edited_but_used_component_is_locked(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        [$usedComponent] = $this->makeSchedule();
        $temporarySubject = Subject::create(['code' => 'TMP', 'name' => 'Sementara', 'is_active' => true]);
        $replacementSubject = Subject::create(['code' => 'IPA', 'name' => 'Ilmu Pengetahuan Alam', 'is_active' => true]);
        $unusedComponent = AssessmentSubject::create([
            'assessment_period_id' => $usedComponent->assessment_period_id,
            'subject_id' => $temporarySubject->id,
            'school_class_id' => $usedComponent->school_class_id,
        ]);

        $this->actingAs($admin)
            ->patch(route('scheduling.components.update', $unusedComponent), [
                'assessment_period_id' => $unusedComponent->assessment_period_id,
                'subject_id' => $replacementSubject->id,
                'school_class_id' => $unusedComponent->school_class_id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($replacementSubject->id, $unusedComponent->refresh()->subject_id);

        $this->actingAs($admin)
            ->patch(route('scheduling.components.update', $usedComponent), [
                'assessment_period_id' => $usedComponent->assessment_period_id,
                'subject_id' => $temporarySubject->id,
                'school_class_id' => $usedComponent->school_class_id,
            ])
            ->assertSessionHasErrors('component');
    }

    public function test_admin_can_assign_a_grading_teacher_and_session_groups_are_collapsed(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $teacher = User::factory()->create(['role' => UserRole::Teacher, 'is_active' => true]);
        [$component] = $this->makeSchedule();

        $this->actingAs($admin)->patch(route('scheduling.components.teacher', $component), [
            'teacher_user_id' => $teacher->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($teacher->id, $component->fresh()->teacher_user_id);
        $this->get(route('scheduling.index'))->assertOk()
            ->assertViewHas('components', fn ($components): bool => $components->perPage() === 10)
            ->assertSee('Buka pengaturan')
            ->assertSee('jumlah kelompok per halaman dapat dipilih')
            ->assertSee('Guru pengoreksi');
    }

    private function makeSchedule(): array
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        $campus = Campus::create([
            'name' => 'Kampus Utama',
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'radius_meters' => 100,
            'max_accuracy_meters' => 50,
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'code' => 'INF',
            'name' => 'Informatika',
            'is_active' => true,
        ]);
        $schoolClass = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'IX-2',
            'grade_level' => 9,
        ]);
        $period = AssessmentPeriod::create([
            'academic_year_id' => $year->id,
            'code' => 'ATS-GANJIL',
            'name' => 'Asesmen Tengah Semester Ganjil',
            'type' => AssessmentType::ATS,
            'semester' => Semester::Odd,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-10',
            'status' => PeriodStatus::Published,
        ]);
        $component = AssessmentSubject::create([
            'assessment_period_id' => $period->id,
            'subject_id' => $subject->id,
            'school_class_id' => $schoolClass->id,
        ]);
        $regular = ExamSession::create([
            'assessment_subject_id' => $component->id,
            'campus_id' => $campus->id,
            'kind' => SessionKind::Regular,
            'status' => SessionStatus::Published,
            'starts_at' => '2026-09-01 08:00:00',
            'ends_at' => '2026-09-01 10:00:00',
            'duration_minutes' => 90,
        ]);
        $makeup = ExamSession::create([
            'assessment_subject_id' => $component->id,
            'campus_id' => $campus->id,
            'source_session_id' => $regular->id,
            'kind' => SessionKind::Makeup,
            'status' => SessionStatus::Published,
            'starts_at' => '2026-09-12 08:00:00',
            'ends_at' => '2026-09-12 10:00:00',
            'duration_minutes' => 90,
        ]);

        return [$component, $regular, $makeup, $campus];
    }
}
