<?php

namespace App\Services\Students;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentRosterRevision
{
    public function current(): int
    {
        return (int) DB::table('student_roster_revision')->where('id', 1)->value('revision');
    }

    public function withCurrent(Closure $action, int $expected): mixed
    {
        return DB::transaction(function () use ($action, $expected): mixed {
            $current = (int) DB::table('student_roster_revision')->where('id', 1)->lockForUpdate()->value('revision');
            if ($current !== $expected) {
                throw ValidationException::withMessages([
                    'student_ids' => 'Daftar siswa sudah direset. Muat ulang halaman sebelum melanjutkan.',
                ]);
            }

            return $action();
        }, 3);
    }

    public function advance(): void
    {
        DB::table('student_roster_revision')->where('id', 1)->increment('revision');
    }
}
