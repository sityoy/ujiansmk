<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicDataManagementTest extends TestCase
{
    use RefreshDatabase;

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
            'email' => 'siswa@example.test',
            'password' => 'Student123',
            'password_confirmation' => 'Student123',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_years', ['name' => '2026/2027', 'is_active' => true]);
        $this->assertDatabaseHas('subjects', ['code' => 'INF']);
        $this->assertDatabaseHas('school_classes', ['name' => 'IX-2']);
        $this->assertDatabaseHas('students', ['student_number' => 'S-001', 'nisn' => '0123456789']);
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

    public function test_non_admin_cannot_manage_academic_data(): void
    {
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);

        $this->actingAs($teacher)
            ->post(route('academic.subjects.store'), ['code' => 'MTK', 'name' => 'Matematika'])
            ->assertForbidden();

        $this->assertDatabaseCount('subjects', 0);
    }
}
