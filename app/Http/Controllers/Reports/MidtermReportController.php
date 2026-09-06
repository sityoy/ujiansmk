<?php

namespace App\Http\Controllers\Reports;

use App\Enums\AssessmentType;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\SchoolProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Reports\MidtermReportService;
use App\Services\Reports\MidtermReportAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;

class MidtermReportController extends Controller
{
    public function index(Request $request, MidtermReportAccess $access): View
    {
        $periods = AssessmentPeriod::query()
            ->where('type', AssessmentType::ATS)
            ->with(['academicYear', 'assessmentSubjects.schoolClass'])
            ->latest('starts_on')
            ->get();

        $periods->each(function (AssessmentPeriod $period) use ($request, $access): void {
            $classes = $period->assessmentSubjects->pluck('schoolClass')->filter()->unique('id')->sortBy('name')
                ->filter(fn (SchoolClass $schoolClass) => $access->canView($request->user(), $period, $schoolClass));
            $period->setRelation('accessibleClasses', $classes->values());
        });

        return view('reports.midterm.index', compact('periods'));
    }

    public function show(
        Request $request,
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        MidtermReportService $service,
        MidtermReportAccess $access,
    ): View {
        abort_unless($access->canView($request->user(), $assessmentPeriod, $schoolClass), 403);
        $report = $this->reportOrFail($service, $assessmentPeriod, $schoolClass);
        $canEdit = $report['subjects']->contains(fn ($subject) => $access->canManageSubject($request->user(), $subject))
            || $access->canRecordAttendance($request->user(), $schoolClass)
            || $report['extracurriculars']->contains(fn ($activity) => $access->canManageExtracurricular($request->user(), $activity))
            || $access->canConfigure($request->user());

        return view('reports.midterm.show', [...$report, 'canEdit' => $canEdit]);
    }

    public function print(
        Request $request,
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        Student $student,
        MidtermReportService $service,
        MidtermReportAccess $access,
    ): View {
        abort_unless($student->school_class_id === $schoolClass->id, 404);
        abort_unless($access->canView($request->user(), $assessmentPeriod, $schoolClass), 403);

        $report = $this->reportOrFail($service, $assessmentPeriod, $schoolClass);
        $row = $report['rows']->first(fn (array $item) => $item['student']->is($student));

        abort_unless($row, 404);

        return view('reports.midterm.print', [
            ...$report,
            'row' => $row,
            'letterheadData' => $this->letterheadData(),
        ]);
    }

    private function letterheadData(): ?string
    {
        $profile = SchoolProfile::query()->first();
        $path = $profile?->letterhead_path && Storage::exists($profile->letterhead_path)
            ? Storage::path($profile->letterhead_path)
            : public_path('images/kop-surat-smk-islam-bahagia.png');

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
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
