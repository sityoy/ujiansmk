<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
}
