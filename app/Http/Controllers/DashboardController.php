<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPeriod;
use App\Models\DailyCheckin;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->role->value === 'student') {
            return redirect()->route('student.exams.index');
        }

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
