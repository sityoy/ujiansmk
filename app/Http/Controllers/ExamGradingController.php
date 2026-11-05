<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentType;
use App\Enums\AttemptStatus;
use App\Enums\ExamQuestionType;
use App\Enums\GradingStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\ExamAttempt;
use App\Models\SchoolClass;
use App\Services\Exams\ExamGradingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExamGradingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'assessment_period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'type' => ['nullable', Rule::enum(AssessmentType::class)],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'grading_status' => ['nullable', Rule::in([GradingStatus::Pending->value, GradingStatus::Graded->value])],
        ]);

        $attempts = ExamAttempt::query()
            ->with([
                'assignment.student',
                'assignment.assessmentSubject.subject',
                'assignment.assessmentSubject.schoolClass',
                'assignment.assessmentSubject.teacher',
                'assignment.assessmentSubject.assessmentPeriod.academicYear',
            ])
            ->where('status', AttemptStatus::Submitted)
            ->whereHas('assignment.assessmentSubject.questions', fn ($query) => $query
                ->whereIn('question_type', [ExamQuestionType::ShortAnswer->value, ExamQuestionType::Essay->value]))
            ->when($filters['academic_year_id'] ?? null, fn ($query, $year) => $query
                ->whereHas('assignment.assessmentSubject.assessmentPeriod', fn ($period) => $period->where('academic_year_id', $year)))
            ->when($filters['assessment_period_id'] ?? null, fn ($query, $period) => $query
                ->whereHas('assignment.assessmentSubject', fn ($component) => $component->where('assessment_period_id', $period)))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query
                ->whereHas('assignment.assessmentSubject.assessmentPeriod', fn ($period) => $period->where('type', $type)))
            ->when($filters['school_class_id'] ?? null, fn ($query, $class) => $query
                ->whereHas('assignment.assessmentSubject', fn ($component) => $component->where('school_class_id', $class)))
            ->when($filters['grading_status'] ?? null, fn ($query, $status) => $query->where('grading_status', $status));

        if ($request->user()->role === UserRole::Teacher) {
            $attempts->whereHas('assignment.assessmentSubject', fn ($query) => $query
                ->where('teacher_user_id', $request->user()->id));
        }

        $periodOptions = AssessmentPeriod::query()
            ->with('academicYear')
            ->when($request->user()->role === UserRole::Teacher, fn ($query) => $query
                ->whereHas('assessmentSubjects', fn ($component) => $component->where('teacher_user_id', $request->user()->id)))
            ->orderByDesc('starts_on')
            ->get();

        return view('grading.index', [
            'attempts' => $attempts->latest('submitted_at')->paginate(25)->withQueryString(),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'periodOptions' => $periodOptions,
            'classOptions' => SchoolClass::query()
                ->with('academicYear')
                ->when($request->user()->role === UserRole::Teacher, fn ($query) => $query
                    ->whereHas('assessmentSubjects', fn ($component) => $component->where('teacher_user_id', $request->user()->id)))
                ->orderBy('name')
                ->get(),
            'assessmentTypes' => AssessmentType::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, ExamAttempt $attempt): View
    {
        $this->authorizeGrader($request, $attempt);
        $attempt->load(['assignment.student', 'assignment.assessmentSubject.subject',
            'assignment.assessmentSubject.schoolClass', 'assignment.assessmentSubject.questions', 'answers']);

        return view('grading.show', [
            'attempt' => $attempt,
            'questions' => $attempt->assignment->assessmentSubject->questions
                ->filter(fn ($question) => $question->question_type->requiresManualGrading()),
            'answers' => $attempt->answers->keyBy('exam_question_id'),
        ]);
    }

    public function update(
        Request $request,
        ExamAttempt $attempt,
        ExamGradingService $grading,
    ): RedirectResponse {
        $this->authorizeGrader($request, $attempt);
        $validated = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['required', 'numeric', 'min:0'],
        ]);
        $grading->grade($attempt, $request->user(), $validated['scores']);

        return redirect()->route('grading.index')->with('status', 'Koreksi disimpan dan nilai akhir berhasil dihitung.');
    }

    private function authorizeGrader(Request $request, ExamAttempt $attempt): void
    {
        abort_unless($attempt->status === AttemptStatus::Submitted, 404);
        if ($request->user()->role === UserRole::Teacher) {
            $attempt->loadMissing('assignment.assessmentSubject');
            abort_unless($attempt->assignment->assessmentSubject->teacher_user_id === $request->user()->id, 403);
        }
    }
}
