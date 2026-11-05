<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeExaminedStudents extends Command
{
    protected $signature = 'students:purge-examined
        {--execute : Jalankan penghapusan permanen}
        {--backup-confirmed : Konfirmasi bahwa backup database lengkap sudah dibuat}';

    protected $description = 'Pratinjau atau hapus permanen siswa yang sudah mempunyai percobaan ujian';

    public function handle(): int
    {
        $query = Student::query()->whereHas('examAssignments.attempt');
        $studentCount = (clone $query)->count();

        if ($studentCount === 0) {
            $this->info('Tidak ada siswa yang pernah memulai ujian.');

            return self::SUCCESS;
        }

        $studentIds = (clone $query)->select('students.id');
        $attemptCount = ExamAttempt::query()
            ->whereHas('assignment', fn ($assignment) => $assignment->whereIn('student_id', clone $studentIds))
            ->count();
        $answerCount = ExamAnswer::query()
            ->whereHas('attempt.assignment', fn ($assignment) => $assignment->whereIn('student_id', clone $studentIds))
            ->count();

        $preview = (clone $query)
            ->with(['schoolClass.academicYear', 'user'])
            ->withCount([
                'examAssignments as attempts_count' => fn ($assignment) => $assignment->whereHas('attempt'),
            ])
            ->orderBy('full_name')
            ->limit(50)
            ->get();

        $this->table(
            ['ID', 'NIS', 'NISN', 'Nama', 'Kelas', 'Tahun Ajaran', 'Percobaan'],
            $preview->map(fn (Student $student) => [
                $student->id,
                $student->student_number,
                $student->nisn ?: '-',
                $student->full_name,
                $student->schoolClass?->name ?: '-',
                $student->schoolClass?->academicYear?->name ?: '-',
                $student->attempts_count,
            ])->all(),
        );

        $this->newLine();
        $this->warn('Target penghapusan permanen:');
        $this->line('Siswa                 : '.$studentCount);
        $this->line('Percobaan ujian       : '.$attemptCount);
        $this->line('Jawaban tersimpan     : '.$answerCount);
        if ($studentCount > 50) {
            $this->line('Pratinjau tabel       : 50 dari '.$studentCount.' siswa');
        }

        if (! $this->option('execute')) {
            $this->newLine();
            $this->info('Mode pratinjau: belum ada data yang dihapus.');
            $this->line('Setelah backup database dibuat, jalankan:');
            $this->line('php artisan students:purge-examined --execute --backup-confirmed');

            return self::SUCCESS;
        }

        if (! $this->option('backup-confirmed')) {
            $this->error('Penghapusan dibatalkan. Buat backup database lengkap lalu tambahkan --backup-confirmed.');

            return self::FAILURE;
        }

        if (! $this->confirm('Hapus permanen seluruh siswa di atas beserta akun dan riwayat ujiannya?', false)) {
            $this->info('Penghapusan dibatalkan. Tidak ada data yang berubah.');

            return self::SUCCESS;
        }

        $backupRows = (clone $query)
            ->with(['schoolClass.academicYear', 'user'])
            ->orderBy('id')
            ->get()
            ->map(fn (Student $student) => [
                'student_id' => $student->id,
                'user_id' => $student->user_id,
                'nis' => $student->student_number,
                'nisn' => $student->nisn,
                'name' => $student->full_name,
                'username' => $student->user?->username,
                'email' => $student->user?->email,
                'class' => $student->schoolClass?->name,
                'academic_year' => $student->schoolClass?->academicYear?->name,
            ])
            ->values();

        $backupPath = 'backups/purged-examined-students-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put(
            $backupPath,
            json_encode([
                'created_at' => now()->toIso8601String(),
                'note' => 'Daftar identitas sebelum penghapusan. Pemulihan lengkap tetap menggunakan backup database.',
                'students' => $backupRows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $targetIds = $backupRows->pluck('student_id')->map(fn ($id) => (int) $id);
        $userIds = $backupRows->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->unique();

        try {
            DB::transaction(function () use ($targetIds, $userIds): void {
                Student::query()->whereIn('id', $targetIds)->delete();
                User::query()
                    ->whereIn('id', $userIds)
                    ->where('role', UserRole::Student->value)
                    ->delete();
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Penghapusan gagal dan transaksi dibatalkan: '.$exception->getMessage());
            $this->line('Daftar identitas tersimpan di storage/app/private/'.$backupPath);

            return self::FAILURE;
        }

        $this->info($studentCount.' siswa beserta akun dan riwayat ujiannya berhasil dihapus.');
        $this->line('Daftar identitas sebelum penghapusan: storage/app/private/'.$backupPath);

        return self::SUCCESS;
    }
}
