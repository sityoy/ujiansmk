<?php

namespace App\Http\Controllers\Reports;

use App\Enums\AssessmentType;
use App\Enums\AttemptStatus;
use App\Enums\ExamQuestionType;
use App\Enums\PeriodStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\ExamAttempt;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\User;
use App\Services\Exams\ExamScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExamResultController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'type' => ['nullable', Rule::enum(AssessmentType::class)],
            'status' => ['nullable', Rule::enum(PeriodStatus::class)],
        ]);

        $periods = AssessmentPeriod::query()
            ->when($filters['academic_year_id'] ?? null, fn ($query, $year) => $query->where('academic_year_id', $year))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with(['academicYear', 'assessmentSubjects.schoolClass'])
            ->latest('ends_on')
            ->paginateForDisplay()
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

        return view('reports.exam-results.index', [
            'periods' => $periods,
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'assessmentTypes' => AssessmentType::cases(),
            'periodStatuses' => PeriodStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function print(
        Request $request,
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        ExamScoreCalculator $calculator,
    ): View {
        abort_unless($this->canPrint($request->user(), $schoolClass), 403);

        $subjects = AssessmentSubject::query()
            ->with(['subject', 'questions:id,assessment_subject_id,question_type,points'])
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

        $subjectsById = $subjects->keyBy('id');
        $attempts = ExamAttempt::query()
            ->with(['assignment', 'answers:id,exam_attempt_id,exam_question_id,is_correct,points_awarded'])
            ->where(fn ($query) => $query->where('status', AttemptStatus::Submitted)
                ->orWhere(fn ($terminated) => $terminated->where('status', AttemptStatus::Terminated)->whereNotNull('score')))
            ->whereHas('assignment', fn ($query) => $query->whereIn('assessment_subject_id', $subjectsById->keys())
                ->whereIn('student_id', $students->modelKeys()))
            ->get();

        $componentsBySubject = [];
        $results = [];
        foreach ($attempts as $attempt) {
            $subject = $subjectsById->get($attempt->assignment->assessment_subject_id);
            $answers = $attempt->answers->keyBy('exam_question_id');
            $components = $calculator->componentScores(
                $subject->questions,
                $answers,
                $attempt->manual_score === null ? null : (float) $attempt->manual_score,
                $attempt->score === null ? null : (float) $attempt->score,
            );
            $componentsBySubject[$subject->id] = ($componentsBySubject[$subject->id] ?? []) + $components;
            $results[$attempt->assignment->student_id][$subject->id] = [
                'components' => $components,
                'final' => $attempt->score === null ? null : (float) $attempt->score,
            ];
        }

        $rows = $students->map(function (Student $student) use ($subjects, $results): array {
            $studentResults = $results[$student->id] ?? [];
            $availableScores = $subjects->map(fn (AssessmentSubject $subject) => $studentResults[$subject->id]['final'] ?? null)
                ->filter(fn ($score) => $score !== null);

            return [
                'student' => $student,
                'results' => $studentResults,
                'average' => $availableScores->isEmpty() ? null : round($availableScores->avg(), 2),
                'complete' => $availableScores->count() === $subjects->count(),
                'rank' => null,
            ];
        });

        $ranked = $rows->filter(fn (array $row): bool => $row['complete'])
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

        $subjectGroups = $this->subjectGroups($subjects, $componentsBySubject);

        return view('reports.exam-results.print', [
            'period' => $assessmentPeriod->loadMissing('academicYear'),
            'schoolClass' => $schoolClass->loadMissing('homeroomTeacher'),
            'subjectGroups' => $subjectGroups,
            'subjectPages' => $this->subjectPages($subjectGroups),
            'rows' => $rows,
            'school' => SchoolProfile::query()->first(),
            'letterheadData' => $this->letterheadData(),
        ]);
    }

    private function subjectGroups(Collection $subjects, array $componentsBySubject): array
    {
        $labels = [
            ExamQuestionType::MultipleChoice->value => 'PG',
            ExamQuestionType::ShortAnswer->value => 'Isian Singkat',
            ExamQuestionType::Essay->value => 'Esai',
            ExamScoreCalculator::COMBINED_WRITTEN => 'Nilai Tertulis Gabungan',
        ];

        return $subjects->map(function (AssessmentSubject $subject) use ($labels, $componentsBySubject): array {
            $types = $subject->questions->map(fn ($question) => $question->question_type->value)->all();
            $columns = array_intersect_key($labels, array_fill_keys($types, true) + ($componentsBySubject[$subject->id] ?? []));
            $columns['final'] = 'Nilai Akhir';
            return ['subject' => $subject, 'columns' => $columns];
        })->all();
    }

    private function subjectPages(array $subjectGroups): array
    {
        $pages = [];
        $page = [];
        $columnCount = 0;
        foreach ($subjectGroups as $group) {
            $groupSize = count($group['columns']);
            if ($columnCount + $groupSize > 10) {
                $pages[] = $page;
                $page = [];
                $columnCount = 0;
            }
            $page[] = $group;
            $columnCount += $groupSize;
        }
        $pages[] = $page;

        return $pages;
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
