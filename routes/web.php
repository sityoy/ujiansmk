<?php

use App\Http\Controllers\AcademicDataController;
use App\Http\Controllers\AttendanceSecurityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamOperationsController;
use App\Http\Controllers\QuestionBankController;
use App\Http\Controllers\Reports\MidtermReportController;
use App\Http\Controllers\SchedulingController;
use App\Http\Controllers\Settings\SchoolProfileController;
use App\Http\Controllers\StudentAttendanceController;
use App\Http\Controllers\StudentExamController;
use App\Http\Controllers\StudentSpreadsheetController;
use App\Http\Controllers\SubjectSpreadsheetController;
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
    Route::get('/account/password', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/account/password', [PasswordChangeController::class, 'update'])->name('password.update');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('student')
            ->middleware('role:student')
            ->name('student.')
            ->group(function (): void {
                Route::get('/exams', [StudentExamController::class, 'index'])->name('exams.index');
                Route::post('/attendance/{assignment}', [StudentAttendanceController::class, 'store'])
                    ->middleware('throttle:10,1')
                    ->name('attendance.store');
                Route::post('/exams/{assignment}/start', [StudentExamController::class, 'start'])->name('exams.start');
                Route::get('/attempts/{attempt}', [StudentExamController::class, 'show'])->name('exams.show');
                Route::put('/attempts/{attempt}/questions/{question}', [StudentExamController::class, 'answer'])
                    ->middleware('throttle:120,1')
                    ->name('exams.answer');
                Route::post('/attempts/{attempt}/submit', [StudentExamController::class, 'submit'])->name('exams.submit');
                Route::post('/attempts/{attempt}/incidents', [StudentExamController::class, 'incident'])
                    ->middleware('throttle:30,1')
                    ->name('exams.incident');
            });

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
            Route::get('/subjects/template', [SubjectSpreadsheetController::class, 'template'])->name('subjects.template');
            Route::get('/subjects/export', [SubjectSpreadsheetController::class, 'export'])->name('subjects.export');
            Route::post('/subjects/import', [SubjectSpreadsheetController::class, 'import'])->name('subjects.import');
            Route::patch('/subjects/{subject}/toggle', [AcademicDataController::class, 'toggleSubject'])->name('subjects.toggle');
            Route::delete('/subjects/{subject}', [AcademicDataController::class, 'destroySubject'])->name('subjects.destroy');

            Route::post('/classes', [AcademicDataController::class, 'storeClass'])->name('classes.store');
            Route::delete('/classes/{schoolClass}', [AcademicDataController::class, 'destroyClass'])->name('classes.destroy');

            Route::post('/students', [AcademicDataController::class, 'storeStudent'])->name('students.store');
            Route::get('/students/template', [StudentSpreadsheetController::class, 'template'])->name('students.template');
            Route::get('/students/export', [StudentSpreadsheetController::class, 'export'])->name('students.export');
            Route::post('/students/import', [StudentSpreadsheetController::class, 'import'])->name('students.import');
            Route::patch('/students/{student}/toggle', [AcademicDataController::class, 'toggleStudent'])->name('students.toggle');
            Route::delete('/students/{student}', [AcademicDataController::class, 'destroyStudent'])->name('students.destroy');
        });

        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/toggle', [UserManagementController::class, 'toggle'])->name('users.toggle');
    });

    Route::prefix('scheduling')
        ->middleware('role:super_admin,committee')
        ->name('scheduling.')
        ->group(function (): void {
            Route::get('/', [SchedulingController::class, 'index'])->name('index');
            Route::post('/campuses', [SchedulingController::class, 'storeCampus'])->name('campuses.store');
            Route::patch('/campuses/{campus}/toggle', [SchedulingController::class, 'toggleCampus'])->name('campuses.toggle');

            Route::post('/periods', [SchedulingController::class, 'storePeriod'])->name('periods.store');
            Route::patch('/periods/{assessmentPeriod}/status', [SchedulingController::class, 'updatePeriodStatus'])->name('periods.status');
            Route::delete('/periods/{assessmentPeriod}', [SchedulingController::class, 'destroyPeriod'])->name('periods.destroy');

            Route::post('/components', [SchedulingController::class, 'storeComponent'])->name('components.store');
            Route::delete('/components/{assessmentSubject}', [SchedulingController::class, 'destroyComponent'])->name('components.destroy');

            Route::post('/sessions', [SchedulingController::class, 'storeSession'])->name('sessions.store');
            Route::delete('/sessions/{examSession}', [SchedulingController::class, 'destroySession'])->name('sessions.destroy');
            Route::post('/components/{assessmentSubject}/sessions/{examSession}/assign-class', [SchedulingController::class, 'assignClass'])
                ->name('assign-class');
            Route::post('/makeup/move', [SchedulingController::class, 'moveToMakeup'])->name('makeup.move');

            Route::get('/components/{assessmentSubject}/questions', [QuestionBankController::class, 'index'])
                ->name('questions.index');
            Route::post('/components/{assessmentSubject}/questions', [QuestionBankController::class, 'store'])
                ->name('questions.store');
            Route::delete('/components/{assessmentSubject}/questions/{question}', [QuestionBankController::class, 'destroy'])
                ->name('questions.destroy');
        });

    Route::prefix('reports/midterm')
        ->middleware('role:super_admin,committee,principal')
        ->name('reports.midterm.')
        ->group(function (): void {
            Route::get('/', [MidtermReportController::class, 'index'])->name('index');
            Route::get('/{assessmentPeriod}/{schoolClass}', [MidtermReportController::class, 'show'])->name('show');
            Route::get('/{assessmentPeriod}/{schoolClass}/{student}/print', [MidtermReportController::class, 'print'])->name('print');
        });

    Route::prefix('operations')
        ->middleware('role:super_admin,committee,proctor')
        ->name('operations.')
        ->group(function (): void {
            Route::get('/', [ExamOperationsController::class, 'index'])->name('index');
            Route::patch('/sessions/{examSession}/status', [ExamOperationsController::class, 'updateSessionStatus'])
                ->name('sessions.status');
        });

    Route::prefix('attendance')
        ->middleware('role:super_admin,committee,proctor')
        ->name('attendance.')
        ->group(function (): void {
            Route::get('/', [AttendanceSecurityController::class, 'index'])->name('index');
            Route::get('/{dailyCheckin}/selfie', [AttendanceSecurityController::class, 'selfie'])->name('selfie');
            Route::patch('/{dailyCheckin}/status', [AttendanceSecurityController::class, 'updateStatus'])->name('status');
        });

    });
});
