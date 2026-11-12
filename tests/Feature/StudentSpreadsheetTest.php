<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Students\StudentSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
            '2026002,9876543210,Siswa Dua,,,aktif',
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
        $this->assertDatabaseHas('users', [
            'username' => '9876543210',
            'must_change_password' => false,
            'role' => UserRole::Student->value,
        ]);

        $secondStudent = Student::query()->where('student_number', '2026002')->firstOrFail();
        $this->assertTrue(Hash::check('2026002', $secondStudent->user->password));
        $this->assertSame('2026002', $secondStudent->printablePassword());
        $this->assertSame('Siswa1234', Student::query()->where('student_number', '2026001')->firstOrFail()->printablePassword());
    }

    public function test_reimport_with_blank_password_preserves_existing_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $class = $this->makeClass();
        $firstImport = implode("\n", [
            'NIS,NISN,Nama Lengkap,Email,Password,Status',
            '2026001,0123456789,Siswa Satu,,Siswa1234,aktif',
        ]);
        $secondImport = implode("\n", [
            'NIS,NISN,Nama Lengkap,Email,Password,Status',
            '2026001,0123456789,Siswa Satu Diperbarui,,,aktif',
        ]);

        $this->actingAs($admin)->post(route('academic.students.import'), [
            'school_class_id' => $class->id,
            'spreadsheet' => UploadedFile::fake()->createWithContent('awal.csv', $firstImport),
        ]);
        $this->actingAs($admin)->post(route('academic.students.import'), [
            'school_class_id' => $class->id,
            'spreadsheet' => UploadedFile::fake()->createWithContent('ulang.csv', $secondImport),
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('username', '0123456789')->firstOrFail();
        $this->assertTrue(Hash::check('Siswa1234', $user->password));
        $this->assertSame('Siswa1234', $user->student_card_credentials['password']);
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseHas('students', ['full_name' => 'Siswa Satu Diperbarui']);
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

    public function test_imported_rooms_can_be_updated_cleared_and_preserved_with_an_old_template(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $class = $this->makeClass();
        $this->actingAs($admin);
        foreach ([
            ['NIS,Nama Lengkap,Ruangan', '2026001,Siswa Satu,Ruang 01', 'Ruang 01'],
            ['NIS,Nama Lengkap', '2026001,Siswa Satu', 'Ruang 01'],
            ['NIS,Nama Lengkap,Ruangan', '2026001,Siswa Satu,Lab Bahasa', 'Lab Bahasa'],
            ['NIS,Nama Lengkap,Ruangan', '2026001,Siswa Satu,', null],
        ] as [$header, $row, $expectedRoom]) {
            $this->post(route('academic.students.import'), [
                'school_class_id' => $class->id,
                'spreadsheet' => UploadedFile::fake()->createWithContent('ruangan.csv', $header."\n".$row),
            ])->assertRedirect()->assertSessionHasNoErrors();
            $student = Student::query()->where('student_number', '2026001')->firstOrFail();
            $this->assertSame($expectedRoom, $student->room_name);
            $this->assertTrue(Hash::check('2026001', $student->user->password));
        }
        $this->assertDatabaseCount('students', 1);
    }

    public function test_invalid_room_in_import_rejects_the_entire_file(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $class = $this->makeClass();
        $csv = "NIS,Nama Lengkap,Ruangan\n001,Siswa Satu,Ruang 01\n002,Siswa Dua,".str_repeat('R', 81);
        $this->actingAs($admin)->post(route('academic.students.import'), [
            'school_class_id' => $class->id,
            'spreadsheet' => UploadedFile::fake()->createWithContent('ruangan.csv', $csv),
        ])->assertSessionHasErrors('spreadsheet');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_excel_template_and_export_include_rooms_and_export_can_be_reimported(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $class = $this->makeClass();
        $this->actingAs($admin)->post(route('academic.students.import'), [
            'school_class_id' => $class->id,
            'spreadsheet' => UploadedFile::fake()->createWithContent('ruangan.csv', "NIS,Nama Lengkap,Ruangan\n001,Siswa Satu,Lab Bahasa"),
        ])->assertSessionHasNoErrors();
        $service = app(StudentSpreadsheetService::class);
        $template = $service->createTemplate();
        try {
            $rows = $service->read(new UploadedFile($template, 'template.xlsx', null, null, true));
            $this->assertSame('Ruang 01', $rows[0]['ruangan']);
        } finally {
            unlink($template);
        }
        $response = $this->get(route('academic.students.export'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $rows = $service->read(new UploadedFile($path, 'export.xlsx', null, null, true));
            $this->assertSame('Lab Bahasa', $rows[0]['ruangan']);
            $this->assertArrayNotHasKey('password', $rows[0]);
            Student::query()->update(['room_name' => 'Ruangan Sementara']);
            $this->post(route('academic.students.import'), [
                'spreadsheet' => new UploadedFile($path, 'export.xlsx', null, null, true),
            ])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('students', ['student_number' => '001', 'room_name' => 'Lab Bahasa', 'school_class_id' => $class->id]);
            $this->assertDatabaseCount('students', 1);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
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
