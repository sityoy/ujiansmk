<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class StudentSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_import_students_from_csv_with_optional_email(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $class = $this->makeClass();
        $csv = implode("\n", [
            'NIS,NISN,Nama Lengkap,Email,Password,Status',
            '2026001,0123456789,Siswa Satu,,Siswa1234,aktif',
            '2026002,,Siswa Dua,,,aktif',
        ]);

        $this->actingAs($admin)
            ->post(route('academic.students.import'), [
                'school_class_id' => $class->id,
                'spreadsheet' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('students', [
            'student_number' => '2026001',
            'nisn' => '0123456789',
            'full_name' => 'Siswa Satu',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => '0123456789',
            'email' => null,
            'role' => UserRole::Student->value,
        ]);
        $this->assertDatabaseHas('students', ['student_number' => '2026002', 'user_id' => null]);
    }

    public function test_template_is_a_valid_xlsx_download(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP ZIP is not available.');
        }

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $response = $this->actingAs($admin)->get(route('academic.students.template'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('template-import-siswa.xlsx', (string) $response->headers->get('content-disposition'));
    }

    private function makeClass(): SchoolClass
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);

        return SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'IX-2',
            'grade_level' => 9,
        ]);
    }
}
