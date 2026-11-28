<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\Exams\ExamStudentRoster;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SyncExamStudentRoster extends Command
{
    protected $signature = 'exams:sync-student-roster';

    protected $description = 'Sinkronkan status siswa dengan daftar peserta jadwal reguler yang masih berlaku';

    public function handle(ExamStudentRoster $roster): int
    {
        Student::query()->select('id')->chunkById(100, function (Collection $students) use ($roster): void {
            DB::transaction(fn () => $roster->sync(
                Student::query()->whereKey($students->modelKeys())->orderBy('id')->lockForUpdate()->get(),
            ), 3);
        });

        $this->info('Daftar peserta ujian reguler berhasil disinkronkan.');

        return self::SUCCESS;
    }
}
