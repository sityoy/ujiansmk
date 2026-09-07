<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_proctor_can_open_operations_and_attendance_pages(): void
    {
        $this->withoutVite();
        $proctor = User::factory()->create(['role' => UserRole::Proctor]);

        $this->actingAs($proctor)->get(route('operations.index'))->assertOk();
        $this->actingAs($proctor)->get(route('attendance.index'))->assertOk();
    }

    public function test_teacher_cannot_open_restricted_operations_pages(): void
    {
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);

        $this->actingAs($teacher)->get(route('operations.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('attendance.index'))->assertForbidden();
    }
}
