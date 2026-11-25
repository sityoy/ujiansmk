<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentType;
use App\Enums\ExamQuestionType;
use App\Enums\UserRole;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\ExamQuestion;
use App\Models\SchoolProfile;
use App\Services\Exams\CoreQuestionBank;
use App\Services\Exams\CoreQuestionBankCatalog;
use App\Services\Exams\ExamScoreCalculator;
use App\Services\Exams\MultipleChoiceAnswers;
use App\Services\Exams\QuestionPdfImport;
use App\Services\Exams\QuestionText;
use App\Services\Students\StudentSpreadsheetService;
use App\Support\QuestionBankNavigation;
use App\Support\TablePagination;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionBankController extends Controller
{
    public function __construct(private readonly CoreQuestionBank $banks, private readonly QuestionText $questionText) {}

    public function catalog(Request $request, CoreQuestionBankCatalog $catalog): View
    {
        $data = $catalog->data($request);

        return view('questions.catalog', [
            ...$data,
            'components' => TablePagination::collection($data['grouped']),
            'shareMessages' => $data['catalogView'] === 'summary' ? $this->shareMessages($data) : [],
        ]);
    }

    public function updateDeadline(Request $request, AssessmentPeriod $assessmentPeriod): RedirectResponse
    {
        $validated = $request->validateWithBag('questionDeadline', [
            'core_question_deadline_at' => ['present', 'nullable', 'date_format:Y-m-d\TH:i'],
        ], [
            'core_question_deadline_at.date_format' => 'Isi tanggal dan jam batas pembuatan soal dengan benar.',
        ]);
        $value = $validated['core_question_deadline_at'];
        $deadline = $value === null ? null : Carbon::createFromFormat('!Y-m-d\TH:i', $value, AssessmentPeriod::CORE_QUESTION_TIMEZONE)
            ->setTimezone(config('app.timezone'));

        DB::transaction(function () use ($assessmentPeriod, $deadline): void {
            $period = AssessmentPeriod::query()->lockForUpdate()->findOrFail($assessmentPeriod->id);
            $period->core_question_deadline_at = $deadline;
            $period->save();
        });

        return back()->with('status', $deadline
            ? 'Batas pembuatan soal untuk periode ini berhasil disimpan.'
            : 'Batas pembuatan soal untuk periode ini berhasil dihapus.');
    }

    public function recap(Request $request, CoreQuestionBankCatalog $catalog): Response
    {
        $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'grade_level' => ['nullable', Rule::in([10, 11, 12])],
            'q' => ['nullable', 'string', 'max:100'],
            'question_status' => ['nullable', Rule::in(['filled', 'empty'])],
        ]);
        $data = $catalog->data($request);
        $selectedPeriod = $data['periods']->firstWhere('id', $data['periodId']);
        if ($request->user()->role === UserRole::Teacher && $data['periodId']) {
            abort_unless($selectedPeriod, 403);
        }
        $groups = $data['grouped']->sortBy(fn ($bank) => sprintf('%02d|%010d|%s|%s', $bank->grade_level, $bank->assessment_period_id, $bank->subject->name, $bank->teacher?->name ?? ''))
            ->groupBy(fn ($bank) => $bank->grade_level.'|'.$bank->assessment_period_id)
            ->map(function ($banks): array {
                $first = $banks->first();
                $period = $first->assessmentPeriod;

                return [
                    'title' => 'Kelas '.$first->grade_level.' · '.$period->name,
                    'subtitle' => 'Tahun ajaran '.$period->academicYear->name,
                    'deadline' => $this->recapDeadline($period),
                    'rows' => $banks->values()->map(fn ($bank) => [
                        $bank->teacher?->name ?? 'Belum ditetapkan',
                        $bank->subject->name,
                        $bank->rombel_names,
                        (int) $bank->pg_count, (int) $bank->essay_count, (int) $bank->short_answer_count,
                        (int) $bank->questions_count,
                        $bank->questions_count > 0 ? 'Sudah ada soal' : 'Belum ada soal',
                    ])->all(),
                ];
            })->values();
        $banks = $data['grouped'];
        $totals = [
            'banks' => $banks->count(), 'filled' => $banks->where('questions_count', '>', 0)->count(),
            'empty' => $banks->where('questions_count', 0)->count(),
            'questions' => (int) $banks->sum('questions_count'),
        ];

        return response()->view('questions.recap', [
            'groups' => $groups,
            'totals' => $totals,
            'school' => SchoolProfile::first(),
            'gradeLabel' => $data['gradeLevel'] ? 'Kelas '.$data['gradeLevel'] : 'Semua tingkat',
            'periodLabel' => $selectedPeriod?->name ?? 'Semua periode',
            'emptyDeadline' => $groups->isEmpty() && $selectedPeriod ? $this->recapDeadline($selectedPeriod) : null,
            'filterLabel' => collect([
                $data['questionStatus'] === 'filled' ? 'Sudah ada soal' : ($data['questionStatus'] === 'empty' ? 'Belum ada soal' : null),
                $data['search'] !== '' ? 'Pencarian: '.$data['search'] : null,
            ])->filter()->join(' · '),
            'generatedAt' => now()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            'filename' => 'rekap-bank-soal-inti-'.($data['gradeLevel'] ?? 'semua-tingkat').'-'.now()->format('Ymd-His'),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function recapDeadline(AssessmentPeriod $period): string
    {
        $deadline = $period->core_question_deadline_at?->timezone(AssessmentPeriod::CORE_QUESTION_TIMEZONE);

        return $deadline ? $deadline->format('d/m/Y H:i').' WIB' : 'Belum ditetapkan';
    }

    private function shareMessages(array $data): array
    {
        $selectedPeriod = $data['periods']->firstWhere('id', $data['periodId']);
        $scope = collect([
            $selectedPeriod ? $selectedPeriod->name.' · '.$selectedPeriod->academicYear->name : 'Semua periode',
            $data['gradeLevel'] ? 'Kelas '.$data['gradeLevel'] : 'Semua tingkat',
            $data['search'] !== '' ? 'Pencarian: '.$data['search'] : null,
        ])->filter()->join(' · ');
        $banks = $data['allStatuses']
            ->sortBy(fn ($bank) => sprintf('%02d|%s|%s', $bank->grade_level, $bank->subject->name, $bank->teacher?->name ?? ''))
            ->groupBy(fn ($bank) => $bank->questions_count > 0 ? 'filled' : 'empty');

        $messages = [];
        foreach (['filled' => 'Sudah Membuat Soal', 'empty' => 'Belum Membuat Soal'] as $status => $label) {
            $matching = $banks->get($status, collect());
            $lines = ['*Rekap Pembuatan Soal Ujian Inti*', $scope, '', "*{$label}*"];
            if ($matching->isEmpty()) {
                $lines[] = 'Tidak ada bank pada daftar ini.';
            }

            foreach ($matching->groupBy('assessment_period_id') as $periodBanks) {
                $period = $periodBanks->first()->assessmentPeriod;
                $lines[] = '';
                if (! $selectedPeriod) {
                    $lines[] = "*{$period->name} · {$period->academicYear->name}*";
                }
                if ($period->core_question_deadline_at) {
                    $lines[] = 'Batas pembuatan soal: '.$this->recapDeadline($period);
                }
                foreach ($periodBanks->values() as $index => $bank) {
                    $teacher = $bank->teacher?->name ?? 'Guru belum ditetapkan';
                    $total = (int) $bank->questions_count;
                    $lines[] = ($index + 1).". {$bank->subject->name} — Kelas {$bank->grade_level} ({$bank->rombel_names})";
                    $lines[] = "    {$teacher} · {$total} soal";
                    $lines[] = sprintf('    PG %d · Isian Singkat %d · Esai %d', $bank->pg_count, $bank->short_answer_count, $bank->essay_count);
                }
            }
            if ($matching->isNotEmpty()) {
                $lines[] = '';
                $lines[] = $status === 'filled' ? 'Terima kasih, Bapak/Ibu.' : 'Mohon Bapak/Ibu mulai mengisi soal sesuai jadwal yang ditetapkan. Terima kasih.';
            }
            $messages[$status] = ['label' => $label, 'text' => implode("\n", $lines)];
        }

        return $messages;
    }

    public function index(Request $request, AssessmentSubject $assessmentSubject): View
    {
        $this->authorizeAccess($request, $assessmentSubject);
        $rombelNames = $assessmentSubject->questionBankGroup()->with('schoolClass')->get()
            ->pluck('schoolClass.name')->sort(SORT_NATURAL)->join(', ');
        $assessmentSubject = $this->banks->readableComponent($assessmentSubject);
        $assessmentSubject->load(['assessmentPeriod.academicYear', 'subject', 'schoolClass', 'teacher']);
        $questions = $assessmentSubject->questions()
            ->orderBy('position')
            ->paginateForDisplay()
            ->withQueryString();
        $questionCount = $assessmentSubject->questions()->count();
        $totalPoints = (float) $assessmentSubject->questions()->sum('points');
        $hasAttempts = $assessmentSubject->questionBankGroup()->whereHas('assignments.attempt')->exists();
        $isLocked = $hasAttempts || $assessmentSubject->assessmentPeriod->coreQuestionDeadlinePassed();
        $questionTypes = $this->questionTypes($assessmentSubject);
        $gradeLevel = (int) $assessmentSubject->schoolClass->grade_level;

        return view('questions.index', compact(
            'assessmentSubject', 'questions', 'questionCount', 'totalPoints',
            'isLocked', 'hasAttempts', 'questionTypes', 'gradeLevel', 'rombelNames'
        ));
    }

    public function edit(Request $request, AssessmentSubject $assessmentSubject, ExamQuestion $question): View
    {
        $this->authorizeAccess($request, $assessmentSubject);
        abort_unless($question->assessment_subject_id === $assessmentSubject->id, 404);
        $assessmentSubject->load(['subject', 'assessmentPeriod']);
        abort_if($assessmentSubject->assessmentPeriod->coreQuestionDeadlinePassed()
            || $assessmentSubject->questionBankGroup()->whereHas('assignments.attempt')->exists(), 403, 'Bank soal sedang terkunci.');

        return view('questions.edit', [
            'subjectName' => $assessmentSubject->subject->name,
            'question' => $question,
            'questionTypes' => $this->questionTypes($assessmentSubject),
            'backUrl' => QuestionBankNavigation::questionUrl($assessmentSubject, $question),
            'action' => route('scheduling.questions.update', [$assessmentSubject, $question, 'per_page' => TablePagination::selected()]),
            'trial' => false,
        ]);
    }

    public function preview(Request $request, AssessmentSubject $assessmentSubject): Response
    {
        $this->authorizeAccess($request, $assessmentSubject);
        $assessmentSubject = $this->banks->readableComponent($assessmentSubject);
        $assessmentSubject->load(['subject', 'assessmentPeriod', 'schoolClass']);
        $page = ['per_page' => TablePagination::selected(), 'page' => max(1, $request->integer('page', 1))];
        $bank = [$assessmentSubject, ...$page];
        $questions = $assessmentSubject->questions()->orderBy('position');
        $focusedQuestion = $request->filled('soal') ? $questions->findOrFail($request->integer('soal')) : null;

        return response()->view('questions.preview', [
            'subjectName' => $assessmentSubject->subject->name,
            'examName' => $assessmentSubject->assessmentPeriod->name,
            'gradeLevel' => $assessmentSubject->schoolClass->grade_level,
            'questions' => $focusedQuestion ? collect([$focusedQuestion]) : $questions->get(),
            'focusedQuestion' => $focusedQuestion,
            'fullPreviewUrl' => route('scheduling.questions.preview', $bank),
            'backUrl' => $focusedQuestion ? QuestionBankNavigation::questionUrl($assessmentSubject, $focusedQuestion) : route('scheduling.questions.index', $bank),
            'trial' => false,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function importPdfForm(Request $request, AssessmentSubject $assessmentSubject, ?\Closure $prepare = null): View
    {
        $this->authorizeAccess($request, $assessmentSubject);
        $assessmentSubject->loadMissing('assessmentPeriod');
        abort_if($assessmentSubject->assessmentPeriod->coreQuestionDeadlinePassed()
            || $assessmentSubject->questionBankGroup()->whereHas('assignments.attempt')->exists(), 403, 'Bank soal sedang terkunci.');

        return view('questions.import-pdf', ($prepare ? $prepare() : []) + [
            'title' => $assessmentSubject->subject->name.' · Kelas '.$assessmentSubject->schoolClass->grade_level,
            'types' => $this->questionTypes($assessmentSubject),
            'trial' => false,
            'action' => route('scheduling.questions.pdf-import.store', $assessmentSubject),
            'backUrl' => route('scheduling.questions.index', $assessmentSubject),
        ]);
    }

    public function importPdf(Request $request, AssessmentSubject $assessmentSubject, QuestionPdfImport $import): View|RedirectResponse
    {
        $this->authorizeAccess($request, $assessmentSubject);
        $assessmentSubject->loadMissing('assessmentPeriod');
        if (! $request->has('import_token')) {
            return $this->importPdfForm($request, $assessmentSubject,
                fn () => $import->prepare($request, 'core:'.$assessmentSubject->id, $this->questionTypes($assessmentSubject), allowWrittenStatement: false));
        }

        [$question, $count] = $import->confirm($request, 'core:'.$assessmentSubject->id, 'exam-questions',
            function (array $questions) use ($request, $assessmentSubject): array {
                $siblings = $this->lockedGroup($request, $assessmentSubject);
                $this->assertEditable($siblings);
                $current = $siblings->find($assessmentSubject->id);
                $types = array_column($this->questionTypes($current), 'value');
                if (array_diff(array_column($questions, 'question_type'), $types) !== []) {
                    throw ValidationException::withMessages(['pdf' => 'Jenis asesmen berubah. Unggah PDF sesuai jenis soal yang berlaku.']);
                }
                $this->banks->synchronize($siblings);
                $position = (int) ExamQuestion::query()->whereIn('assessment_subject_id', $siblings->modelKeys())->max('position') + 1;
                foreach ($siblings as $component) {
                    $next = $position;
                    foreach ($questions as $attributes) {
                        $component->questions()->create($attributes + ['position' => $next++]);
                    }
                }

                return [$current->questions()->where('position', $position)->firstOrFail(), count($questions)];
            });

        return redirect()->to(QuestionBankNavigation::questionUrl($assessmentSubject, $question))
            ->with('saved_question_id', $question->id)->with('status', $count.' soal berhasil diimpor.');
    }

    public function print(Request $request, AssessmentSubject $assessmentSubject): Response
    {
        $this->authorizeAccess($request, $assessmentSubject);
        $assessmentSubject = $this->banks->readableComponent($assessmentSubject);
        $assessmentSubject->load(['subject', 'assessmentPeriod.academicYear', 'schoolClass']);
        $subject = $assessmentSubject->subject->name;
        $period = $assessmentSubject->assessmentPeriod;
        $grade = $assessmentSubject->schoolClass->grade_level;
        $group = $assessmentSubject->questionBankGroup()->with('schoolClass')->get()
            ->pluck('schoolClass.name')->sort(SORT_NATURAL)->join(', ');
        $withKey = $request->boolean('kunci');
        $returnPage = ['per_page' => TablePagination::selected(), 'page' => max(1, $request->integer('page', 1))];

        return response()->view('questions.print', [
            'school' => SchoolProfile::first(),
            'bankType' => 'Ujian Inti',
            'title' => $subject,
            'details' => [$period->name, 'Tahun ajaran '.$period->academicYear->name, 'Kelas '.$grade, 'Rombel '.$group],
            'questions' => $assessmentSubject->questions()->get(),
            'withKey' => $withKey,
            'trial' => false,
            'filename' => 'bank-soal-inti-'.Str::slug($subject).'-kelas-'.$grade.($withKey ? '-kunci' : ''),
            'backUrl' => route('scheduling.questions.index', [$assessmentSubject, ...$returnPage]),
            'modeUrl' => route('scheduling.questions.pdf', [$assessmentSubject, ...$returnPage, 'kunci' => $withKey ? null : 1]),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function export(
        Request $request,
        AssessmentSubject $assessmentSubject,
        StudentSpreadsheetService $spreadsheet,
        ExamScoreCalculator $scores,
    ): BinaryFileResponse {
        $this->authorizeAccess($request, $assessmentSubject);
        $assessmentSubject = $this->banks->readableComponent($assessmentSubject);
        $assessmentSubject->load(['assessmentPeriod.academicYear', 'subject', 'schoolClass']);
        $questions = $assessmentSubject->questions()
            ->where('question_type', ExamQuestionType::MultipleChoice->value)
            ->orderBy('position')
            ->get();
        $weight = $scores->multipleChoiceWeight($questions->count());
        $rows = $questions->values()->map(fn (ExamQuestion $question, int $index): array => [
            $index + 1,
            $this->questionText->plainText($question->question_text, $question->text_format),
            $this->questionText->plainText($question->options['A'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['B'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['C'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['D'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['E'] ?? '', $question->text_format),
            MultipleChoiceAnswers::label($question->correct_answer),
            number_format($weight, 4, '.', ''),
            $question->question_image_path ? Storage::url($question->question_image_path) : '',
            $this->questionText->plainText($question->statement, $question->text_format),
        ])->all();

        $path = $spreadsheet->createWorkbook(
            ['No', 'Pertanyaan', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D', 'Pilihan E', 'Kunci', 'Bobot PG', 'Gambar', 'Pernyataan'],
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
        $this->authorizeAccess($request, $assessmentSubject);
        $validated = $this->validateQuestion($request, $assessmentSubject);

        $question = DB::transaction(function () use ($request, $assessmentSubject, $validated): ExamQuestion {
            $siblings = $this->lockedGroup($request, $assessmentSubject);
            $this->assertEditable($siblings);
            $this->banks->synchronize($siblings);
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

            return $assessmentSubject->questions()->where('position', $position)->firstOrFail();
        });

        return redirect()->to(QuestionBankNavigation::questionUrl($assessmentSubject, $question))
            ->with('saved_question_id', $question->id)->with('status', 'Soal berhasil ditambahkan ke bank soal ini.');
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

        DB::transaction(function () use ($request, $assessmentSubject, $question, $validated): void {
            $siblings = $this->lockedGroup($request, $assessmentSubject);
            $this->assertEditable($siblings);
            $question = $assessmentSubject->questions()->findOrFail($question->id);
            $this->banks->synchronize($siblings);
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

        return redirect()->to(QuestionBankNavigation::questionUrl($assessmentSubject, $question->refresh()))
            ->with('saved_question_id', $question->id)->with('status', 'Soal berhasil diperbarui di bank soal ini.');
    }

    public function destroy(Request $request, AssessmentSubject $assessmentSubject, ExamQuestion $question): RedirectResponse
    {
        $this->authorizeAccess($request, $assessmentSubject);
        if ($question->assessment_subject_id !== $assessmentSubject->id) {
            abort(404);
        }

        try {
            DB::transaction(function () use ($request, $assessmentSubject, $question): void {
                $siblings = $this->lockedGroup($request, $assessmentSubject);
                $question = $assessmentSubject->questions()->findOrFail($question->id);
                $matching = ExamQuestion::query()
                    ->whereIn('assessment_subject_id', $siblings->modelKeys())
                    ->where('position', $question->position);
                if ((clone $matching)->whereHas('answers')->exists()) {
                    throw ValidationException::withMessages([
                        'question' => 'Soal tidak dapat dihapus karena sudah memiliki jawaban siswa.',
                    ]);
                }

                $this->assertEditable($siblings);
                $this->banks->synchronize($siblings);
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
                'question' => 'Bank soal terkunci karena sudah ada siswa pada rombel yang memakai bank ini yang mulai ujian.',
            ]);
        }
    }

    private function questionTypes(AssessmentSubject $component): array
    {
        return $component->assessmentPeriod->type === AssessmentType::ATS
            ? [ExamQuestionType::ShortAnswer, ExamQuestionType::Essay]
            : ExamQuestionType::cases();
    }

    private function validateQuestion(Request $request, AssessmentSubject $component): array
    {
        $component->loadMissing('assessmentPeriod');
        $allowedTypes = array_map(fn (ExamQuestionType $type): string => $type->value, $this->questionTypes($component));

        $validated = $request->validate([
            'question_type' => ['required', Rule::in($allowedTypes)],
            'text_format' => ['nullable', Rule::in(['plain', 'html'])],
            'question_text' => ['required', 'string', 'max:20000'],
            'statement' => ['exclude_unless:question_type,multiple_choice', 'nullable', 'string', 'max:20000'],
            'option_a' => ['required_if:question_type,multiple_choice', 'nullable', 'string', 'max:20000'],
            'option_b' => ['required_if:question_type,multiple_choice', 'nullable', 'string', 'max:20000'],
            'option_c' => ['required_if:question_type,multiple_choice', 'nullable', 'string', 'max:20000'],
            'option_d' => ['required_if:question_type,multiple_choice', 'nullable', 'string', 'max:20000'],
            'option_e' => ['required_if:question_type,multiple_choice', 'nullable', 'string', 'max:20000'],
            ...MultipleChoiceAnswers::bankRules(),
            'question_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_a' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_b' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_c' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_d' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'option_file_e' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'points' => ['required_unless:question_type,multiple_choice', 'nullable', 'numeric', 'between:0.01,1000'],
        ]);

        return $this->questionText->normalize($validated);
    }

    private function questionAttributes(array $validated): array
    {
        $isMultipleChoice = $validated['question_type'] === ExamQuestionType::MultipleChoice->value;

        return [
            'question_text' => trim($validated['question_text']),
            'text_format' => $validated['text_format'],
            'statement' => $validated['statement'] ?? null,
            'question_type' => $validated['question_type'],
            'options' => $isMultipleChoice ? [
                'A' => trim($validated['option_a']),
                'B' => trim($validated['option_b']),
                'C' => trim($validated['option_c']),
                'D' => trim($validated['option_d']),
                'E' => trim($validated['option_e']),
            ] : null,
            'correct_answer' => $isMultipleChoice
                ? MultipleChoiceAnswers::key($validated['correct_answers'] ?? [$validated['correct_answer']]) : null,
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

    private function authorizeAccess(Request $request, AssessmentSubject $component): void
    {
        if ($request->user()->role === UserRole::Teacher) {
            abort_unless($component->teacher_user_id === $request->user()->id, 403);
        }
    }

    private function lockedGroup(Request $request, AssessmentSubject $component): EloquentCollection
    {
        $period = AssessmentPeriod::query()->lockForUpdate()->findOrFail($component->assessment_period_id);
        $current = AssessmentSubject::query()->lockForUpdate()->findOrFail($component->id);
        $this->authorizeAccess($request, $current);
        if ($current->assessment_period_id !== $period->id) {
            throw ValidationException::withMessages(['question' => 'Periode bank soal berubah. Muat ulang halaman dan coba lagi.']);
        }
        if ($period->coreQuestionDeadlinePassed()) {
            throw ValidationException::withMessages(['question' => 'Batas pembuatan soal sudah lewat. Hubungi Panitia atau Super Admin untuk mengubah batas periode.']);
        }

        return $current->questionBankGroup()->orderBy('id')->lockForUpdate()->get();
    }
}
