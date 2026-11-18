<?php

namespace App\Services\Students;

use App\Enums\AttemptStatus;
use App\Enums\UserRole;
use App\Models\DailyCheckin;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\Student;
use App\Models\TrialAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentDeletionService
{
    /** @param array<int, int|string> $studentIds */
    public function delete(array $studentIds): int
    {
        $ids = array_values(array_unique(array_map('intval', $studentIds)));
        if ($ids === [] || count($ids) > 500) {
            throw ValidationException::withMessages(['student_ids' => 'Pilih 1 sampai 500 siswa untuk dihapus.']);
        }

        return DB::transaction(function () use ($ids): int {
            // The parent locks also serialize this operation with trial starts.
            $students = Student::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            if ($students->count() !== count($ids)) {
                throw ValidationException::withMessages(['student_ids' => 'Data siswa berubah. Muat ulang daftar lalu pilih kembali siswa.']);
            }
            $users = User::query()->whereKey($students->pluck('user_id')->filter())->orderBy('id')->lockForUpdate()->get();
            if ($users->contains(fn (User $user): bool => $user->role !== UserRole::Student)) {
                throw ValidationException::withMessages(['student_ids' => 'Ada siswa yang terhubung ke akun selain siswa. Periksa data akun sebelum menghapus.']);
            }

            // Core starts lock their assignment before creating an attempt. A
            // locking read below sees attempts committed while waiting for it.
            $assignments = ExamAssignment::query()->whereIn('student_id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');
            $attempts = ExamAttempt::query()->whereIn('exam_assignment_id', $assignments)->orderBy('id')->lockForUpdate()->get(['id', 'status']);
            $identifiers = $students->flatMap(fn (Student $student) => [$student->student_number, $student->nisn])->filter(fn ($value) => $value !== null && $value !== '')->unique()->values();
            $conflicts = Student::query()->whereNotIn('id', $ids)
                ->where(fn ($query) => $query->whereIn('student_number', $identifiers)->orWhereIn('nisn', $identifiers))
                ->get(['student_number', 'nisn'])->flatMap(fn (Student $student) => [$student->student_number, $student->nisn])->filter(fn ($value) => $value !== null && $value !== '');
            $legacyIdentifiers = $identifiers->diff($conflicts)->values();
            $trials = TrialAttempt::query()->where(fn ($query) => $query
                ->whereIn('student_id', $ids)
                ->orWhere(fn ($legacy) => $legacy->whereNull('student_id')
                    ->whereIn('participant_identifier', $legacyIdentifiers)
                    ->whereIn('participant_name', $students->pluck('full_name'))))
                ->orderBy('id')->lockForUpdate()->get(['id', 'status']);
            if ($attempts->contains('status', AttemptStatus::InProgress) || $trials->contains('status', 'in_progress')) {
                throw ValidationException::withMessages(['student_ids' => 'Penghapusan dibatalkan karena ada siswa terpilih yang masih mengerjakan ujian inti atau uji coba. Selesaikan ujiannya terlebih dahulu.']);
            }

            $selfies = DailyCheckin::query()->whereIn('student_id', $ids)->pluck('selfie_path')->filter()->unique()->all();
            // The trial FK normally becomes NULL on student deletion. Remove
            // owned attempts and unambiguous legacy matches explicitly so they
            // cannot reattach on re-import. Ambiguous legacy identities remain.
            TrialAttempt::query()->whereKey($trials->modelKeys())->delete();
            Student::query()->whereKey($ids)->delete();
            User::query()->whereKey($users->modelKeys())->where('role', UserRole::Student)->delete();

            DB::afterCommit(function () use ($selfies): void {
                foreach ($selfies as $path) {
                    try {
                        if (! DailyCheckin::query()->where('selfie_path', $path)->exists()) {
                            Storage::disk('local')->delete($path);
                        }
                    } catch (Throwable $exception) {
                        // A file cleanup error must not report a failed deletion
                        // after the database transaction has already committed.
                        report($exception);
                    }
                }
            });

            return $students->count();
        }, 3);
    }
}
