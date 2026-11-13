<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesRoomPeriods;
use Tests\TestCase;

class StudentLoginCardsTest extends TestCase
{
    use CreatesRoomPeriods;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_legacy_student_flag_does_not_force_a_password_change_on_login_or_existing_sessions(): void
    {
        $student = $this->makeStudent('000123', '0123456789', '000123');
        $student->user->update(['must_change_password' => true]);
        $this->post(route('login.store'), ['login' => '0123456789', 'password' => '000123'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertRedirect(route('student.exams.index'));
        $this->get(route('student.exams.index'))->assertOk();
        $this->get(route('password.change'))->assertOk()->assertDontSee('Ganti password terlebih dahulu');
    }

    public function test_cards_print_the_actual_username_and_configured_password_without_resetting_it(): void
    {
        $student = $this->makeStudent('000123', '0123456789', 'SiswaAwal123');
        $user = $student->user;
        $user->username = 'login-siswa-khusus';
        $user->setStudentPassword('SiswaAwal123');
        $user->save();
        $hash = $user->password;

        $this->assertStringNotContainsString('SiswaAwal123', $user->getRawOriginal('student_card_credentials'));
        $this->assertArrayNotHasKey('student_card_credentials', $user->toArray());
        $this->assertTrue(Hash::check('SiswaAwal123', $hash));

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->post(route('academic.students.participant-cards'), [
            'school_class_id' => $student->school_class_id,
            'assessment_period_id' => $this->makeRoomPeriod($student->schoolClass->academic_year_id)->id,
        ])->assertOk()
            ->assertSee('login-siswa-khusus')
            ->assertSee('images/logo-ypmi-bahagia.jpeg')
            ->assertSee('<span>Ruangan</span><span>:</span><strong>Belum diatur</strong>', false)
            ->assertSee('<span>Password</span><span>:</span><strong>SiswaAwal123</strong>', false)
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_legacy_default_passwords_are_verified_and_unknown_passwords_are_not_guessed(): void
    {
        $student = $this->makeStudent('000123', '0123456789', '000123');
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        $data = ['school_class_id' => $student->school_class_id, 'assessment_period_id' => $this->makeRoomPeriod($student->schoolClass->academic_year_id)->id];
        $route = route('academic.students.participant-cards');
        $this->post($route, $data)->assertOk()
            ->assertSee('<span>Password</span><span>:</span><strong>000123</strong>', false);

        $student->user->update(['password' => '0123456789']);
        $this->post($route, $data)->assertOk()
            ->assertSee('<span>Password</span><span>:</span><strong>0123456789</strong>', false);

        $student->user->update(['password' => 'KhususLama123']);
        $hash = $student->user->password;
        $this->post($route, $data)->assertOk()
            ->assertSee('Diatur khusus; konfirmasi ke panitia')
            ->assertDontSee('<span>Password</span><span>:</span><strong>000123</strong>', false)
            ->assertDontSee('<span>Password</span><span>:</span><strong>0123456789</strong>', false);
        $this->assertSame($hash, $student->user->fresh()->password);
    }

    public function test_optional_password_change_invalidates_printable_initial_credentials(): void
    {
        $student = $this->makeStudent('000123', '0123456789', 'SiswaAwal123');
        $user = $student->user;
        $user->setStudentPassword('SiswaAwal123');
        $user->save();
        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'SiswaAwal123',
            'password' => 'PasswordPribadi123',
            'password_confirmation' => 'PasswordPribadi123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertNull($user->fresh()->student_card_credentials);
        $this->assertNull($student->fresh()->printablePassword());
        $this->assertTrue(Hash::check('PasswordPribadi123', $user->fresh()->password));
    }

    public function test_bulk_cards_include_all_active_accounts_and_label_each_class_and_year_correctly(): void
    {
        $first = $this->makeStudent('001', '0123456701', '001');
        $first->update(['full_name' => 'Zainab Kelas Satu']);
        $class = $first->schoolClass;
        $period = $this->makeRoomPeriod($class->academic_year_id);
        $first->examRooms()->create(['assessment_period_id' => $period->id, 'room_name' => 'Ruang 01']);
        $secondClass = SchoolClass::create([
            'academic_year_id' => $class->academic_year_id, 'name' => 'X-2', 'grade_level' => 10,
        ]);
        $second = $this->makeStudent('002', '0123456702', '002', $secondClass);
        $second->update(['full_name' => 'Andi Kelas Dua']);
        $second->examRooms()->create(['assessment_period_id' => $period->id, 'room_name' => 'Lab <Komputer> & Bahasa']);
        $inactiveStudent = $this->makeStudent('003', '0123456703', '003', $secondClass);
        $inactiveStudent->update(['full_name' => 'Siswa Dinonaktifkan', 'is_active' => false]);
        $inactiveUser = $this->makeStudent('004', '0123456704', '004', $secondClass);
        $inactiveUser->update(['full_name' => 'Akun Dinonaktifkan']);
        $inactiveUser->user->update(['is_active' => false]);
        $oldYear = AcademicYear::create([
            'name' => '2025/2026', 'starts_on' => '2025-07-01', 'ends_on' => '2026-06-30', 'is_active' => false,
        ]);
        $oldClass = SchoolClass::create(['academic_year_id' => $oldYear->id, 'name' => 'X-1', 'grade_level' => 10]);
        $old = $this->makeStudent('005', '0123456705', '005', $oldClass);
        $old->update(['full_name' => 'Peserta Tahun Lama']);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        $hashes = User::query()->where('role', UserRole::Student)->pluck('password', 'id')->all();
        $route = route('academic.students.participant-cards');
        $this->post($route, ['school_class_id' => 'all', 'assessment_period_id' => $period->id])
            ->assertOk()
            ->assertViewHas('students', fn ($students): bool => $students->count() === 2)
            ->assertSeeInOrder(['X-1 · 2026/2027', 'Zainab Kelas Satu', 'Ruang 01', 'X-2 · 2026/2027', 'Andi Kelas Dua', 'Lab <Komputer> & Bahasa'])
            ->assertDontSee('Lab <Komputer> & Bahasa', false)
            ->assertDontSee('Peserta Tahun Lama')->assertDontSee('Siswa Dinonaktifkan')->assertDontSee('Akun Dinonaktifkan');
        $this->post($route, ['school_class_id' => 'all', 'assessment_period_id' => $this->makeRoomPeriod($oldYear->id)->id])
            ->assertOk()->assertViewHas('classGroups', fn ($groups): bool => $groups->count() === 1)
            ->assertSee('X-1 · 2025/2026')->assertSee('Peserta Tahun Lama');
        $this->post($route, ['school_class_id' => $secondClass->id, 'assessment_period_id' => $period->id])
            ->assertOk()->assertSee('Andi Kelas Dua')->assertSee('Lab <Komputer> & Bahasa')->assertDontSee('Zainab Kelas Satu');
        $this->assertSame($hashes, User::query()->where('role', UserRole::Student)->pluck('password', 'id')->all());
    }

    public function test_bulk_print_validates_the_selection_and_remains_restricted_to_academic_managers(): void
    {
        $student = $this->makeStudent('001', '0123456701', '001');
        $otherYear = AcademicYear::create([
            'name' => '2025/2026', 'starts_on' => '2025-07-01', 'ends_on' => '2026-06-30', 'is_active' => false,
        ]);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $route = route('academic.students.participant-cards');
        $period = $this->makeRoomPeriod($student->schoolClass->academic_year_id);
        $otherPeriod = $this->makeRoomPeriod($otherYear->id);
        $this->actingAs($admin)->post($route, [
            'school_class_id' => $student->school_class_id, 'assessment_period_id' => $otherPeriod->id,
        ])->assertSessionHasErrors('school_class_id');
        $this->post($route, ['school_class_id' => 'invalid', 'assessment_period_id' => $period->id])->assertSessionHasErrors('school_class_id');
        $this->post($route, ['school_class_id' => 'all', 'assessment_period_id' => $otherPeriod->id])
            ->assertOk()->assertSee('Tidak ada siswa aktif')->assertDontSee('0123456701');
        $this->post($route, ['school_class_id' => 'all'])->assertSessionHasErrors('assessment_period_id');
        $this->post($route, ['school_class_id' => 'all', 'assessment_period_id' => $period->id, 'academic_year_id' => $otherYear->id])->assertSessionHasErrors('academic_year_id');
        $this->actingAs($student->user)->post($route, ['school_class_id' => 'all'])->assertForbidden();
    }

    private function makeStudent(string $nis, string $nisn, string $password, ?SchoolClass $class = null): Student
    {
        if (! $class) {
            $year = AcademicYear::create([
                'name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => true,
            ]);
            $class = SchoolClass::create([
                'academic_year_id' => $year->id, 'name' => 'X-1', 'grade_level' => 10,
            ]);
        }
        $user = User::factory()->create([
            'role' => UserRole::Student, 'email' => null, 'username' => $nisn,
            'password' => $password, 'is_active' => true,
        ]);

        return Student::create([
            'user_id' => $user->id, 'school_class_id' => $class->id,
            'student_number' => $nis, 'nisn' => $nisn, 'full_name' => 'Peserta X-1', 'is_active' => true,
        ]);
    }
}
