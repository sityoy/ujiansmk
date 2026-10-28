<?php

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Enums\ExamQuestionType;
use App\Enums\UserRole;
use App\Models\ExamAttempt;
use App\Services\Exams\ExamGradingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamGradingController extends Controller
{
    public function index(Request $request): View
    {
        $attempts = ExamAttempt::query()
            ->with(['assignment.student', 'assignment.assessmentSubject.subject',
                'assignment.assessmentSubject.schoolClass', 'assignment.assessmentSubject.teacher'])
            ->where('status', AttemptStatus::Submitted)
            ->whereHas('assignment.assessmentSubject.questions', fn ($query) => $query
                ->whereIn('question_type', [ExamQuestionType::ShortAnswer->value, ExamQuestionType::Essay->value]));
        if ($request->user()->role === UserRole::Teacher) {
            $attempts->whereHas('assignment.assessmentSubject', fn ($query) => $query
                ->where('teacher_user_id', $request->user()->id));
        }

        return view('grading.index', ['attempts' => $attempts->latest('submitted_at')->paginate(25)]);
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
