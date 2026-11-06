<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AssessmentSubject;
use App\Models\Subject;
use App\Models\TrialQuestion;
use App\Models\TrialQuestionBank;
use App\Services\Exams\ExamScoreCalculator;
use App\Services\Students\StudentSpreadsheetService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrialQuestionBankController extends Controller
{
    public function index(Request $request): View
    {
        $gradeLevel = in_array($request->integer('grade_level'), [10, 11, 12], true)
            ? $request->integer('grade_level')
            : null;
        $search = trim((string) $request->query('q'));
        $teacherId = $request->user()->role === UserRole::Teacher ? $request->user()->id : null;

        return view('trial-question-banks.catalog', [
            'banks' => TrialQuestionBank::query()
                ->with(['subject', 'teacher'])
                ->withCount(['questions', 'trialExams'])
                ->when($teacherId, fn ($query) => $query->where('teacher_user_id', $teacherId))
                ->when($gradeLevel, fn ($query) => $query->where('grade_level', $gradeLevel))
                ->when($search, fn ($query) => $query
                    ->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('subject', fn ($query) => $query
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('code', 'like', '%'.$search.'%'))))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'subjects' => Subject::query()
                ->where('is_active', true)
                ->whereHas('assessmentSubjects', fn ($query) => $query
                    ->when($teacherId, fn ($query) => $query->where('teacher_user_id', $teacherId)))
                ->orderBy('name')
                ->get(),
            'gradeLevels' => [10, 11, 12],
            'gradeLevel' => $gradeLevel,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_level' => ['required', 'integer', Rule::in([10, 11, 12])],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('trial_question_banks')->where(fn ($query) => $query
                    ->where('subject_id', $request->integer('subject_id'))
                    ->where('grade_level', $request->integer('grade_level'))),
            ],
        ]);

        $teacherId = $request->user()->role === UserRole::Teacher ? $request->user()->id : null;
        $eligible = AssessmentSubject::query()
            ->where('subject_id', $validated['subject_id'])
            ->whereHas('schoolClass', fn ($query) => $query->where('grade_level', $validated['grade_level']))
            ->when($teacherId, fn ($query) => $query->where('teacher_user_id', $teacherId))
            ->exists();

        if (! $eligible) {
            throw ValidationException::withMessages([
                'subject_id' => 'Mata pelajaran dan tingkat ini belum ditugaskan kepada Anda.',
            ]);
        }

        $bank = TrialQuestionBank::create([
            ...$validated,
            'name' => trim($validated['name']),
            'teacher_user_id' => $teacherId,
        ]);

        return redirect()->route('trial-question-banks.questions.index', $bank)
            ->with('status', 'Bank soal uji coba dibuat. Silakan tambahkan soal pilihan ganda.');
    }

    public function questions(Request $request, TrialQuestionBank $trialQuestionBank): View
    {
        $this->authorizeBank($request, $trialQuestionBank);
        $trialQuestionBank->load(['subject', 'teacher']);

        return view('trial-question-banks.questions', [
            'bank' => $trialQuestionBank,
            'questions' => $trialQuestionBank->questions()->paginate(20)->withQueryString(),
            'questionCount' => $trialQuestionBank->questions()->count(),
            'totalPoints' => (float) $trialQuestionBank->questions()->sum('points'),
            'isLocked' => $this->isLocked($trialQuestionBank),
        ]);
    }

    public function export(
        Request $request,
        TrialQuestionBank $trialQuestionBank,
        StudentSpreadsheetService $spreadsheet,
        ExamScoreCalculator $scores,
    ): BinaryFileResponse {
        $this->authorizeBank($request, $trialQuestionBank);
        $trialQuestionBank->load('subject');
        $questions = $trialQuestionBank->questions()->get();
        $weight = $scores->multipleChoiceWeight($questions->count());
        $rows = $questions->values()->map(fn (TrialQuestion $question, int $index): array => [
            $index + 1,
            $question->question_text,
            $question->options['A'] ?? '',
            $question->options['B'] ?? '',
            $question->options['C'] ?? '',
            $question->options['D'] ?? '',
            $question->options['E'] ?? '',
            $question->correct_answer,
            number_format($weight, 4, '.', ''),
            $question->question_image_path ? Storage::url($question->question_image_path) : '',
        ])->all();

        $path = $spreadsheet->createWorkbook(
            ['No', 'Pertanyaan', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D', 'Pilihan E', 'Kunci', 'Bobot PG', 'Gambar'],
            $rows,
            'PG Uji Coba',
        );
        $filename = 'bank-soal-pg-uji-coba-'.Str::slug($trialQuestionBank->subject->name)
            .'-kelas-'.$trialQuestionBank->grade_level
            .'-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function storeQuestion(Request $request, TrialQuestionBank $trialQuestionBank): RedirectResponse
    {
        $this->authorizeBank($request, $trialQuestionBank);
        $validated = $this->validateQuestion($request);

        DB::transaction(function () use ($request, $trialQuestionBank, $validated): void {
            $this->assertEditable($trialQuestionBank);
            $position = (int) $trialQuestionBank->questions()->lockForUpdate()->max('position') + 1;
            $trialQuestionBank->questions()->create([
                ...$this->attributes($validated),
                'question_image_path' => $request->file('question_image')?->store('trial-questions', 'public'),
                'position' => $position,
            ]);
        });

        return back()->with('status', 'Soal uji coba berhasil ditambahkan.');
    }

    public function updateQuestion(
        Request $request,
        TrialQuestionBank $trialQuestionBank,
        TrialQuestion $question,
    ): RedirectResponse {
        $this->authorizeBank($request, $trialQuestionBank);
        abort_unless($question->trial_question_bank_id === $trialQuestionBank->id, 404);
        $validated = $this->validateQuestion($request);

        DB::transaction(function () use ($request, $trialQuestionBank, $question, $validated): void {
            $this->assertEditable($trialQuestionBank);
            $attributes = $this->attributes($validated);
            if ($request->hasFile('question_image')) {
                $oldImage = $question->question_image_path;
                $attributes['question_image_path'] = $request->file('question_image')->store('trial-questions', 'public');
                $question->update($attributes);
                if ($oldImage) {
                    Storage::disk('public')->delete($oldImage);
                }

                return;
            }
            $question->update($attributes);
        });

        return back()->with('status', 'Soal uji coba berhasil diperbarui.');
    }

    public function destroyQuestion(
        Request $request,
        TrialQuestionBank $trialQuestionBank,
        TrialQuestion $question,
    ): RedirectResponse {
        $this->authorizeBank($request, $trialQuestionBank);
        abort_unless($question->trial_question_bank_id === $trialQuestionBank->id, 404);
        if ($question->answers()->exists()) {
            throw ValidationException::withMessages([
                'question' => 'Soal tidak dapat dihapus karena sudah memiliki jawaban peserta.',
            ]);
        }

        try {
            DB::transaction(function () use ($trialQuestionBank, $question): void {
                $this->assertEditable($trialQuestionBank);
                $imagePath = $question->question_image_path;
                $question->delete();
                if ($imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
                $trialQuestionBank->questions()->lockForUpdate()->get()
                    ->each(function (TrialQuestion $remaining, int $index): void {
                        if ((int) $remaining->position !== $index + 1) {
                            $remaining->update(['position' => $index + 1]);
                        }
                    });
            });
        } catch (QueryException) {
            return back()->withErrors(['question' => 'Soal tidak dapat dihapus karena sudah digunakan.']);
        }

        return back()->with('status', 'Soal dihapus dan nomor soal telah dirapikan.');
    }

    public function destroy(Request $request, TrialQuestionBank $trialQuestionBank): RedirectResponse
    {
        $this->authorizeBank($request, $trialQuestionBank);
        if ($trialQuestionBank->trialExams()->exists()) {
            return back()->withErrors(['bank' => 'Bank soal sudah dipakai ujian uji coba dan tidak dapat dihapus.']);
        }

        $paths = $trialQuestionBank->questions()->pluck('question_image_path')->filter();
        $trialQuestionBank->delete();
        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }

        return back()->with('status', 'Bank soal uji coba berhasil dihapus.');
    }

    private function validateQuestion(Request $request): array
    {
        return $request->validate([
            'question_text' => ['required', 'string', 'max:5000'],
            'option_a' => ['required', 'string', 'max:2000'],
            'option_b' => ['required', 'string', 'max:2000'],
            'option_c' => ['required', 'string', 'max:2000'],
            'option_d' => ['required', 'string', 'max:2000'],
            'option_e' => ['required', 'string', 'max:2000'],
            'correct_answer' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'question_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'points' => ['required', 'numeric', 'between:0.01,1000'],
        ]);
    }

    private function attributes(array $validated): array
    {
        return [
            'question_text' => trim($validated['question_text']),
            'options' => [
                'A' => trim($validated['option_a']),
                'B' => trim($validated['option_b']),
                'C' => trim($validated['option_c']),
                'D' => trim($validated['option_d']),
                'E' => trim($validated['option_e']),
            ],
            'correct_answer' => $validated['correct_answer'],
            'points' => $validated['points'],
        ];
    }

    private function authorizeBank(Request $request, TrialQuestionBank $bank): void
    {
        if ($request->user()->role === UserRole::Teacher) {
            abort_unless($bank->teacher_user_id === $request->user()->id, 403);
        }
    }

    private function isLocked(TrialQuestionBank $bank): bool
    {
        return $bank->trialExams()->whereHas('attempts')->exists();
    }

    private function assertEditable(TrialQuestionBank $bank): void
    {
        $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($bank->id);
        if ($this->isLocked($bank)) {
            throw ValidationException::withMessages([
                'question' => 'Bank soal uji coba terkunci karena sudah ada peserta yang mulai ujian.',
            ]);
        }
    }
}

