<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPeriod;
use App\Models\DailyCheckin;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            'user' => $request->user(),
            'statistics' => [
                'students' => Student::query()->where('is_active', true)->count(),
                'assessmentPeriods' => AssessmentPeriod::query()->count(),
                'examSessions' => ExamSession::query()->count(),
                'checkinsToday' => DailyCheckin::query()
                    ->whereDate('attendance_date', now()->toDateString())
                    ->count(),
            ],
        ]);
    }
}
