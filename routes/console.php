<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Services\Exams\ExamAttemptService;

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
