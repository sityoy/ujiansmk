<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_active_user_can_sign_in_and_open_the_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Committee,
            'is_active' => true,
            'password' => 'SecurePass123',
        ]);

        $response = $this->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'SecurePass123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Selamat datang');
    }

    public function test_students_on_one_school_ip_can_sign_in_while_repeated_guesses_are_limited(): void
    {
        $login = route('login.store');
        for ($index = 0; $index < 5; $index++) {
            $this->post($login, ['login' => 'unknown', 'password' => 'invalid'])
                ->assertRedirect()->assertSessionHasErrors('login');
        }
        $this->post($login, ['login' => 'UNKNOWN', 'password' => 'invalid'])->assertTooManyRequests();

        foreach (User::factory()->count(10)->create(['role' => UserRole::Student]) as $student) {
            $this->post($login, ['login' => $student->email, 'password' => 'password'])
                ->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($student);
            $this->post(route('logout'))->assertRedirect(route('login'));
        }
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'password' => 'SecurePass123',
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'login' => $user->email,
                'password' => 'SecurePass123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_student_can_sign_in_with_nisn_username(): void
    {
        $studentUser = User::factory()->create([
            'email' => null,
            'username' => '0123456789',
            'role' => UserRole::Student,
            'password' => 'SecurePass123',
        ]);

        $this->post(route('login.store'), [
            'login' => '0123456789',
            'password' => 'SecurePass123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($studentUser);
    }

    public function test_staff_with_initial_password_must_change_it_before_opening_dashboard(): void
    {
        $teacherUser = User::factory()->create([
            'email' => null,
            'username' => '0123456789',
            'role' => UserRole::Teacher,
            'password' => '0123456789',
            'must_change_password' => true,
        ]);

        $this->post(route('login.store'), [
            'login' => '0123456789',
            'password' => '0123456789',
        ])->assertRedirect(route('password.change'));

        $this->get(route('dashboard'))->assertRedirect(route('password.change'));

        $this->put(route('password.update'), [
            'current_password' => '0123456789',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($teacherUser->refresh()->must_change_password);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_login_displays_both_logos_and_the_configured_school_name(): void
    {
        SchoolProfile::create(['name' => 'SMK Islam Bahagia']);

        $this->get(route('login'))->assertOk()
            ->assertSeeInOrder(['logo-ypmi-bahagia.jpeg', 'SMK Islam Bahagia', 'logo-pt-sityoy.png', 'PT Sityoy IT Solutions', 'Masuk ke sistem'])
            ->assertSee('data-password-toggle', false);
        $this->assertFileExists(public_path('images/logo-pt-sityoy.png'));
    }

    public function test_custom_school_logo_replaces_the_default_on_login(): void
    {
        Storage::fake();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $profile = SchoolProfile::create(['name' => 'SMK Contoh']);
        $this->actingAs($admin)->put(route('settings.school.update'), [
            'name' => $profile->name, 'logo' => UploadedFile::fake()->image('sekolah.png', 300, 200),
        ])->assertSessionHasNoErrors();
        $profile->refresh();
        Storage::assertExists($profile->logo_path);
        $logoUrl = SchoolProfile::logoUrl($profile);
        $this->get($logoUrl)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('settings.school.edit'))->assertOk()->assertSee($logoUrl);
        $this->post(route('logout'));
        $this->get(route('login'))->assertOk()->assertSee($logoUrl)->assertDontSee('logo-ypmi-bahagia.jpeg');
    }

    public function test_inactive_student_receives_the_recorded_reason_only_after_password_verification(): void
    {
        $user = User::factory()->create(['role' => UserRole::Student, 'password' => 'SecurePass123', 'is_active' => false]);
        $year = AcademicYear::create(['name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
        $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'X-1', 'grade_level' => 10]);
        $student = Student::create(['user_id' => $user->id, 'school_class_id' => $class->id, 'student_number' => 'T-01',
            'full_name' => 'Peserta Nonaktif', 'is_active' => false, 'inactive_reason' => 'Administrasi sekolah perlu diverifikasi.']);

        $credentials = ['login' => $user->email];
        $this->from(route('login'))->post(route('login.store'), $credentials + ['password' => 'wrong'])
            ->assertSessionHasErrors(['login' => 'Email/NISN/NIS, kata sandi, atau status akun tidak sesuai.']);
        $this->post(route('login.store'), $credentials + ['password' => 'SecurePass123'])
            ->assertRedirect(route('login'))->assertSessionHasErrors(['login' => $student->inactiveNotice()]);
        $this->assertGuest();

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->get(route('academic.index', ['student_status' => 'inactive']))
            ->assertOk()->assertSee($student->full_name)->assertSee($student->inactive_reason);
        $this->patch(route('academic.students.toggle', $student), ['roster_revision' => 0, 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertNull($student->fresh()->inactive_reason);
        $this->post(route('logout'));
        $this->post(route('login.store'), $credentials + ['password' => 'SecurePass123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_can_sign_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_role_middleware_blocks_an_unlisted_role(): void
    {
        Route::middleware(['web', 'auth', 'active', 'role:super_admin'])
            ->get('/_testing/admin-only', fn () => response('allowed'));

        $committee = User::factory()->create(['role' => UserRole::Committee]);

        $this->actingAs($committee)
            ->get('/_testing/admin-only')
            ->assertForbidden();

        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)
            ->get('/_testing/admin-only')
            ->assertOk()
            ->assertSee('allowed');
    }
}
