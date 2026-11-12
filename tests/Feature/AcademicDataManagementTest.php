<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicDataManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_super_admin_can_create_core_academic_data_and_student_login(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->post(route('academic.years.store'), [
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ])->assertRedirect();

        $year = AcademicYear::firstOrFail();

        $this->actingAs($admin)->post(route('academic.subjects.store'), [
            'code' => 'INF',
            'name' => 'Informatika',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('academic.classes.store'), [
            'academic_year_id' => $year->id,
            'name' => 'IX-2',
            'grade_level' => 9,
        ])->assertRedirect();

        $class = SchoolClass::firstOrFail();

        $this->actingAs($admin)->post(route('academic.students.store'), [
            'school_class_id' => $class->id,
            'student_number' => 'S-001',
            'nisn' => '0123456789',
            'full_name' => 'Peserta Uji',
            'room_name' => 'Ruang 02',
            'email' => 'siswa@example.test',
            'password' => 'Student123',
            'password_confirmation' => 'Student123',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_years', ['name' => '2026/2027', 'is_active' => true]);
        $this->assertDatabaseHas('subjects', ['code' => 'INF']);
        $this->assertDatabaseHas('school_classes', ['name' => 'IX-2']);
        $this->assertDatabaseHas('students', ['student_number' => 'S-001', 'nisn' => '0123456789', 'room_name' => 'Ruang 02']);
        $this->assertDatabaseHas('users', ['email' => 'siswa@example.test', 'username' => '0123456789', 'role' => UserRole::Student->value]);
    }

    public function test_only_one_academic_year_can_be_active(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $oldYear = AcademicYear::create([
            'name' => '2025/2026',
            'starts_on' => '2025-07-01',
            'ends_on' => '2026-06-30',
            'is_active' => true,
        ]);
        $newYear = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('academic.years.activate', $newYear))
            ->assertRedirect();

        $this->assertFalse($oldYear->refresh()->is_active);
        $this->assertTrue($newYear->refresh()->is_active);
    }

    public function test_manual_student_creation_uses_nis_as_the_initial_password_when_blank(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'X-1',
            'grade_level' => 10,
        ]);

        $this->actingAs($admin)->post(route('academic.students.store'), [
            'school_class_id' => $class->id,
            'student_number' => 'S-002',
            'nisn' => '9876543210',
            'full_name' => 'Siswa Otomatis',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::query()->where('username', '9876543210')->firstOrFail();
        $this->assertTrue(Hash::check('S-002', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertSame('S-002', $user->student_card_credentials['password']);
        $this->assertDatabaseHas('students', ['user_id' => $user->id, 'student_number' => 'S-002']);
    }

    public function test_class_list_can_be_filtered_by_year_and_shows_five_rows_by_default(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);

        foreach (range(1, 6) as $number) {
            SchoolClass::create([
                'academic_year_id' => $year->id,
                'name' => 'IX-'.$number,
                'grade_level' => 9,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('academic.index', ['class_year' => $year->id]))
            ->assertOk()
            ->assertViewHas('classes', fn ($classes) => $classes->count() === 5 && $classes->total() === 6)
            ->assertViewHas('classYearId', $year->id);

        $this->actingAs($admin)
            ->get(route('academic.index', ['class_year' => $year->id, 'class_per_page' => 'all']))
            ->assertOk()
            ->assertViewHas('classes', fn ($classes) => $classes->count() === 6);
    }

    public function test_student_list_supports_search_class_filter_page_size_and_anchor(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'must_change_password' => false,
        ]);
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        $classA = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'X-1',
            'grade_level' => 10,
        ]);
        $classB = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'XI-1',
            'grade_level' => 11,
        ]);

        foreach ([
            [$classA, '1001', '0000000001', 'Alpha Siswa'],
            [$classA, '1002', '0000000002', 'Beta Siswa'],
            [$classB, '2001', '0000000003', 'Gamma Siswa'],
        ] as [$class, $nis, $nisn, $name]) {
            $user = User::factory()->create([
                'role' => UserRole::Student,
                'name' => $name,
                'username' => $nisn,
                'must_change_password' => false,
            ]);
            Student::create([
                'user_id' => $user->id,
                'school_class_id' => $class->id,
                'student_number' => $nis,
                'nisn' => $nisn,
                'full_name' => $name,
                'is_active' => true,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('academic.index', [
                'student_search' => 'Alpha',
                'student_class' => $classA->id,
                'student_per_page' => '50',
            ]))
            ->assertOk()
            ->assertSee('data-sidebar-toggle', false)
            ->assertSee('Master Data')
            ->assertViewHas('students', fn ($students) => $students->count() === 1
                && $students->first()->full_name === 'Alpha Siswa'
                && $students->perPage() === 50
                && $students->fragment() === 'student-list')
            ->assertViewHas('studentSearch', 'Alpha')
            ->assertViewHas('studentClassId', $classA->id)
            ->assertViewHas('studentPerPage', '50');

        $this->actingAs($admin)
            ->get(route('academic.index', [
                'student_class' => $classA->id,
                'student_per_page' => 'all',
            ]))
            ->assertOk()
            ->assertViewHas('students', fn ($students) => $students->count() === 2);
    }

    public function test_teacher_does_not_see_or_access_trial_exam_management_menu(): void
    {
        $teacher = User::factory()->create([
            'role' => UserRole::Teacher,
            'must_change_password' => false,
        ]);

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Ujian uji coba<', false)
            ->assertDontSee('Bank soal uji coba')
            ->assertSee('Bank soal inti');

        $this->actingAs($teacher)
            ->get(route('tryouts.index'))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->get(route('trial-question-banks.index'))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_manage_academic_data(): void
    {
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);

        $this->actingAs($teacher)
            ->post(route('academic.subjects.store'), ['code' => 'MTK', 'name' => 'Matematika'])
            ->assertForbidden();

        $this->assertDatabaseCount('subjects', 0);
    }
}
