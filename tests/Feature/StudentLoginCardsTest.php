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

class StudentLoginCardsTest extends TestCase
{
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
        ])->assertOk()
            ->assertSee('login-siswa-khusus')
            ->assertSee('<span>Password</span><span>:</span><strong>SiswaAwal123</strong>', false)
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_legacy_default_passwords_are_verified_and_unknown_passwords_are_not_guessed(): void
    {
        $student = $this->makeStudent('000123', '0123456789', '000123');
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        $data = ['school_class_id' => $student->school_class_id];
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

    private function makeStudent(string $nis, string $nisn, string $password): Student
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $year->id, 'name' => 'X-1', 'grade_level' => 10,
        ]);
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
