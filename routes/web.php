<?php

use App\Http\Controllers\AcademicDataController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Reports\MidtermReportController;
use App\Http\Controllers\Settings\SchoolProfileController;
use App\Http\Controllers\UserManagementController;
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

        Route::prefix('academic')->name('academic.')->group(function (): void {
            Route::get('/', [AcademicDataController::class, 'index'])->name('index');

            Route::post('/years', [AcademicDataController::class, 'storeAcademicYear'])->name('years.store');
            Route::patch('/years/{academicYear}/activate', [AcademicDataController::class, 'activateAcademicYear'])->name('years.activate');
            Route::delete('/years/{academicYear}', [AcademicDataController::class, 'destroyAcademicYear'])->name('years.destroy');

            Route::post('/subjects', [AcademicDataController::class, 'storeSubject'])->name('subjects.store');
            Route::patch('/subjects/{subject}/toggle', [AcademicDataController::class, 'toggleSubject'])->name('subjects.toggle');
            Route::delete('/subjects/{subject}', [AcademicDataController::class, 'destroySubject'])->name('subjects.destroy');

            Route::post('/classes', [AcademicDataController::class, 'storeClass'])->name('classes.store');
            Route::delete('/classes/{schoolClass}', [AcademicDataController::class, 'destroyClass'])->name('classes.destroy');

            Route::post('/students', [AcademicDataController::class, 'storeStudent'])->name('students.store');
            Route::patch('/students/{student}/toggle', [AcademicDataController::class, 'toggleStudent'])->name('students.toggle');
            Route::delete('/students/{student}', [AcademicDataController::class, 'destroyStudent'])->name('students.destroy');
        });

        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/toggle', [UserManagementController::class, 'toggle'])->name('users.toggle');
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
