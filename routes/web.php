<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Reports\MidtermReportController;
use App\Http\Controllers\Settings\SchoolProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => config('app.name'),
    'time' => now()->toIso8601String(),
]));

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:super_admin')->group(function (): void {
        Route::get('/settings/school', [SchoolProfileController::class, 'edit'])
            ->name('settings.school.edit');
        Route::put('/settings/school', [SchoolProfileController::class, 'update'])
            ->name('settings.school.update');
    });

    Route::prefix('reports/midterm')
        ->middleware('role:super_admin,committee,principal')
        ->name('reports.midterm.')
        ->group(function (): void {
            Route::get('/', [MidtermReportController::class, 'index'])->name('index');
            Route::get('/{assessmentPeriod}/{schoolClass}', [MidtermReportController::class, 'show'])->name('show');
            Route::get('/{assessmentPeriod}/{schoolClass}/{student}/print', [MidtermReportController::class, 'print'])->name('print');
        });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
