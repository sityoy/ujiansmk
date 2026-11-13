<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    #[DataProvider('roleLabels')]
    public function test_role_has_an_indonesian_label(UserRole $role, string $label): void
    {
        $this->assertSame($label, $role->label());
    }

    public static function roleLabels(): array
    {
        return [
            'super admin' => [UserRole::SuperAdmin, 'Super Admin'],
            'committee' => [UserRole::Committee, 'Panitia'],
            'teacher' => [UserRole::Teacher, 'Guru'],
            'proctor' => [UserRole::Proctor, 'Pengawas'],
            'principal' => [UserRole::Principal, 'Kepala Sekolah'],
            'student' => [UserRole::Student, 'Siswa'],
        ];
    }
}
