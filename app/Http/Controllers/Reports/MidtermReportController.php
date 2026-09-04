<?php

namespace App\Http\Controllers\Reports;

use App\Enums\AssessmentType;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Reports\MidtermReportService;
use Illuminate\View\View;
use InvalidArgumentException;

class MidtermReportController extends Controller
{
    public function index(): View
    {
        $periods = AssessmentPeriod::query()
            ->where('type', AssessmentType::ATS)
            ->with(['academicYear', 'assessmentSubjects.schoolClass'])
            ->latest('starts_on')
            ->get();

        return view('reports.midterm.index', compact('periods'));
    }

    public function show(
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        MidtermReportService $service,
    ): View {
        return view('reports.midterm.show', $this->reportOrFail($service, $assessmentPeriod, $schoolClass));
    }

    public function print(
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        Student $student,
        MidtermReportService $service,
    ): View {
        abort_unless($student->school_class_id === $schoolClass->id, 404);

        $report = $this->reportOrFail($service, $assessmentPeriod, $schoolClass);
        $row = $report['rows']->first(fn (array $item) => $item['student']->is($student));

        abort_unless($row, 404);

        return view('reports.midterm.print', [
            ...$report,
            'row' => $row,
        ]);
    }

    private function reportOrFail(
        MidtermReportService $service,
        AssessmentPeriod $period,
        SchoolClass $schoolClass,
    ): array {
        try {
            return $service->build($period, $schoolClass);
        } catch (InvalidArgumentException $exception) {
            abort(404, $exception->getMessage());
        }
    }
}
