<?php

namespace App\Services\Exams;

use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\TrialAttempt;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ExamResetAllowance
{
    public const LIMIT = 2;

    // The caller must hold the owner's row lock within the same transaction.
    public function consume(ExamAssignment|TrialAttempt $owner, User $actor, bool $resume = false): int
    {
        $roles = [UserRole::SuperAdmin, UserRole::Committee, UserRole::Principal];
        if ($resume) {
            $roles[] = UserRole::Proctor;
        }
        abort_unless(in_array($actor->role, $roles, true), 403);
        if ($owner->security_reset_count >= self::LIMIT) {
            throw ValidationException::withMessages([
                'security' => 'Batas maksimal 2 kali reset atau izin lanjut untuk peserta pada ujian ini sudah digunakan. Jatah berlaku bersama untuk seluruh petugas.',
            ]);
        }
        $owner->forceFill(['security_reset_count' => $owner->security_reset_count + 1])->save();

        return (int) $owner->security_reset_count;
    }
}
