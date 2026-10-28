<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use ZipArchive;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_committee_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Panitia Ujian',
                'email' => 'panitia@example.test',
                'role' => UserRole::Committee->value,
                'password' => 'PanitiaPass123',
                'password_confirmation' => 'PanitiaPass123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'panitia@example.test',
            'role' => UserRole::Committee->value,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_cannot_disable_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->patch(route('users.toggle', $admin))
            ->assertRedirect()
            ->assertSessionHasErrors('user');

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_account_list_uses_ten_rows_and_short_pagination_labels(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'name' => 'Admin Utama']);
        User::factory()->count(10)->create(['role' => UserRole::Teacher]);

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk()
            ->assertViewHas('users', fn ($users): bool => $users->count() === 10 && $users->perPage() === 10)
            ->assertSee('Next.')
            ->assertDontSee('pagination.next');
    }

    public function test_only_one_principal_and_four_super_admins_can_exist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        User::factory()->create(['role' => UserRole::Principal]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Kepala Sekolah Kedua',
            'email' => 'kepala2@example.test',
            'role' => UserRole::Principal->value,
            'password' => 'KepalaBaru123',
            'password_confirmation' => 'KepalaBaru123',
        ])->assertSessionHasErrors('role');

        User::factory()->count(3)->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Admin Kelima',
            'email' => 'admin5@example.test',
            'role' => UserRole::SuperAdmin->value,
            'password' => 'AdminKelima123',
            'password_confirmation' => 'AdminKelima123',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseCount('users', 5);
    }

    public function test_super_admin_can_edit_account_and_reset_its_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $account = User::factory()->create([
            'role' => UserRole::Committee,
            'email' => 'lama@example.test',
        ]);

        $this->actingAs($admin)->put(route('users.update', $account), [
            'name' => 'Guru Diperbarui',
            'email' => 'guru@example.test',
            'role' => UserRole::Teacher->value,
            'is_active' => '1',
            'password' => 'GuruRahasia123',
            'password_confirmation' => 'GuruRahasia123',
        ])->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame('Guru Diperbarui', $account->name);
        $this->assertSame('guru@example.test', $account->email);
        $this->assertSame(UserRole::Teacher, $account->role);
        $this->assertTrue($account->must_change_password);
        $this->assertTrue(Hash::check('GuruRahasia123', $account->password));
    }

    public function test_account_csv_import_creates_and_updates_accounts_without_exposing_passwords(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $createCsv = implode("\n", [
            'ID,Nama,Email,Akses,Password Baru,Status',
            ',Guru Baru,guru@example.test,Guru,GuruBaru123,aktif',
        ]);

        $this->actingAs($admin)->post(route('users.import'), [
            'account_spreadsheet' => UploadedFile::fake()->createWithContent('akun.csv', $createCsv),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $teacher = User::query()->where('email', 'guru@example.test')->firstOrFail();
        $this->assertSame(UserRole::Teacher, $teacher->role);
        $this->assertTrue(Hash::check('GuruBaru123', $teacher->password));
        $originalPassword = $teacher->password;

        $updateCsv = implode("\n", [
            'ID,Nama,Email,Akses,Password Baru,Status',
            $teacher->id.',Guru Baru Diperbarui,guru@example.test,Panitia,,aktif',
        ]);

        $this->actingAs($admin)->post(route('users.import'), [
            'account_spreadsheet' => UploadedFile::fake()->createWithContent('akun-update.csv', $updateCsv),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $teacher->refresh();
        $this->assertSame('Guru Baru Diperbarui', $teacher->name);
        $this->assertSame(UserRole::Committee, $teacher->role);
        $this->assertSame($originalPassword, $teacher->password);
    }

    public function test_account_import_enforces_principal_limit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $csv = implode("\n", [
            'ID,Nama,Email,Akses,Password Baru,Status',
            ',Kepala Satu,kepala1@example.test,Kepala Sekolah,KepalaSatu123,aktif',
            ',Kepala Dua,kepala2@example.test,Kepala Sekolah,KepalaDua123,aktif',
        ]);

        $this->actingAs($admin)->post(route('users.import'), [
            'account_spreadsheet' => UploadedFile::fake()->createWithContent('kepala.csv', $csv),
        ])->assertSessionHasErrors('account_spreadsheet');

        $this->assertDatabaseMissing('users', ['role' => UserRole::Principal->value]);
    }

    public function test_account_template_and_export_are_xlsx_downloads(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP ZIP is not available.');
        }

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get(route('users.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)->get(route('users.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
