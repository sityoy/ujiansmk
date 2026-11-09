<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentType;
use App\Enums\ExamQuestionType;
use App\Enums\UserRole;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\ExamQuestion;
use App\Services\Exams\ExamScoreCalculator;
use App\Services\Students\StudentSpreadsheetService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionBankController extends Controller
{
    public function catalog(Request $request): View
    {
        $teacherId = $request->user()->role === UserRole::Teacher ? $request->user()->id : null;
        $periodId = $request->integer('period_id') ?: null;
        $gradeLevel = in_array($request->integer('grade_level'), [10, 11, 12], true)
            ? $request->integer('grade_level')
            : null;
        $search = trim((string) $request->query('q'));
        $scopeTeacher = fn ($query) => $query->when(
            $teacherId,
            fn ($query) => $query->where('teacher_user_id', $teacherId),
        );

        $grouped = AssessmentSubject::query()
            ->with(['assessmentPeriod.academicYear', 'subject', 'schoolClass', 'teacher'])
            ->withCount('questions')
            ->withSum('questions', 'points')
            ->when($teacherId, fn ($query) => $query->where('teacher_user_id', $teacherId))
            ->when($periodId, fn ($query) => $query->where('assessment_period_id', $periodId))
            ->when($gradeLevel, fn ($query) => $query->whereHas('schoolClass', fn ($query) => $query
                ->where('grade_level', $gradeLevel)))
            ->when($search, fn ($query) => $query->whereHas('subject', fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('code', 'like', '%'.$search.'%')))
            ->latest()
            ->get()
            ->groupBy(fn (AssessmentSubject $component): string => implode('|', [
                $component->assessment_period_id,
                $component->subject_id,
                $component->schoolClass->grade_level,
            ]))
            ->map(function ($components): AssessmentSubject {
                $representative = $components->sortByDesc('questions_count')->first();
                $representative->setAttribute('grade_level', $representative->schoolClass->grade_level);
                $representative->setAttribute('rombel_count', $components->count());

                return $representative;
            })
            ->sortByDesc('id')
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 12;
        $components = new LengthAwarePaginator(
            $grouped->forPage($page, $perPage)->values(),
            $grouped->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('questions.catalog', [
            'components' => $components,
            'periods' => AssessmentPeriod::query()
                ->with('academicYear')
                ->whereHas('assessmentSubjects', $scopeTeacher)
                ->orderByDesc('starts_on')
                ->get(),
            'gradeLevels' => [10, 11, 12],
            'periodId' => $periodId,
            'gradeLevel' => $gradeLevel,
            'search' => $search,
        ]);
    }

    public function index(Request $request, AssessmentSubject $assessmentSubject): View
    {
        $siblings = $this->authorizeAccess($request, $assessmentSubject);
        $assessmentSubject->load([
            'assessmentPeriod.academicYear',
            'subject',
            'schoolClass',
        ]);
        $questions = $assessmentSubject->questions()
            ->orderBy('position')
            ->paginate(20)
            ->withQueryString();
        $questionCount = $assessmentSubject->questions()->count();
        $totalPoints = (float) $assessmentSubject->questions()->sum('points');
        $isLocked = $this->isLocked($siblings);
        $questionTypes = $assessmentSubject->assessmentPeriod->type === AssessmentType::ATS
            ? [ExamQuestionType::ShortAnswer, ExamQuestionType::Essay]
            : ExamQuestionType::cases();
        $gradeLevel = (int) $assessmentSubject->schoolClass->grade_level;

        return view('questions.index', compact(
            'assessmentSubject', 'questions', 'questionCount', 'totalPoints',
            'isLocked', 'questionTypes', 'gradeLevel'
        ));
    }

    public function export(
        Request $request,
        AssessmentSubject $assessmentSubject,
        StudentSpreadsheetService $spreadsheet,
        ExamScoreCalculator $scores,
    ): BinaryFileResponse {
        $this->authorizeAccess($request, $assessmentSubject);
        $assessmentSubject->load(['assessmentPeriod.academicYear', 'subject', 'schoolClass']);
        $questions = $assessmentSubject->questions()
            ->where('question_type', ExamQuestionType::MultipleChoice->value)
            ->orderBy('position')
            ->get();
        $weight = $scores->multipleChoiceWeight($questions->count());
        $rows = $questions->values()->map(fn (ExamQuestion $question, int $index): array => [
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
            'PG Ujian Inti',
        );
        $filename = 'bank-soal-pg-inti-'.Str::slug($assessmentSubject->subject->name)
            .'-kelas-'.$assessmentSubject->schoolClass->grade_level
            .'-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function store(Request $request, AssessmentSubject $assessmentSubject): RedirectResponse
    {
        $siblings = $this->authorizeAccess($request, $assessmentSubject);
        $validated = $this->validateQuestion($request, $assessmentSubject);

        DB::transaction(function () use ($request, $siblings, $validated): void {
            $this->assertEditable($siblings);
            $imagePath = $request->file('question_image')?->store('exam-questions', 'public');
            $optionFilePaths = $this->storeOptionFiles($request, 'exam-options');
            $position = (int) ExamQuestion::query()
                ->whereIn('assessment_subject_id', $siblings->modelKeys())
                ->lockForUpdate()
                ->max('position') + 1;

            foreach ($siblings as $component) {
                $component->questions()->create([
                    ...$this->questionAttributes($validated),
                    'question_image_path' => $imagePath,
                    'option_file_paths' => $optionFilePaths ?: null,
                    'position' => $position,
                ]);
            }
        });

        return back()->with('status', 'Soal berhasil ditambahkan untuk seluruh rombel pada tingkat ini.');
    }

    public function update(
        Request $request,
        AssessmentSubject $assessmentSubject,
        ExamQuestion $question,
    ): RedirectResponse {
        $siblings = $this->authorizeAccess($request, $assessmentSubject);
        if ($question->assessment_subject_id !== $assessmentSubject->id) {
            abort(404);
        }
        $validated = $this->validateQuestion($request, $assessmentSubject);

        DB::transaction(function () use ($request, $siblings, $question, $validated): void {
            $this->assertEditable($siblings);
            $attributes = $this->questionAttributes($validated);
            $oldOptionPaths = collect($question->option_file_paths ?? [])->filter()->values()->all();
            $attributes['option_file_paths'] = $validated['question_type'] === ExamQuestionType::MultipleChoice->value
                ? ($this->mergeOptionFiles($request, $question->option_file_paths ?? [], 'exam-options') ?: null)
                : null;
            $newImagePath = null;
            if ($request->hasFile('question_image')) {
                $newImagePath = $request->file('question_image')->store('exam-questions', 'public');
                $attributes['question_image_path'] = $newImagePath;
            }

            $oldImagePaths = [];
            foreach ($siblings as $component) {
                $editable = ExamQuestion::query()
                    ->where('assessment_subject_id', $component->id)
                    ->where('position', $question->position)
                    ->lockForUpdate()
                    ->first();

                if ($editable?->question_image_path) {
                    $oldImagePaths[] = $editable->question_image_path;
                }
                if ($editable) {
                    $editable->update($attributes);
                } else {
                    $component->questions()->create([
                        ...$attributes,
                        'question_image_path' => $newImagePath ?? $question->question_image_path,
                        'position' => $question->position,
                    ]);
                }
            }

            if ($newImagePath) {
                foreach (array_unique($oldImagePaths) as $oldImagePath) {
                    if ($oldImagePath !== $newImagePath
                        && ! ExamQuestion::query()->where('question_image_path', $oldImagePath)->exists()) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                }
            }
            $this->deleteUnusedOptionFiles($oldOptionPaths);
        });

        return back()->with('status', 'Soal berhasil diperbarui untuk seluruh rombel pada tingkat ini.');
    }

    public function destroy(Request $request, AssessmentSubject $assessmentSubject, ExamQuestion $question): RedirectResponse
    {
        $siblings = $this->authorizeAccess($request, $assessmentSubject);
        if ($question->assessment_subject_id !== $assessmentSubject->id) {
            abort(404);
        }

        $matching = ExamQuestion::query()
            ->whereIn('assessment_subject_id', $siblings->modelKeys())
            ->where('position', $question->position);
        if ((clone $matching)->whereHas('answers')->exists()) {
            throw ValidationException::withMessages([
                'question' => 'Soal tidak dapat dihapus karena sudah memiliki jawaban siswa.',
            ]);
        }

        try {
            DB::transaction(function () use ($siblings, $matching): void {
                $this->assertEditable($siblings);
                $matchedQuestions = (clone $matching)->get();
                $imagePaths = $matchedQuestions->pluck('question_image_path')->filter()->unique();
                $optionPaths = $matchedQuestions->flatMap(fn (ExamQuestion $item) => $item->option_file_paths ?? [])->filter()->unique();
                (clone $matching)->delete();

                foreach ($siblings as $component) {
                    ExamQuestion::query()
                        ->where('assessment_subject_id', $component->id)
                        ->orderBy('position')
                        ->lockForUpdate()
                        ->get()
                        ->each(function (ExamQuestion $remaining, int $index): void {
                            if ((int) $remaining->position !== $index + 1) {
                                $remaining->update(['position' => $index + 1]);
                            }
                        });
                }

                foreach ($imagePaths as $imagePath) {
                    if (! ExamQuestion::query()->where('question_image_path', $imagePath)->exists()) {
                        Storage::disk('public')->delete($imagePath);
                    }
                }
                $this->deleteUnusedOptionFiles($optionPaths->all());
            });
        } catch (QueryException) {
            return back()->withErrors(['question' => 'Soal tidak dapat dihapus karena sudah digunakan.']);
        }

        return back()->with('status', 'Soal berhasil dihapus dan nomor soal telah dirapikan.');
    }

    private function assertEditable(EloquentCollection $siblings): void
    {
        $locked = AssessmentSubject::query()->whereKey($siblings->modelKeys())
            ->lockForUpdate()
            ->whereHas('assignments.attempt')
            ->exists();
        if ($locked) {
            throw ValidationException::withMessages([
                'question' => 'Bank soal terkunci karena sudah ada siswa pada tingkat ini yang mulai ujian.',
            ]);
        }
    }

    private function isLocked(EloquentCollection $siblings): bool
    {
        return AssessmentSubject::query()->whereKey($siblings->modelKeys())
            ->whereHas('assignments.attempt')
            ->exists();
    }

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
            'option_e' => ['required_if:question_type,multiple_choice', 'nullable', 'string', 'max:2000'],
            'correct_answer' => ['required_if:question_type,multiple_choice', 'nullable', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'question_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_a' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_b' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_c' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_d' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_e' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'points' => ['required_unless:question_type,multiple_choice', 'nullable', 'numeric', 'between:0.01,1000'],
        ]);
    }

    private function questionAttributes(array $validated): array
    {
        $isMultipleChoice = $validated['question_type'] === ExamQuestionType::MultipleChoice->value;

        return [
            'question_text' => trim($validated['question_text']),
            'question_type' => $validated['question_type'],
            'options' => $isMultipleChoice ? [
                'A' => trim($validated['option_a']),
                'B' => trim($validated['option_b']),
                'C' => trim($validated['option_c']),
                'D' => trim($validated['option_d']),
                'E' => trim($validated['option_e']),
            ] : null,
            'correct_answer' => $isMultipleChoice ? $validated['correct_answer'] : null,
            'points' => $isMultipleChoice ? ExamScoreCalculator::MULTIPLE_CHOICE_POINTS : $validated['points'],
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
        $paths = $existing;
        foreach ($this->storeOptionFiles($request, $directory) as $option => $path) {
            $paths[$option] = $path;
        }

        return array_filter($paths);
    }

    private function deleteUnusedOptionFiles(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            $inUse = ExamQuestion::query()->whereNotNull('option_file_paths')->get(['option_file_paths'])
                ->contains(fn (ExamQuestion $question): bool => in_array($path, $question->option_file_paths ?? [], true));
            if (! $inUse) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function authorizeAccess(Request $request, AssessmentSubject $component): EloquentCollection
    {
        $siblings = $this->siblings($component);
        if ($request->user()->role === UserRole::Teacher) {
            abort_unless($siblings->contains('teacher_user_id', $request->user()->id), 403);
        }

        return $siblings;
    }

    private function siblings(AssessmentSubject $component): EloquentCollection
    {
        $component->loadMissing('schoolClass');

        return AssessmentSubject::query()
            ->where('assessment_period_id', $component->assessment_period_id)
            ->where('subject_id', $component->subject_id)
            ->whereHas('schoolClass', fn ($query) => $query
                ->where('grade_level', $component->schoolClass->grade_level))
            ->get();
    }
}

