<?php

namespace App\Services\Exams;

use App\Enums\ExamQuestionType;
use App\Enums\UserRole;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use Illuminate\Http\Request;

class CoreQuestionBankCatalog
{
    public function data(Request $request): array
    {
        $teacherId = $request->user()->role === UserRole::Teacher ? $request->user()->id : null;
        $periodId = $request->integer('period_id') ?: null;
        $gradeLevel = in_array($request->integer('grade_level'), [10, 11, 12], true)
            ? $request->integer('grade_level')
            : null;
        $search = trim((string) $request->query('q'));
        $catalogView = $request->query('view') === 'summary' ? 'summary' : 'banks';
        $questionStatus = in_array($request->query('question_status'), ['filled', 'empty'], true)
            ? $request->query('question_status') : null;
        $scopeTeacher = fn ($query) => $query->when(
            $teacherId,
            fn ($query) => $query->where('teacher_user_id', $teacherId),
        );

        $grouped = AssessmentSubject::query()
            ->with(['assessmentPeriod.academicYear', 'subject', 'schoolClass', 'teacher'])
            ->withCount([
                'questions',
                'questions as pg_count' => fn ($query) => $query->where('question_type', ExamQuestionType::MultipleChoice->value),
                'questions as essay_count' => fn ($query) => $query->where('question_type', ExamQuestionType::Essay->value),
                'questions as short_answer_count' => fn ($query) => $query->where('question_type', ExamQuestionType::ShortAnswer->value),
            ])
            ->withSum('questions', 'points')
            ->when($teacherId, fn ($query) => $query->where('teacher_user_id', $teacherId))
            ->when($periodId, fn ($query) => $query->where('assessment_period_id', $periodId))
            ->when($gradeLevel, fn ($query) => $query->whereHas('schoolClass', fn ($query) => $query
                ->where('grade_level', $gradeLevel)))
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->whereHas('subject', fn ($query) => $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%'))
                ->orWhereHas('teacher', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))))
            ->latest()
            ->get()
            ->groupBy(fn (AssessmentSubject $component): string => implode('|', [
                $component->assessment_period_id,
                $component->subject_id,
                $component->schoolClass->grade_level,
                $component->teacher_user_id ?? 'rombel-'.$component->id,
            ]))
            ->map(function ($components): AssessmentSubject {
                $representative = $components->sortByDesc('questions_count')->first();
                $representative->setAttribute('grade_level', $representative->schoolClass->grade_level);
                $representative->setAttribute('rombel_names', $components->pluck('schoolClass.name')->sort(SORT_NATURAL)->join(', '));

                return $representative;
            })
            ->sortByDesc('id')
            ->values();

        // Count each shared bank once, before applying the filled/empty tab filter.
        $summary = [
            'banks' => $grouped->count(),
            'filled' => $grouped->where('questions_count', '>', 0)->count(),
            'empty' => $grouped->where('questions_count', 0)->count(),
            'questions' => (int) $grouped->sum('questions_count'),
        ];
        $grouped = $grouped->filter(fn (AssessmentSubject $component): bool => match ($questionStatus) {
            'filled' => $component->questions_count > 0,
            'empty' => (int) $component->questions_count === 0,
            default => true,
        })->values();

        return [
            'grouped' => $grouped,
            'catalogView' => $catalogView,
            'questionStatus' => $questionStatus,
            'summary' => $summary,
            'periods' => AssessmentPeriod::query()->with('academicYear')->whereHas('assessmentSubjects', $scopeTeacher)->orderByDesc('starts_on')->get(),
            'gradeLevels' => [10, 11, 12],
            'periodId' => $periodId,
            'gradeLevel' => $gradeLevel,
            'search' => $search,
        ];
    }
}
