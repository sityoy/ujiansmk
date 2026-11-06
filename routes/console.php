<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Services\Exams\ExamAttemptService;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('exams:finalize-expired', function (ExamAttemptService $service) {
    $count = 0;
    ExamAttempt::query()->where('status', AttemptStatus::InProgress)
        ->with('assignment.examSession')
        ->eachById(function (ExamAttempt $attempt) use ($service, &$count): void {
            if ($service->isExpired($attempt)) {
                $service->submit($attempt);
                $count++;
            }
        });
    $this->info("Ujian kedaluwarsa diproses: {$count}.");
})->purpose('Kumpulkan ujian yang waktunya habis meskipun siswa menutup browser');

Schedule::command('exams:finalize-expired')->everyMinute()->withoutOverlapping();

Artisan::command('exams:readiness {--deployment : Tolak jika masih ada ujian berlangsung}', function () {
    $checks = [];
    $failed = false;
    $databaseReady = false;
    $check = function (string $name, bool $passed, string $detail, bool $critical = true) use (&$checks, &$failed): void {
        $checks[] = [$name, $passed ? 'OK' : ($critical ? 'GAGAL' : 'PERINGATAN'), $detail];
        if (! $passed && $critical) {
            $failed = true;
        }
    };

    try {
        DB::connection()->getPdo();
        $databaseReady = true;
        $check('Database', true, DB::connection()->getDatabaseName());
    } catch (Throwable $exception) {
        $check('Database', false, $exception->getMessage());
    }

    $check('Aset frontend', file_exists(public_path('build/manifest.json')), 'public/build/manifest.json');
    $check('Storage Laravel', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'storage dan bootstrap/cache harus writable');
    $check('Mode production', app()->environment('production'), 'APP_ENV='.app()->environment(), false);
    $check('Debug nonaktif', ! config('app.debug'), 'APP_DEBUG='.(config('app.debug') ? 'true' : 'false'));
    $check('Session bersama', in_array(config('session.driver'), ['database', 'redis'], true), 'SESSION_DRIVER='.config('session.driver'));
    $check('Cache bersama', in_array(config('cache.default'), ['database', 'redis'], true), 'CACHE_STORE='.config('cache.default'));
    $check('Versi PHP', version_compare(PHP_VERSION, '8.4.1', '>='), 'PHP '.PHP_VERSION.' (minimal 8.4.1)');

    $freeBytes = @disk_free_space(base_path());
    $freeMb = $freeBytes === false ? null : (int) floor($freeBytes / 1024 / 1024);
    $check('Disk kosong', $freeMb !== null && $freeMb >= 1024, $freeMb === null ? 'tidak dapat dibaca' : $freeMb.' MB tersedia');

    if ($databaseReady) {
        $activeAttempts = ExamAttempt::query()->where('status', AttemptStatus::InProgress)->count();
        $check('Ujian berlangsung', ! $this->option('deployment') || $activeAttempts === 0, $activeAttempts.' peserta masih mengerjakan');
    } else {
        $check('Ujian berlangsung', false, 'tidak dapat diperiksa karena database gagal');
    }

    $this->table(['Pemeriksaan', 'Status', 'Keterangan'], $checks);

    return $failed ? 1 : 0;
})->purpose('Periksa kesiapan aplikasi dan keamanan waktu deployment');
