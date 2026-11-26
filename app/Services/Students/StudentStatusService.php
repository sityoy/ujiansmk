<?php

namespace App\Services\Students;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class StudentStatusService
{
    public function update(Collection $students, bool $active, ?string $reason): void
    {
        Student::query()->whereKey($students->modelKeys())->update([
            'is_active' => $active,
            'inactive_reason' => $active ? null : $reason,
        ]);

        User::query()->where('role', UserRole::Student)->whereKey($students->pluck('user_id')->filter()->all())
            ->update(['is_active' => $active]);
    }
}
