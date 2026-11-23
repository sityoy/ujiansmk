<?php

namespace App\Services\Exams;

use App\Enums\ExamQuestionType;
use App\Enums\UserRole;
use App\Models\TrialQuestionBank;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrialQuestionBankCatalog
{
    public function data(Request $request): array
    {
        $filters = $request->validate([
            'grade_level' => ['nullable', 'integer', Rule::in([10, 11, 12])],
            'q' => ['nullable', 'string', 'max:100'],
            'question_status' => ['nullable', Rule::in(['filled', 'empty'])],
        ]);
        $gradeLevel = isset($filters['grade_level']) ? (int) $filters['grade_level'] : null;
        $search = trim($filters['q'] ?? '');
        $questionStatus = $filters['question_status'] ?? null;
        $archived = $request->boolean('archived') && $request->user()->role !== UserRole::Teacher;
        $teacherId = $request->user()->role === UserRole::Teacher ? $request->user()->id : null;

        $base = TrialQuestionBank::query()
            ->when($archived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->when($teacherId, fn ($query) => $query->where('teacher_user_id', $teacherId))
            ->when($gradeLevel, fn ($query) => $query->where('grade_level', $gradeLevel))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhereHas('subject', fn ($query) => $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%'))
                ->orWhereHas('teacher', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))));
        $counted = (clone $base)->select('id')->withCount('questions')->get();
        $filled = $counted->where('questions_count', '>', 0)->count();
        $summary = [
            'banks' => $counted->count(),
            'filled' => $filled,
            'empty' => $counted->count() - $filled,
            'questions' => (int) $counted->sum('questions_count'),
        ];

        $query = $base->with(['subject', 'teacher'])->withCount([
            'questions', 'trialExams',
            'questions as pg_count' => fn ($questions) => $questions->where('question_type', ExamQuestionType::MultipleChoice),
            'questions as short_answer_count' => fn ($questions) => $questions->where('question_type', ExamQuestionType::ShortAnswer),
            'questions as essay_count' => fn ($questions) => $questions->where('question_type', ExamQuestionType::Essay),
        ])->withSum('questions', 'points')
            ->when($questionStatus === 'filled', fn ($banks) => $banks->has('questions'))
            ->when($questionStatus === 'empty', fn ($banks) => $banks->doesntHave('questions'));

        return compact('query', 'gradeLevel', 'search', 'questionStatus', 'archived', 'summary');
    }
}
