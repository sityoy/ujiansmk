<?php

namespace App\Http\Controllers;

use App\Enums\ExamQuestionType;
use App\Enums\UserRole;
use App\Models\AssessmentSubject;
use App\Models\SchoolProfile;
use App\Models\Subject;
use App\Models\TrialAttempt;
use App\Models\TrialQuestion;
use App\Models\TrialQuestionBank;
use App\Models\User;
use App\Services\Exams\ExamScoreCalculator;
use App\Services\Exams\MultipleChoiceAnswers;
use App\Services\Exams\QuestionPdfImport;
use App\Services\Exams\QuestionAttachments;
use App\Services\Exams\QuestionContextManager;
use App\Services\Exams\QuestionText;
use App\Services\Exams\TrialQuestionBankArchive;
use App\Services\Exams\TrialQuestionBankCatalog;
use App\Services\Exams\TrialQuestionPack;
use App\Services\Students\StudentSpreadsheetService;
use App\Support\QuestionBankNavigation;
use App\Support\TablePagination;
use Illuminate\Database\Eloquent\Builder;
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

class TrialQuestionBankController extends Controller
{
    public function __construct(
        private readonly QuestionText $questionText,
        private readonly QuestionContextManager $contexts,
        private readonly QuestionAttachments $attachments,
    ) {}

    public function index(Request $request, TrialQuestionPack $pack, TrialQuestionBankCatalog $catalog): View
    {
        $data = $catalog->data($request);
        $catalogView = $request->query('view') === 'summary' ? 'summary' : 'banks';
        $packGrade = in_array($request->integer('pack_grade_level', 10), [10, 11, 12], true)
            ? $request->integer('pack_grade_level', 10) : null;
        $packSearch = trim((string) $request->query('pack_search'));
        $canManageBanks = $request->user()->role !== UserRole::Teacher;
        $banks = $data['query']->latest('id')->paginateForDisplay()->withQueryString();
        unset($data['query']);

        return view('trial-question-banks.catalog', $data + [
            'banks' => $banks,
            'catalogView' => $catalogView,
            'canManageBanks' => $canManageBanks,
            'subjects' => $canManageBanks ? Subject::query()->where('is_active', true)
                ->whereHas('assessmentSubjects')->orderBy('name')->get() : collect(),
            'gradeLevels' => [10, 11, 12],
            'packs' => $canManageBanks && $catalogView === 'banks' ? $pack->menu($packGrade, $packSearch) : [],
            'packGrade' => $packGrade,
            'packSearch' => $packSearch,
        ]);
    }

    public function recap(Request $request, TrialQuestionBankCatalog $catalog): Response
    {
        $data = $catalog->data($request);
        $banks = $data['query']->orderBy('grade_level')->orderBy('name')->orderBy('id')->get();
        $groups = $banks->groupBy('grade_level')->map(fn ($byGrade, $grade) => [
            'title' => 'Kelas '.$grade,
            'subtitle' => $data['archived'] ? 'Bank soal uji coba diarsipkan' : 'Bank soal uji coba aktif',
            'rows' => $byGrade->values()->map(fn (TrialQuestionBank $bank) => [
                $bank->teacher?->name ?? 'Belum ditetapkan',
                $bank->subject->name,
                $bank->name."\nBatas: ".($bank->questionDeadlineLabel() ?? 'Belum ditetapkan'),
                (int) $bank->pg_count, (int) $bank->essay_count, (int) $bank->short_answer_count,
                (int) $bank->questions_count,
                $bank->questions_count > 0 ? 'Sudah ada soal' : 'Belum ada soal',
            ])->all(),
        ])->values();
        $filled = $banks->where('questions_count', '>', 0)->count();

        return response()->view('questions.recap', [
            'reportKind' => 'Bank Soal Uji Coba',
            'detailColumn' => 'Bank / batas pembuatan',
            'backUrl' => route('trial-question-banks.index', $request->only(['grade_level', 'q', 'question_status', 'archived']) + ['view' => 'summary']),
            'groupingLabel' => 'per tingkat',
            'note' => 'Setiap bank uji coba dihitung satu kali. Rekap ini memuat jumlah soal dan tidak memuat isi atau kunci jawaban.',
            'groups' => $groups,
            'totals' => [
                'banks' => $banks->count(), 'filled' => $filled, 'empty' => $banks->count() - $filled,
                'questions' => (int) $banks->sum('questions_count'),
            ],
            'school' => SchoolProfile::first(),
            'gradeLabel' => $data['gradeLevel'] ? 'Kelas '.$data['gradeLevel'] : 'Semua tingkat',
            'periodLabel' => $data['archived'] ? 'Bank diarsipkan' : 'Bank aktif',
            'emptyDeadline' => null,
            'filterLabel' => collect([
                match ($data['questionStatus']) {
                    'filled' => 'Sudah ada soal',
                    'empty' => 'Belum ada soal',
                    default => null,
                },
                $data['search'] !== '' ? 'Pencarian: '.$data['search'] : null,
            ])->filter()->join(' · '),
            'generatedAt' => now()->timezone(TrialQuestionBank::QUESTION_TIMEZONE)->format('d/m/Y H:i').' WIB',
            'filename' => 'rekap-bank-soal-uji-coba-'.($data['gradeLevel'] ?? 'semua-tingkat').'-'.now()->format('Ymd-His'),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
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
            ->with('status', 'Bank soal uji coba dibuat. Silakan tambahkan soal.');
    }

    public function questions(Request $request, TrialQuestionBank $trialQuestionBank): View
    {
        $this->authorizeBank($request, $trialQuestionBank);
        $trialQuestionBank->load(['subject', 'teacher']);
        $lockedReason = $this->lockedReason($trialQuestionBank);

        return view('trial-question-banks.questions', [
            'bank' => $trialQuestionBank,
            'questions' => $trialQuestionBank->questions()->with('context')->paginateForDisplay()->withQueryString(),
            'availableContexts' => $lockedReason ? collect() : $this->contexts->available($trialQuestionBank->questions()),
            'questionCount' => $trialQuestionBank->questions()->count(),
            'totalPoints' => (float) $trialQuestionBank->questions()->sum('points'),
            'isLocked' => $lockedReason !== null,
            'canDeleteQuestion' => $this->lockedReason($trialQuestionBank, allowAttemptHistory: true) === null,
            'lockedReason' => $lockedReason,
            'teachers' => $request->user()->role !== UserRole::Teacher
                ? User::query()->where('role', UserRole::Teacher)->whereIn('id', $this->eligibleTeachers($trialQuestionBank))
                    ->orderBy('name')->get() : collect(),
        ]);
    }

    public function editQuestion(Request $request, TrialQuestionBank $trialQuestionBank, TrialQuestion $question): View
    {
        $this->authorizeBank($request, $trialQuestionBank);
        abort_unless($question->trial_question_bank_id === $trialQuestionBank->id && $question->retired_after_attempt_id === null, 404);
        abort_if($reason = $this->lockedReason($trialQuestionBank), 403, $reason);

        return view('questions.edit', [
            'subjectName' => $trialQuestionBank->subject->name,
            'question' => $question->load('context'),
            'availableContexts' => $this->contexts->available($trialQuestionBank->questions()),
            'questionTypes' => ExamQuestionType::cases(),
            'backUrl' => QuestionBankNavigation::questionUrl($trialQuestionBank, $question),
            'action' => route('trial-question-banks.questions.update', [$trialQuestionBank, $question, 'per_page' => TablePagination::selected()]),
            'trial' => true,
        ]);
    }

    public function preview(Request $request, TrialQuestionBank $trialQuestionBank): Response
    {
        $this->authorizeBank($request, $trialQuestionBank);
        $bank = [$trialQuestionBank, 'per_page' => TablePagination::selected(), 'page' => max(1, $request->integer('page', 1))];
        $questions = $trialQuestionBank->questions()->with('context');
        $focusedQuestion = $request->filled('soal') ? $questions->findOrFail($request->integer('soal')) : null;

        return response()->view('questions.preview', [
            'subjectName' => $trialQuestionBank->subject->name,
            'examName' => $trialQuestionBank->name,
            'gradeLevel' => $trialQuestionBank->grade_level,
            'questions' => $focusedQuestion ? collect([$focusedQuestion]) : $questions->get(),
            'focusedQuestion' => $focusedQuestion,
            'fullPreviewUrl' => route('trial-question-banks.questions.preview', $bank),
            'backUrl' => $focusedQuestion ? QuestionBankNavigation::questionUrl($trialQuestionBank, $focusedQuestion) : route('trial-question-banks.questions.index', $bank),
            'trial' => true,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function updateSettings(Request $request, TrialQuestionBank $trialQuestionBank): RedirectResponse
    {
        $validated = $request->validate([
            'question_deadline_at' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i'],
            'teacher_user_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        DB::transaction(function () use ($trialQuestionBank, $validated): void {
            $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($trialQuestionBank->id);
            if (array_key_exists('teacher_user_id', $validated)) {
                $teacherId = $validated['teacher_user_id'];
                if ($teacherId !== null && ! User::query()->whereKey($teacherId)->where('role', UserRole::Teacher)
                    ->whereIn('id', $this->eligibleTeachers($bank))->exists()) {
                    throw ValidationException::withMessages(['teacher_user_id' => 'Pilih guru yang ditugaskan pada mata pelajaran dan tingkat bank ini.']);
                }
                $bank->teacher_user_id = $teacherId;
            }
            if (array_key_exists('question_deadline_at', $validated)) {
                $value = $validated['question_deadline_at'];
                $bank->question_deadline_at = $value === null ? null : Carbon::createFromFormat(
                    '!Y-m-d\TH:i', $value, TrialQuestionBank::QUESTION_TIMEZONE,
                )->setTimezone(config('app.timezone'));
            }
            $bank->save();
        });

        return back()->with('status', 'Pengaturan bank soal uji coba berhasil disimpan.');
    }

    private function eligibleTeachers(TrialQuestionBank $bank): Builder
    {
        return AssessmentSubject::query()->where('subject_id', $bank->subject_id)
            ->whereHas('schoolClass', fn ($classes) => $classes->where('grade_level', $bank->grade_level))
            ->whereNotNull('teacher_user_id')->select('teacher_user_id');
    }

    public function importPdfForm(Request $request, TrialQuestionBank $trialQuestionBank, ?\Closure $prepare = null): View
    {
        $this->authorizeBank($request, $trialQuestionBank);
        abort_if($reason = $this->lockedReason($trialQuestionBank), 403, $reason);

        return view('questions.import-pdf', ($prepare ? $prepare() : []) + [
            'title' => $trialQuestionBank->name.' · Kelas '.$trialQuestionBank->grade_level,
            'types' => ExamQuestionType::cases(),
            'trial' => true,
            'action' => route('trial-question-banks.questions.pdf-import.store', $trialQuestionBank),
            'backUrl' => route('trial-question-banks.questions.index', $trialQuestionBank),
        ]);
    }

    public function importPdf(Request $request, TrialQuestionBank $trialQuestionBank, QuestionPdfImport $import): View|RedirectResponse
    {
        $this->authorizeBank($request, $trialQuestionBank);
        if (! $request->has('import_token')) {
            return $this->importPdfForm($request, $trialQuestionBank,
                fn () => $import->prepare($request, 'trial:'.$trialQuestionBank->id, ExamQuestionType::cases()));
        }

        [$question, $count] = $import->confirm($request, 'trial:'.$trialQuestionBank->id, 'trial-questions',
            function (array $questions) use ($request, $trialQuestionBank): array {
                $this->assertEditable($request, $trialQuestionBank);
                $position = (int) $trialQuestionBank->allQuestions()->reorder()->max('position') + 1;
                $next = $position;
                foreach ($questions as $attributes) {
                    $trialQuestionBank->questions()->create($attributes + ['position' => $next++]);
                }

                return [$trialQuestionBank->questions()->where('position', $position)->firstOrFail(), count($questions)];
            });

        return redirect()->to(QuestionBankNavigation::questionUrl($trialQuestionBank, $question))
            ->with('saved_question_id', $question->id)->with('status', $count.' soal berhasil diimpor.');
    }

    public function print(Request $request, TrialQuestionBank $trialQuestionBank): Response
    {
        $this->authorizeBank($request, $trialQuestionBank);
        $subject = $trialQuestionBank->subject->name;
        $grade = $trialQuestionBank->grade_level;
        $withKey = $request->boolean('kunci');
        $returnPage = ['per_page' => TablePagination::selected(), 'page' => max(1, $request->integer('page', 1))];
        $questions = $trialQuestionBank->questions()->with('context')->get();
        foreach ($questions as $index => $question) {
            $question->position = $index + 1;
        }

        return response()->view('questions.print', [
            'school' => SchoolProfile::first(),
            'bankType' => 'Ujian Uji Coba',
            'title' => $trialQuestionBank->name,
            'details' => [$subject, 'Kelas '.$grade],
            'questions' => $questions,
            'withKey' => $withKey,
            'trial' => true,
            'filename' => 'bank-soal-uji-coba-'.Str::slug($trialQuestionBank->name).($withKey ? '-kunci' : ''),
            'backUrl' => route('trial-question-banks.questions.index', [$trialQuestionBank, ...$returnPage]),
            'modeUrl' => route('trial-question-banks.questions.pdf', [$trialQuestionBank, ...$returnPage, 'kunci' => $withKey ? null : 1]),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function export(
        Request $request,
        TrialQuestionBank $trialQuestionBank,
        StudentSpreadsheetService $spreadsheet,
    ): BinaryFileResponse {
        $this->authorizeBank($request, $trialQuestionBank);
        $trialQuestionBank->load('subject');
        $questions = $trialQuestionBank->questions()->with('context')->get();
        $rows = $questions->values()->map(fn (TrialQuestion $question, int $index): array => [
            $index + 1,
            $this->questionText->plainText($question->question_text, $question->text_format),
            $this->questionText->plainText($question->options['A'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['B'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['C'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['D'] ?? '', $question->text_format),
            $this->questionText->plainText($question->options['E'] ?? '', $question->text_format),
            MultipleChoiceAnswers::label($question->correct_answer),
            number_format((float) $question->points, 4, '.', ''),
            $question->question_image_path ? Storage::url($question->question_image_path) : '',
            $this->contexts->exportStatement($question),
            $question->question_type->label(),
            $question->context?->image_path ? Storage::url($question->context->image_path) : '',
            $question->question_image_caption,
            $question->context?->image_caption,
        ])->all();

        $path = $spreadsheet->createWorkbook(
            ['No', 'Pertanyaan', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D', 'Pilihan E', 'Kunci', 'Bobot', 'Gambar', 'Pernyataan', 'Jenis Soal', 'Gambar Pernyataan Bersama', 'Keterangan Gambar Soal', 'Keterangan Gambar Bersama'],
            $rows,
            'Soal Uji Coba',
        );
        $filename = 'bank-soal-uji-coba-'.Str::slug($trialQuestionBank->subject->name)
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

        $question = $this->attachments->save(function () use ($request, $trialQuestionBank, $validated): TrialQuestion {
            $this->assertEditable($request, $trialQuestionBank);
            $scope = $trialQuestionBank->questions()->getQuery();
            $contextId = $this->contexts->resolve($validated, $scope, $this->attachments);
            $position = (int) $trialQuestionBank->allQuestions()->reorder()->max('position') + 1;
            return $trialQuestionBank->questions()->create([
                ...$this->attributes($validated),
                'question_context_id' => $contextId,
                ...$this->attachments->questionAttributes($validated, 'trial'),
                'position' => $position,
            ]);
        });

        return redirect()->to(QuestionBankNavigation::questionUrl($trialQuestionBank, $question))
            ->with('saved_question_id', $question->id)->with('saved_context_id', $question->question_context_id)->with('status', 'Soal uji coba berhasil ditambahkan.');
    }

    public function updateQuestion(
        Request $request,
        TrialQuestionBank $trialQuestionBank,
        TrialQuestion $question,
    ): RedirectResponse {
        $this->authorizeBank($request, $trialQuestionBank);
        abort_unless($question->trial_question_bank_id === $trialQuestionBank->id, 404);
        $validated = $this->validateQuestion($request);

        $this->attachments->save(function () use ($request, $trialQuestionBank, $question, $validated): void {
            $this->assertEditable($request, $trialQuestionBank);
            $question = $trialQuestionBank->questions()->findOrFail($question->id);
            $scope = $trialQuestionBank->questions()->getQuery();
            $previousContext = $question->question_context_id;
            $attributes = $this->attributes($validated);
            $attributes['question_context_id'] = $this->contexts->resolve($validated, $scope, $this->attachments, $previousContext);
            $attachments = $this->attachments->questionAttributes($validated, 'trial', $question);
            $oldPaths = [$question->question_image_path, ...array_values($question->option_file_paths ?? [])];

            $question->update($attributes + $attachments);
            $this->attachments->cleanAfterCommit(
                $oldPaths,
                [$attachments['question_image_path'], ...array_values($attachments['option_file_paths'] ?? [])],
            );
            if ($previousContext !== $attributes['question_context_id']) {
                $this->contexts->release($previousContext, $this->attachments);
            }
        });

        return redirect()->to(QuestionBankNavigation::questionUrl($trialQuestionBank, $question->refresh()))
            ->with('saved_question_id', $question->id)->with('saved_context_id', $question->question_context_id)->with('status', 'Soal uji coba berhasil diperbarui.');
    }

    public function destroyQuestion(
        Request $request,
        TrialQuestionBank $trialQuestionBank,
        TrialQuestion $question,
    ): RedirectResponse {
        $this->authorizeBank($request, $trialQuestionBank);
        abort_unless($question->trial_question_bank_id === $trialQuestionBank->id, 404);

        try {
            $retired = DB::transaction(function () use ($request, $trialQuestionBank, $question): bool {
                $bank = $this->assertEditable($request, $trialQuestionBank, allowAttemptHistory: true);
                $question = $bank->allQuestions()->findOrFail($question->id);
                if ($question->retired_after_attempt_id !== null) {
                    return true;
                }

                // Starts take the same bank lock. Later attempts receive larger IDs and omit this question.
                $lastAttemptId = TrialAttempt::query()->whereIn('trial_exam_id', $bank->trialExams()->select('id'))->max('id');
                if ($lastAttemptId !== null) {
                    $question->forceFill(['retired_after_attempt_id' => $lastAttemptId])->save();

                    return true;
                }

                if ($question->answers()->exists()) {
                    throw ValidationException::withMessages(['question' => 'Soal masih terhubung ke jawaban dari sesi lain.']);
                }
                $imagePath = $question->question_image_path;
                $optionPaths = $question->option_file_paths ?? [];
                $question->delete();
                $this->contexts->release($question->question_context_id, $this->attachments);
                $this->attachments->cleanAfterCommit([$imagePath, ...array_values($optionPaths)]);
                if ($bank->allQuestions()->whereNotNull('retired_after_attempt_id')->doesntExist()) {
                    $bank->questions()->lockForUpdate()->get()
                        ->each(function (TrialQuestion $remaining, int $index): void {
                            if ((int) $remaining->position !== $index + 1) {
                                $remaining->update(['position' => $index + 1]);
                            }
                        });
                }

                return false;
            }, 3);
        } catch (QueryException) {
            return back()->withErrors(['question' => 'Soal tidak dapat dihapus karena sudah digunakan.']);
        }

        return back()->with('status', $retired
            ? 'Soal dihapus dari bank aktif untuk peserta baru. Riwayat jawaban tetap tersimpan.'
            : 'Soal dihapus dan nomor soal telah dirapikan.');
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
        $request->mergeIfMissing(['question_type' => ExamQuestionType::MultipleChoice->value]);
        $validated = $request->validate([
            'question_type' => ['required', Rule::enum(ExamQuestionType::class)],
            'points' => ['exclude_if:question_type,multiple_choice', 'required', 'numeric', 'between:0.01,1000', 'decimal:0,2'],
            'text_format' => ['nullable', Rule::in(['plain', 'html'])],
            'question_text' => ['required', 'string', 'max:20000'],
            'statement' => ['nullable', 'string', 'max:20000'],
            ...$this->contexts->rules(),
            'option_a' => ['exclude_unless:question_type,multiple_choice', 'required', 'string', 'max:20000'],
            'option_b' => ['exclude_unless:question_type,multiple_choice', 'required', 'string', 'max:20000'],
            'option_c' => ['exclude_unless:question_type,multiple_choice', 'required', 'string', 'max:20000'],
            'option_d' => ['exclude_unless:question_type,multiple_choice', 'required', 'string', 'max:20000'],
            'option_e' => ['exclude_unless:question_type,multiple_choice', 'required', 'string', 'max:20000'],
            ...MultipleChoiceAnswers::bankRules(),
            ...QuestionAttachments::questionRules(),
        ]);

        return $this->questionText->normalize($validated);
    }

    private function attributes(array $validated): array
    {
        $multipleChoice = $validated['question_type'] === ExamQuestionType::MultipleChoice->value;

        return [
            'question_type' => $validated['question_type'],
            'question_text' => trim($validated['question_text']),
            'text_format' => $validated['text_format'],
            'statement' => $validated['statement'] ?? null,
            'options' => $multipleChoice ? [
                'A' => trim($validated['option_a']),
                'B' => trim($validated['option_b']),
                'C' => trim($validated['option_c']),
                'D' => trim($validated['option_d']),
                'E' => trim($validated['option_e']),
            ] : null,
            'correct_answer' => $multipleChoice
                ? MultipleChoiceAnswers::key($validated['correct_answers'] ?? [$validated['correct_answer']]) : null,
            'points' => $multipleChoice ? ExamScoreCalculator::MULTIPLE_CHOICE_POINTS : $validated['points'],
        ];
    }

    private function authorizeBank(Request $request, TrialQuestionBank $bank): void
    {
        if ($request->user()->role === UserRole::Teacher) {
            abort_unless($bank->teacher_user_id === $request->user()->id && $bank->archived_at === null, 403);
        }
    }

    private function lockedReason(TrialQuestionBank $bank, bool $allowAttemptHistory = false): ?string
    {
        if ($bank->archived_at !== null) {
            return 'Bank sudah diarsipkan. Pulihkan sebelum mengelola soal.';
        }
        if (! $allowAttemptHistory && $bank->trialExams()->whereHas('attempts')->exists()) {
            return 'Penambahan dan pengeditan soal dikunci karena sudah ada peserta yang memulai uji coba.';
        }
        if ($bank->question_deadline_at?->lte(now())) {
            return 'Batas pembuatan soal sudah lewat. Hubungi Panitia atau Super Admin untuk mengubah batas bank ini.';
        }

        return null;
    }

    private function assertEditable(Request $request, TrialQuestionBank $bank, bool $allowAttemptHistory = false): TrialQuestionBank
    {
        $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($bank->id);
        $this->authorizeBank($request, $bank);
        if ($reason = $this->lockedReason($bank, $allowAttemptHistory)) {
            throw ValidationException::withMessages(['question' => $reason]);
        }

        return $bank;
    }
}
