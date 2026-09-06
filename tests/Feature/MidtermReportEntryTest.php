<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\ExtracurricularRating;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Extracurricular;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtermReportEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_teacher_sets_objectives_score_and_learning_outcomes(): void
    {
        [$period, $class, $student, $subject] = $this->makeContext();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $subject->update(['teacher_user_id' => $teacher->id]);

        $this->actingAs($teacher)->put(route('reports.midterm.learning-objective.update', $subject), [
            'learning_objective' => "Menganalisis informasi dalam teks laporan.\n\nMenyajikan hasil analisis secara runtut.",
        ])->assertSessionHasNoErrors();

        $this->actingAs($teacher)->put(route('reports.midterm.subject-results.update', $subject), [
            'results' => [
                $student->id => [
                    'score' => 88,
                    'achieved_objectives' => ['Menganalisis informasi dalam teks laporan'],
                    'improvement_objectives' => ['Menyajikan hasil analisis secara runtut'],
                    'description' => 'Deskripsi manual tidak boleh digunakan.',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_subjects', [
            'id' => $subject->id,
            'learning_objective' => "Menganalisis informasi dalam teks laporan.\nMenyajikan hasil analisis secara runtut.",
        ]);
        $this->assertDatabaseHas('midterm_subject_results', [
            'assessment_subject_id' => $subject->id,
            'student_id' => $student->id,
            'score' => 88,
            'recorded_by_user_id' => $teacher->id,
        ]);
        $result = $subject->midtermResults()->firstOrFail();
        $this->assertStringContainsString('sangat baik', $result->description);
        $this->assertStringContainsString('Perlu meningkatkan', $result->description);
        $this->assertStringNotContainsString('Deskripsi manual', $result->description);
        $this->assertSame(['Menganalisis informasi dalam teks laporan'], $result->achieved_objectives);
        $this->assertSame(['Menyajikan hasil analisis secara runtut'], $result->improvement_objectives);

        $this->actingAs($teacher)->put(route('reports.midterm.learning-objective.update', $subject), [
            'learning_objective' => 'Menyusun teks laporan yang logis.',
        ])->assertSessionHasNoErrors();
        $this->assertStringContainsString('menyusun teks laporan', $result->fresh()->description);

        $otherTeacher = User::factory()->create(['role' => UserRole::Teacher]);
        $this->actingAs($otherTeacher)->put(route('reports.midterm.subject-results.update', $subject), [
            'results' => [$student->id => ['score' => 10]],
        ])->assertForbidden();
        $this->actingAs($teacher)->put(route('reports.midterm.learning-objective.update', $subject), [
            'learning_objective' => 'Guru mata pelajaran boleh mengubah TP.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('reports.midterm.learning-objective.update', $subject), [
            'learning_objective' => 'Panitia atau admin tidak mengubah materi TP.',
        ])->assertForbidden();
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $this->actingAs($committee)->put(route('reports.midterm.learning-objective.update', $subject), [
            'learning_objective' => 'Panitia juga tidak mengubah materi TP.',
        ])->assertForbidden();
    }

    public function test_subject_teacher_cannot_print_report_unless_assigned_as_homeroom_teacher(): void
    {
        $this->withoutVite();
        [$period, $class, $student, $subject] = $this->makeContext();
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $subject->update(['teacher_user_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->get(route('reports.midterm.show', [$period, $class]))
            ->assertRedirect(route('reports.midterm.edit', [$period, $class]));
        $this->get(route('reports.midterm.edit', [$period, $class]))
            ->assertOk()
            ->assertSee('Tujuan Pembelajaran (dasar capaian)')
            ->assertDontSee('Pengaturan Cetak');
        $this->get(route('reports.midterm.print', [$period, $class, $student]))->assertForbidden();

        $class->update(['homeroom_teacher_user_id' => $teacher->id]);
        $this->get(route('reports.midterm.print', [$period, $class, $student]))->assertOk();
    }

    public function test_admin_can_set_report_place_and_date(): void
    {
        [$period, $class] = $this->makeContext();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->put(route('reports.midterm.settings.update', [$period, $class]), [
            'report_place' => 'Jakarta',
            'report_date' => '2026-09-18',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_periods', [
            'id' => $period->id,
            'report_place' => 'Jakarta',
        ]);
        $this->assertSame('2026-09-18', $period->fresh()->report_date->format('Y-m-d'));
    }

    public function test_homeroom_teacher_can_record_attendance_but_other_teacher_cannot(): void
    {
        [$period, $class, $student] = $this->makeContext();
        $homeroom = User::factory()->create(['role' => UserRole::Teacher]);
        $class->update(['homeroom_teacher_user_id' => $homeroom->id]);
        $payload = ['attendance' => [
            $student->id => [
                'sick_days' => 2,
                'excused_days' => 1,
                'unexcused_days' => 0,
                'notes' => 'Pertahankan kedisiplinan dan semangat belajar.',
            ],
        ]];

        $this->actingAs($homeroom)
            ->put(route('reports.midterm.attendance.update', [$period, $class]), $payload)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('midterm_attendance_summaries', [
            'assessment_period_id' => $period->id,
            'student_id' => $student->id,
            'sick_days' => 2,
            'excused_days' => 1,
            'unexcused_days' => 0,
            'recorded_by_user_id' => $homeroom->id,
        ]);

        $otherTeacher = User::factory()->create(['role' => UserRole::Teacher]);
        $this->actingAs($otherTeacher)
            ->put(route('reports.midterm.attendance.update', [$period, $class]), $payload)
            ->assertForbidden();
    }

    public function test_admin_assigns_participant_and_coach_records_extracurricular_grade(): void
    {
        [$period, $class, $student] = $this->makeContext();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $coach = User::factory()->create(['role' => UserRole::Teacher]);

        $this->actingAs($admin)->post(route('reports.midterm.extracurriculars.store', [$period, $class]), [
            'name' => 'Badminton',
            'coach_user_id' => $coach->id,
        ])->assertSessionHasNoErrors();
        $activity = Extracurricular::query()->firstOrFail();
        $this->put(route('reports.midterm.extracurriculars.participants', [$period, $class, $activity]), [
            'participant_ids' => [$student->id],
        ])->assertSessionHasNoErrors();

        $this->actingAs($coach)->put(route('reports.midterm.extracurriculars.grades', [$period, $class, $activity]), [
            'ratings' => [$student->id => ExtracurricularRating::VeryGood->value],
            'descriptions' => [$student->id => 'Keterangan manual tidak boleh digunakan.'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('extracurricular_grades', [
            'assessment_period_id' => $period->id,
            'extracurricular_id' => $activity->id,
            'student_id' => $student->id,
            'rating' => ExtracurricularRating::VeryGood->value,
            'graded_by_user_id' => $coach->id,
        ]);
        $description = $activity->grades()->firstOrFail()->description;
        $this->assertStringContainsString('sangat baik', $description);
        $this->assertStringNotContainsString('Keterangan manual', $description);
    }

    public function test_midterm_print_contains_description_extracurricular_and_attendance(): void
    {
        $this->withoutVite();
        [$period, $class, $student, $subject] = $this->makeContext();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $homeroom = User::factory()->create(['role' => UserRole::Teacher, 'name' => 'Wali Kelas Contoh']);
        $class->update(['homeroom_teacher_user_id' => $homeroom->id]);
        $period->update(['report_place' => 'Jakarta Barat', 'report_date' => '2026-09-18']);
        $subject->update(['learning_objective' => 'Memahami teks laporan.']);
        $subject->midtermResults()->create([
            'student_id' => $student->id,
            'score' => 90,
            'description' => 'Menguasai teks laporan dengan sangat baik.',
            'recorded_by_user_id' => $admin->id,
        ]);
        $activity = Extracurricular::create([
            'academic_year_id' => $period->academic_year_id,
            'name' => 'Pramuka',
            'coach_user_id' => $homeroom->id,
        ]);
        $activity->participants()->attach($student);
        $activity->grades()->create([
            'assessment_period_id' => $period->id,
            'student_id' => $student->id,
            'rating' => ExtracurricularRating::Good,
            'description' => 'Aktif dan disiplin mengikuti latihan.',
            'graded_by_user_id' => $homeroom->id,
        ]);
        $period->attendanceSummaries()->create([
            'student_id' => $student->id,
            'sick_days' => 1,
            'excused_days' => 2,
            'unexcused_days' => 0,
            'notes' => 'Pertahankan prestasi belajar.',
            'recorded_by_user_id' => $homeroom->id,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.midterm.print', [$period, $class, $student]))
            ->assertOk()
            ->assertSee('Laporan Hasil Belajar')
            ->assertSee('Capaian Kompetensi')
            ->assertSee('Fase')
            ->assertSee('Menguasai teks laporan dengan sangat baik.')
            ->assertSee('Pramuka')
            ->assertSee('Aktif dan disiplin mengikuti latihan.')
            ->assertSee('Pertahankan prestasi belajar.')
            ->assertSee('Wali Kelas Contoh')
            ->assertSee('Jakarta Barat, 18 September 2026')
            ->assertDontSee('<strong>TP:</strong>', false);
    }

    private function makeContext(): array
    {
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'X-1',
            'grade_level' => 10,
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
        $student = Student::create([
            'school_class_id' => $class->id,
            'student_number' => 'S-001',
            'nisn' => '1234567890',
            'full_name' => 'Peserta Contoh',
            'is_active' => true,
        ]);
        $masterSubject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'is_active' => true]);
        $subject = AssessmentSubject::create([
            'assessment_period_id' => $period->id,
            'subject_id' => $masterSubject->id,
            'school_class_id' => $class->id,
        ]);

        return [$period, $class, $student, $subject];
    }
}
