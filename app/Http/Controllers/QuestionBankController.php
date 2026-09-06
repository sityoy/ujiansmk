<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentType;
use App\Enums\ExamQuestionType;
use App\Enums\UserRole;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\ExamQuestion;
use App\Models\SchoolClass;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionBankController extends Controller
{
    public function catalog(Request $request): View
    {
        $teacherId = $request->user()->role === UserRole::Teacher ? $request->user()->id : null;
        $periodId = $request->integer('period_id') ?: null;
        $classId = $request->integer('class_id') ?: null;
        $search = trim((string) $request->query('q'));
        $scopeTeacher = fn ($query) => $query->when(
            $teacherId,
            fn ($query) => $query->where('teacher_user_id', $teacherId),
        );

        return view('questions.catalog', [
            'components' => AssessmentSubject::query()
                ->with(['assessmentPeriod.academicYear', 'subject', 'schoolClass', 'teacher'])
                ->withCount('questions')
                ->withSum('questions', 'points')
                ->when($teacherId, fn ($query) => $query->where('teacher_user_id', $teacherId))
                ->when($periodId, fn ($query) => $query->where('assessment_period_id', $periodId))
                ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
                ->when($search, fn ($query) => $query->whereHas('subject', fn ($query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'periods' => AssessmentPeriod::query()
                ->with('academicYear')
                ->whereHas('assessmentSubjects', $scopeTeacher)
                ->orderByDesc('starts_on')
                ->get(),
            'classes' => SchoolClass::query()
                ->with('academicYear')
                ->whereHas('assessmentSubjects', $scopeTeacher)
                ->orderBy('name')
                ->get(),
            'periodId' => $periodId,
            'classId' => $classId,
            'search' => $search,
        ]);
    }

    public function index(Request $request, AssessmentSubject $assessmentSubject): View
    {
        $this->authorizeAccess($request, $assessmentSubject);
        $assessmentSubject->load([
            'assessmentPeriod.academicYear',
            'subject',
            'schoolClass',
            'questions',
        ]);

        $isLocked = $assessmentSubject->assignments()->whereHas('attempt')->exists();
        $questionTypes = $assessmentSubject->assessmentPeriod->type === AssessmentType::ATS
            ? [ExamQuestionType::ShortAnswer, ExamQuestionType::Essay]
            : ExamQuestionType::cases();

        return view('questions.index', compact('assessmentSubject', 'isLocked', 'questionTypes'));
    }

    public function store(Request $request, AssessmentSubject $assessmentSubject): RedirectResponse
    {
        $this->authorizeAccess($request, $assessmentSubject);
        $validated = $this->validateQuestion($request, $assessmentSubject);

        DB::transaction(function () use ($assessmentSubject, $validated): void {
            $this->assertEditable($assessmentSubject);
            $position = (int) ExamQuestion::query()
                ->where('assessment_subject_id', $assessmentSubject->id)
                ->lockForUpdate()
                ->max('position') + 1;

            $assessmentSubject->questions()->create([
                ...$this->questionAttributes($validated),
                'position' => $position,
            ]);
        });

        return back()->with('status', 'Soal berhasil ditambahkan. Jawaban isian dan esai akan dikoreksi manual.');
    }

    public function update(
        Request $request,
        AssessmentSubject $assessmentSubject,
        ExamQuestion $question,
    ): RedirectResponse {
        $this->authorizeAccess($request, $assessmentSubject);
        if ($question->assessment_subject_id !== $assessmentSubject->id) {
            abort(404);
        }

        $validated = $this->validateQuestion($request, $assessmentSubject);

        DB::transaction(function () use ($assessmentSubject, $question, $validated): void {
            $this->assertEditable($assessmentSubject);
            $editableQuestion = ExamQuestion::query()
                ->where('assessment_subject_id', $assessmentSubject->id)
                ->lockForUpdate()
                ->findOrFail($question->id);
            $editableQuestion->update($this->questionAttributes($validated));
        });

        return back()->with('status', 'Soal berhasil diperbarui.');
    }

    public function destroy(Request $request, AssessmentSubject $assessmentSubject, ExamQuestion $question): RedirectResponse
    {
        $this->authorizeAccess($request, $assessmentSubject);
        if ($question->assessment_subject_id !== $assessmentSubject->id) {
            abort(404);
        }

        if ($question->answers()->exists()) {
            throw ValidationException::withMessages([
                'question' => 'Soal tidak dapat dihapus karena sudah memiliki jawaban siswa.',
            ]);
        }

        try {
            DB::transaction(function () use ($assessmentSubject, $question): void {
                $this->assertEditable($assessmentSubject);
                $question->delete();
            });
        } catch (QueryException) {
            return back()->withErrors(['question' => 'Soal tidak dapat dihapus karena sudah digunakan.']);
        }

        return back()->with('status', 'Soal berhasil dihapus.');
    }

    private function assertEditable(AssessmentSubject $component): void
    {
        $component = AssessmentSubject::query()->lockForUpdate()->findOrFail($component->id);
        if ($component->assignments()->whereHas('attempt')->exists()) {
            throw ValidationException::withMessages([
                'question' => 'Bank soal terkunci karena sudah ada siswa yang mulai ujian, termasuk untuk sesi susulan.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function validateQuestion(Request $request, AssessmentSubject $component): array
    {
        $component->loadMissing('assessmentPeriod');
        $allowedTypes = $component->assessmentPeriod->type === AssessmentType::ATS
            ? [ExamQuestionType::ShortAnswer->value, ExamQuestionType::Essay->value]
            : array_map(fn (ExamQuestionType $type): string => $type->value, ExamQuestionType::cases());

        return $request->validate([
            'question_type' => ['required', Rule::in($allowedTypes)],
            'question_text' => ['required', 'string', 'max:5000'],
            'option_a' => ['required_if:question_type,multiple_choice', 'nullable', 'string', 'max:2000'],
            'option_b' => ['required_if:question_type,multiple_choice', 'nullable', 'string', 'max:2000'],
            'option_c' => ['required_if:question_type,multiple_choice', 'nullable', 'string', 'max:2000'],
            'option_d' => ['required_if:question_type,multiple_choice', 'nullable', 'string', 'max:2000'],
            'correct_answer' => ['required_if:question_type,multiple_choice', 'nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'points' => ['required', 'numeric', 'between:0.01,1000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function questionAttributes(array $validated): array
    {
        $isMultipleChoice = $validated['question_type'] === ExamQuestionType::MultipleChoice->value;

        return [
            'question_text' => trim($validated['question_text']),
            'question_type' => $validated['question_type'],
            'options' => $isMultipleChoice
                ? [
                    'A' => trim($validated['option_a']),
                    'B' => trim($validated['option_b']),
                    'C' => trim($validated['option_c']),
                    'D' => trim($validated['option_d']),
                ]
                : null,
            'correct_answer' => $isMultipleChoice ? $validated['correct_answer'] : null,
            'points' => $validated['points'],
        ];
    }

    private function authorizeAccess(Request $request, AssessmentSubject $component): void
    {
        if ($request->user()->role === UserRole::Teacher) {
            abort_unless($component->teacher_user_id === $request->user()->id, 403);
        }
    }
}
