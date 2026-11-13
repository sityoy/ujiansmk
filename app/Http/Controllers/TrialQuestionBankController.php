<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AssessmentSubject;
use App\Models\Subject;
use App\Models\TrialQuestion;
use App\Models\TrialQuestionBank;
use App\Services\Exams\ExamScoreCalculator;
use App\Services\Exams\QuestionText;
use App\Services\Exams\TrialQuestionBankArchive;
use App\Services\Exams\TrialQuestionPack;
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
    public function __construct(private readonly QuestionText $questionText) {}

    public function index(Request $request, TrialQuestionPack $pack): View
    {
        $gradeLevel = in_array($request->integer('grade_level'), [10, 11, 12], true)
            ? $request->integer('grade_level')
            : null;
        $search = trim((string) $request->query('q'));
        $archived = $request->boolean('archived');
        $packGrade = in_array($request->integer('pack_grade_level', 10), [10, 11, 12], true)
            ? $request->integer('pack_grade_level', 10) : null;
        $packSearch = trim((string) $request->query('pack_search'));
        $teacherId = $request->user()->role === UserRole::Teacher ? $request->user()->id : null;

        return view('trial-question-banks.catalog', [
            'banks' => TrialQuestionBank::query()
                ->with(['subject', 'teacher'])
                ->withCount(['questions', 'trialExams'])
                ->when($archived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
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
            'archived' => $archived,
            'packs' => $pack->menu($packGrade, $packSearch),
            'packGrade' => $packGrade,
            'packSearch' => $packSearch,
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
            $this->questionText->plainText($question->question_text, $question->text_format),
            $this->questionText->plainText($question->options['A'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['B'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['C'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['D'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['E'] ?? '', $question->text_format),
            $question->correct_answer,
            number_format($weight, 4, '.', ''),
            $question->question_image_path ? Storage::url($question->question_image_path) : '',
            $this->questionText->plainText($question->statement, $question->text_format),
        ])->all();

        $path = $spreadsheet->createWorkbook(
            ['No', 'Pertanyaan', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D', 'Pilihan E', 'Kunci', 'Bobot PG', 'Gambar', 'Pernyataan'],
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
            $optionFilePaths = $this->storeOptionFiles($request, 'trial-options');
            $trialQuestionBank->questions()->create([
                ...$this->attributes($validated),
                'question_image_path' => $request->file('question_image')?->store('trial-questions', 'public'),
                'option_file_paths' => $optionFilePaths ?: null,
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
            $oldOptionPaths = $question->option_file_paths ?? [];
            $attributes['option_file_paths'] = $this->mergeOptionFiles(
                $request,
                $oldOptionPaths,
                'trial-options',
            ) ?: null;
            if ($request->hasFile('question_image')) {
                $oldImage = $question->question_image_path;
                $attributes['question_image_path'] = $request->file('question_image')->store('trial-questions', 'public');
                $question->update($attributes);
                if ($oldImage) {
                    Storage::disk('public')->delete($oldImage);
                }
                $this->deleteUnusedOptionFiles($oldOptionPaths);

                return;
            }
            $question->update($attributes);
            $this->deleteUnusedOptionFiles($oldOptionPaths);
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
                $optionPaths = $question->option_file_paths ?? [];
                $question->delete();
                if ($imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
                $this->deleteUnusedOptionFiles($optionPaths);
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

    public function importPack(Request $request, TrialQuestionPack $pack): RedirectResponse
    {
        $validated = $request->validate([
            'pack_key' => ['required', 'string', Rule::in(array_column($pack->sets(), 'id'))],
        ]);
        $result = $pack->importSelected($validated['pack_key']);
        $bank = $result['bank'];
        if ($bank->archived_at) {
            return redirect()->route('trial-question-banks.index', ['archived' => 1])
                ->with('status', 'Paket ini pernah ditambahkan lalu dihapus. Gunakan Pulihkan untuk mengembalikannya.');
        }

        return redirect()->route('trial-question-banks.questions.index', $bank)->with('status', $result['created']
            ? $bank->name.' ditambahkan: 20 soal dari mudah ke sulit beserta kunci jawaban.'
            : 'Paket sudah tersedia. Soal yang ada tetap tersimpan tanpa ditimpa atau digandakan.');
    }

    public function destroy(Request $request, TrialQuestionBank $trialQuestionBank, TrialQuestionBankArchive $archive): RedirectResponse
    {
        $this->authorizeBank($request, $trialQuestionBank);
        $archive->archive($trialQuestionBank);

        return back()->with('status', 'Bank dihapus dari daftar aktif dan tautan uji cobanya dinonaktifkan. Soal serta riwayat peserta tersimpan di bagian Dihapus.');
    }

    public function restore(Request $request, TrialQuestionBank $trialQuestionBank, TrialQuestionBankArchive $archive): RedirectResponse
    {
        $this->authorizeBank($request, $trialQuestionBank);
        $archive->restore($trialQuestionBank);

        return back()->with('status', 'Bank soal dipulihkan. Aktifkan kembali ujian uji coba secara manual jika diperlukan.');
    }

    private function validateQuestion(Request $request): array
    {
        $validated = $request->validate([
            'text_format' => ['nullable', Rule::in(['plain', 'html'])],
            'question_text' => ['required', 'string', 'max:20000'],
            'statement' => ['nullable', 'string', 'max:20000'],
            'option_a' => ['required', 'string', 'max:20000'],
            'option_b' => ['required', 'string', 'max:20000'],
            'option_c' => ['required', 'string', 'max:20000'],
            'option_d' => ['required', 'string', 'max:20000'],
            'option_e' => ['required', 'string', 'max:20000'],
            'correct_answer' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'question_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_a' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_b' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_c' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_d' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_e' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        return $this->questionText->normalize($validated);
    }

    private function attributes(array $validated): array
    {
        return [
            'question_text' => trim($validated['question_text']),
            'text_format' => $validated['text_format'],
            'statement' => $validated['statement'] ?? null,
            'options' => [
                'A' => trim($validated['option_a']),
                'B' => trim($validated['option_b']),
                'C' => trim($validated['option_c']),
                'D' => trim($validated['option_d']),
                'E' => trim($validated['option_e']),
            ],
            'correct_answer' => $validated['correct_answer'],
            'points' => ExamScoreCalculator::MULTIPLE_CHOICE_POINTS,
        ];
    }

    private function storeOptionFiles(Request $request, string $directory): array
    {
        $paths = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $option) {
            $file = $request->file('option_file_'.strtolower($option));
            if ($file) {
                $paths[$option] = $file->store($directory, 'public');
            }
        }

        return $paths;
    }

    private function mergeOptionFiles(Request $request, array $existing, string $directory): array
    {
        return array_replace($existing, $this->storeOptionFiles($request, $directory));
    }

    private function deleteUnusedOptionFiles(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            $inUse = TrialQuestion::query()->whereNotNull('option_file_paths')->get(['option_file_paths'])
                ->contains(fn (TrialQuestion $question): bool => in_array($path, $question->option_file_paths ?? [], true));
            if (! $inUse) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function authorizeBank(Request $request, TrialQuestionBank $bank): void
    {
        if ($request->user()->role === UserRole::Teacher) {
            abort_unless($bank->teacher_user_id === $request->user()->id, 403);
        }
    }

    private function isLocked(TrialQuestionBank $bank): bool
    {
        return $bank->archived_at !== null || $bank->trialExams()->whereHas('attempts')->exists();
    }

    private function assertEditable(TrialQuestionBank $bank): void
    {
        $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($bank->id);
        if ($this->isLocked($bank)) {
            throw ValidationException::withMessages([
                'question' => 'Bank soal terkunci karena sudah dihapus dari daftar aktif atau sudah memiliki peserta ujian.',
            ]);
        }
    }
}
