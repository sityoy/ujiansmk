<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class SubjectSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_import_and_update_subjects_from_csv(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        Subject::create(['code' => 'MTK', 'name' => 'Matematika Lama', 'is_active' => true]);
        $csv = implode("\n", [
            'Kode Mapel,Nama Mapel,Status',
            'mtk,Matematika,aktif',
            'INF,Informatika,nonaktif',
        ]);

        $this->actingAs($admin)
            ->post(route('academic.subjects.import'), [
                'subject_spreadsheet' => UploadedFile::fake()->createWithContent('mapel.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('subjects', 2);
        $this->assertDatabaseHas('subjects', ['code' => 'MTK', 'name' => 'Matematika', 'is_active' => true]);
        $this->assertDatabaseHas('subjects', ['code' => 'INF', 'name' => 'Informatika', 'is_active' => false]);
    }

    public function test_subject_template_is_a_valid_xlsx_download(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP ZIP is not available.');
        }

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $response = $this->actingAs($admin)->get(route('academic.subjects.template'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('template-import-mapel.xlsx', (string) $response->headers->get('content-disposition'));
    }
}
