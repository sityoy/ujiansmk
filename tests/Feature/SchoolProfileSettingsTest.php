<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_the_school_identity(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->put(route('settings.school.update'), [
                'name' => 'SMK Contoh Jakarta',
                'npsn' => '12345678',
                'address' => 'Jalan Pendidikan No. 1',
                'city' => 'Jakarta',
                'phone' => '0211234567',
                'email' => 'sekolah@example.sch.id',
                'principal_name' => 'Kepala Sekolah',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('school_profiles', [
            'name' => 'SMK Contoh Jakarta',
            'npsn' => '12345678',
        ]);
    }

    public function test_non_super_admin_cannot_update_the_school_identity(): void
    {
        $committee = User::factory()->create(['role' => UserRole::Committee]);

        $this->actingAs($committee)
            ->put(route('settings.school.update'), ['name' => 'Tidak Boleh'])
            ->assertForbidden();

        $this->assertDatabaseCount('school_profiles', 0);
    }
}
