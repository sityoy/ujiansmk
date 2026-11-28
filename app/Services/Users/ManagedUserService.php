<?php

namespace App\Services\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ManagedUserService
{
    public const MAX_SUPER_ADMINS = 4;

    public const MAX_PRINCIPALS = 1;

    /** @return array<int, UserRole> */
    public function roles(): array
    {
        return array_values(array_filter(
            UserRole::cases(),
            fn (UserRole $role): bool => $role !== UserRole::Student,
        ));
    }

    public function roleFromSpreadsheet(string $value): ?UserRole
    {
        $normalized = str($value)->lower()->trim()->replace(['-', '_'], ' ')->squish()->toString();

        return match ($normalized) {
            'super admin', 'superadmin' => UserRole::SuperAdmin,
            'committee', 'panitia' => UserRole::Committee,
            'teacher', 'guru' => UserRole::Teacher,
            'proctor', 'pengawas' => UserRole::Proctor,
            'principal', 'kepala sekolah', 'kepsek' => UserRole::Principal,
            default => null,
        };
    }

    public function assertRoleCapacity(UserRole $role, ?User $except = null): void
    {
        $maximum = $this->maximumFor($role);

        if ($maximum === null) {
            return;
        }

        $count = User::query()
            ->where('role', $role)
            ->when($except, fn ($query) => $query->where('id', '!=', $except->getKey()))
            ->count();

        if ($count >= $maximum) {
            throw ValidationException::withMessages([
                'role' => $this->capacityMessage($role),
            ]);
        }
    }

    /** @param array<string, int> $counts */
    public function assertProjectedRoleCounts(array $counts): void
    {
        if (($counts[UserRole::Principal->value] ?? 0) > self::MAX_PRINCIPALS) {
            throw ValidationException::withMessages([
                'account_spreadsheet' => $this->capacityMessage(UserRole::Principal),
            ]);
        }

        if (($counts[UserRole::SuperAdmin->value] ?? 0) > self::MAX_SUPER_ADMINS) {
            throw ValidationException::withMessages([
                'account_spreadsheet' => $this->capacityMessage(UserRole::SuperAdmin),
            ]);
        }
    }

    public function capacityMessage(UserRole $role): string
    {
        return match ($role) {
            UserRole::Principal => 'Akun Kepala Sekolah hanya boleh berjumlah satu.',
            UserRole::SuperAdmin => 'Akun Super Admin maksimal berjumlah empat.',
            default => 'Jumlah akun untuk hak akses tersebut sudah mencapai batas.',
        };
    }

    private function maximumFor(UserRole $role): ?int
    {
        return match ($role) {
            UserRole::Principal => self::MAX_PRINCIPALS,
            UserRole::SuperAdmin => self::MAX_SUPER_ADMINS,
            default => null,
        };
    }
}
