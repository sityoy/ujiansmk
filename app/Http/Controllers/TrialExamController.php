<?php

namespace App\Http\Controllers;

use App\Enums\ExamQuestionType;
use App\Models\AssessmentSubject;
use App\Models\TrialExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrialExamController extends Controller
{
    public function index(): View
    {
        return view('tryouts.index', [
            'trialExams' => TrialExam::query()
                ->with(['assessmentSubject.assessmentPeriod.academicYear', 'assessmentSubject.subject', 'assessmentSubject.schoolClass'])
                ->withCount('attempts')
                ->withAvg('attempts', 'score')
                ->latest()
                ->paginate(10),
            'components' => AssessmentSubject::query()
                ->with(['assessmentPeriod.academicYear', 'subject', 'schoolClass'])
                ->withCount(['questions as pg_questions_count' => fn ($query) => $query
                    ->where('question_type', ExamQuestionType::MultipleChoice->value)])
                ->whereHas('questions', fn ($query) => $query
                    ->where('question_type', ExamQuestionType::MultipleChoice->value))
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['access_code' => Str::upper(trim((string) $request->input('access_code')))]);

        $validated = $request->validate([
            'assessment_subject_id' => ['required', 'integer', 'exists:assessment_subjects,id'],
            'title' => ['required', 'string', 'max:150'],
            'access_code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/', Rule::unique('trial_exams', 'access_code')],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:300'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $component = AssessmentSubject::query()
            ->whereKey($validated['assessment_subject_id'])
            ->whereHas('questions', fn ($query) => $query
                ->where('question_type', ExamQuestionType::MultipleChoice->value))
            ->firstOrFail();

        $slugBase = Str::slug($validated['title']) ?: 'uji-coba';
        $slug = $slugBase;
        while (TrialExam::query()->where('slug', $slug)->exists()) {
            $slug = $slugBase.'-'.Str::lower(Str::random(6));
        }

        TrialExam::create([
            ...$validated,
            'assessment_subject_id' => $component->id,
            'slug' => $slug,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Ujian uji coba dibuat. Tautan dan kode sudah dapat dibagikan.');
    }

    public function toggle(TrialExam $trialExam): RedirectResponse
    {
        $trialExam->update(['is_active' => ! $trialExam->is_active]);

        return back()->with('status', $trialExam->is_active
            ? 'Ujian uji coba diaktifkan.'
            : 'Ujian uji coba dinonaktifkan.');
    }

    public function destroy(TrialExam $trialExam): RedirectResponse
    {
        if ($trialExam->attempts()->exists()) {
            return back()->withErrors(['trial_exam' => 'Ujian uji coba yang sudah memiliki peserta tidak dapat dihapus. Nonaktifkan saja agar riwayat tetap tersimpan.']);
        }

        $trialExam->delete();

        return back()->with('status', 'Ujian uji coba dihapus.');
    }
}
