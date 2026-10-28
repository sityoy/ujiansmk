<?php

namespace App\Http\Controllers\Reports;

use App\Enums\AssessmentType;
use App\Enums\AttemptStatus;
use App\Enums\PeriodStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\ExamAttempt;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ExamResultController extends Controller
{
    public function index(Request $request): View
    {
        $periods = AssessmentPeriod::query()
            ->where('type', '!=', AssessmentType::ATS->value)
            ->where('status', PeriodStatus::Closed->value)
            ->with(['academicYear', 'assessmentSubjects.schoolClass'])
            ->latest('ends_on')
            ->paginate(10)
            ->withQueryString();

        $periods->getCollection()->each(function (AssessmentPeriod $period) use ($request): void {
            $classes = $period->assessmentSubjects
                ->pluck('schoolClass')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->filter(fn (SchoolClass $schoolClass) => $this->canPrint($request->user(), $schoolClass));
            $period->setRelation('accessibleClasses', $classes->values());
        });

        return view('reports.exam-results.index', compact('periods'));
    }

    public function print(
        Request $request,
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
    ): View {
        abort_if($assessmentPeriod->type === AssessmentType::ATS, 404);
        abort_unless($assessmentPeriod->status === PeriodStatus::Closed, 404);
        abort_unless($this->canPrint($request->user(), $schoolClass), 403);

        $subjects = AssessmentSubject::query()
            ->with('subject')
            ->where('assessment_period_id', $assessmentPeriod->id)
            ->where('school_class_id', $schoolClass->id)
            ->get()
            ->sortBy(fn (AssessmentSubject $component) => $component->subject->name)
            ->values();

        abort_if($subjects->isEmpty(), 404);

        $students = Student::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        $attempts = ExamAttempt::query()
            ->with('assignment')
            ->whereIn('status', [AttemptStatus::Submitted->value, AttemptStatus::Terminated->value])
            ->whereNotNull('score')
            ->whereHas('assignment', fn ($query) => $query->whereIn('assessment_subject_id', $subjects->pluck('id')))
            ->get()
            ->keyBy(fn (ExamAttempt $attempt) => $attempt->assignment->student_id.'-'.$attempt->assignment->assessment_subject_id);

        $rows = $students->map(function (Student $student) use ($subjects, $attempts): array {
            $scores = $subjects->mapWithKeys(function (AssessmentSubject $subject) use ($student, $attempts): array {
                $attempt = $attempts->get($student->id.'-'.$subject->id);

                return [$subject->id => $attempt ? (float) $attempt->score : null];
            });
            $availableScores = $scores->filter(fn ($score) => $score !== null);

            return [
                'student' => $student,
                'scores' => $scores,
                'average' => $availableScores->isEmpty() ? null : round($availableScores->avg(), 2),
                'rank' => null,
            ];
        });

        $ranked = $rows->filter(fn (array $row) => $row['average'] !== null)
            ->sortByDesc('average')
            ->values();
        $previousAverage = null;
        $previousRank = 0;
        $rankByStudent = [];
        foreach ($ranked as $index => $row) {
            $rank = $previousAverage !== null && $row['average'] === $previousAverage
                ? $previousRank
                : $index + 1;
            $rankByStudent[$row['student']->id] = $rank;
            $previousAverage = $row['average'];
            $previousRank = $rank;
        }
        $rows = $rows->map(function (array $row) use ($rankByStudent): array {
            $row['rank'] = $rankByStudent[$row['student']->id] ?? null;

            return $row;
        });

        return view('reports.exam-results.print', [
            'period' => $assessmentPeriod->loadMissing('academicYear'),
            'schoolClass' => $schoolClass->loadMissing('homeroomTeacher'),
            'subjects' => $subjects,
            'rows' => $rows,
            'school' => SchoolProfile::query()->first(),
            'letterheadData' => $this->letterheadData(),
        ]);
    }

    private function canPrint(User $user, SchoolClass $schoolClass): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Committee], true)
            || ($user->role === UserRole::Teacher && $schoolClass->homeroom_teacher_user_id === $user->id);
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
}
