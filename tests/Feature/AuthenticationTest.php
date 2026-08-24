<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

    public function test_user_with_initial_password_must_change_it_before_opening_dashboard(): void
    {
        $studentUser = User::factory()->create([
            'email' => null,
            'username' => '0123456789',
            'role' => UserRole::Student,
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

        $this->assertFalse($studentUser->refresh()->must_change_password);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
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
