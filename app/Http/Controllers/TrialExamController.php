<?php

namespace App\Http\Controllers;

use App\Enums\GradingStatus;
use App\Enums\UserRole;
use App\Models\AssessmentSubject;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use App\Models\TrialLocation;
use App\Models\TrialQuestionBank;
use App\Models\User;
use App\Services\Exams\TrialExamAttemptService;
use App\Services\Exams\TrialExamSecurityService;
use App\Support\TablePagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TrialExamController extends Controller
{
    public function index(): View
    {
        return view('tryouts.index', [
            'trialExams' => TrialExam::query()
                ->with(['questionBank.subject', 'assessmentSubject.assessmentPeriod.academicYear', 'location'])
                ->withCount('currentAttempts as attempts_count')
                ->latest()
                ->paginateForDisplay(),
            'trialLocations' => TrialLocation::query()->orderBy('name')->get(),
            'banks' => TrialQuestionBank::query()->whereNull('archived_at')
                ->with('subject')
                ->withCount('questions')
                ->whereHas('questions')
                ->whereDoesntHave('trialExams')
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Request $request, TrialExam $trialExam): View
    {
        $trialExam->load(['questionBank.subject', 'assessmentSubject.assessmentPeriod.academicYear']);
        $this->authorizeMonitor($request, $trialExam);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'locked', 'submitted'])],
        ]);
        $attempts = $trialExam->currentAttempts()->withCount('securityIncidents')->get();
        $rows = $this->participantRows($trialExam, $request->user(), $attempts);
        $statistics = $this->participantStatistics($rows);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['student']->full_name), mb_strtolower($search))
                || str_contains((string) $row['student']->student_number, $search)
                || str_contains((string) $row['student']->nisn, $search)
            );
        }
        if ($status = $filters['status'] ?? null) {
            $rows = $rows->where('status', $status);
        }

        $participants = TablePagination::collection($rows, 'page');

        $attempts->whereIn('id', collect($participants->items())->pluck('attempt.id')->filter()->all())
            ->load(['securityIncidents' => fn ($query) => $query->orderBy('id')]);

        return view('tryouts.participants', compact(
            'trialExam', 'participants', 'statistics', 'filters'
        ) + [
            'trialsByGrade' => $this->examsByGradeAndSubject(TrialExam::query()
                ->visibleToMonitor($request->user())->with('questionBank.subject')->get())
                ->groupBy('questionBank.grade_level'),
            'canPrintScores' => $this->canPrintScores($request->user()),
            'canRestart' => $this->canRestart($request->user()),
            'canGrade' => $this->canGrade($request->user()),
        ]);
    }

    public function switchParticipants(Request $request): RedirectResponse
    {
        $validated = $request->validate(['exam' => ['required', 'string', 'max:255']]);
        $exam = TrialExam::query()->visibleToMonitor($request->user())
            ->where('slug', $validated['exam'])->firstOrFail();

        return redirect()->route('tryouts.participants', $exam);
    }

    public function editGrade(Request $request, TrialExam $trialExam, TrialAttempt $trialAttempt): View
    {
        $this->authorizeGrading($request, $trialExam, $trialAttempt);
        $trialExam->loadMissing('questionBank.subject');
        $trialAttempt->load(['answers.question']);
        $answers = $trialAttempt->answers
            ->filter(fn ($answer) => $answer->question->question_type->requiresManualGrading())
            ->sortBy('question.position');

        return view('tryouts.grade', compact('trialExam', 'trialAttempt', 'answers'));
    }

    public function updateGrade(
        Request $request,
        TrialExam $trialExam,
        TrialAttempt $trialAttempt,
        TrialExamAttemptService $attempts,
    ): RedirectResponse {
        $this->authorizeGrading($request, $trialExam, $trialAttempt);
        $attempts->gradeWrittenAnswers($trialExam, $trialAttempt, $request->input('scores'));

        return redirect()->route('tryouts.participants', $trialExam)->with('status', 'Nilai jawaban berhasil disimpan.');
    }

    public function progress(Request $request, TrialExam $trialExam): JsonResponse
    {
        $trialExam->load(['questionBank.subject', 'assessmentSubject']);
        $this->authorizeMonitor($request, $trialExam);
        $validated = $request->validate([
            'student_ids' => ['sometimes', 'required', 'array', 'max:50'],
            'student_ids.*' => ['integer'],
        ]);

        $attempts = $trialExam->currentAttempts()->withCount('securityIncidents')->get();
        $rows = $this->participantRows($trialExam, $request->user(), $attempts);
        $statistics = $this->participantStatistics($rows);
        $requested = isset($validated['student_ids']) ? array_flip($validated['student_ids']) : null;

        $canRestart = $this->canRestart($request->user());
        $canGrade = $this->canGrade($request->user());
        $participants = $rows
            ->filter(fn (array $row): bool => $requested === null || isset($requested[$row['student']->id]))
            ->mapWithKeys(function (array $row) use ($canRestart, $canGrade, $trialExam): array {
                $attempt = $row['attempt'];

                return [(string) $row['student']->id => [
                    'status' => $row['status'],
                    'started_at' => $attempt?->started_at?->format('d/m/Y H:i:s'),
                    'submitted_at' => $attempt?->submitted_at?->format('d/m/Y H:i:s'),
                    'violations' => $attempt?->violation_count,
                    'reset_used' => (int) ($attempt?->security_reset_count ?? 0),
                    'incidents' => $attempt?->security_incidents_count,
                    ...($canRestart ? ['restart_url' => $attempt ? route('tryouts.attempts.restart', [$trialExam, $attempt]) : null] : []),
                    ...($canGrade ? ['grade_url' => $this->hasWrittenAnswersToGrade($attempt) ? route('tryouts.attempts.grade.edit', [$trialExam, $attempt]) : null] : []),
                ]];
            });

        return response()->json([
            'statistics' => $statistics,
            'participants' => $participants,
            'updated_at' => now()->format('H:i:s'),
        ])->header('Cache-Control', 'no-store');
    }

    public function printScores(Request $request, TrialExam $trialExam): Response
    {
        abort_unless($this->canPrintScores($request->user()), 403);

        return $this->scorePrintView($trialExam->newCollection([$trialExam]), $trialExam);
    }

    public function printAllScores(Request $request): Response
    {
        abort_unless($this->canPrintScores($request->user()), 403);

        return $this->scorePrintView(TrialExam::all());
    }

    private function scorePrintView(EloquentCollection $exams, ?TrialExam $singleExam = null): Response
    {
        $incidentOrder = fn ($query) => $query->orderBy('occurred_at')->orderBy('id');
        $exams->load([
            'questionBank.subject',
            'assessmentSubject.assessmentPeriod.academicYear',
            'currentAttempts.student.schoolClass',
            'currentAttempts.securityIncidents' => $incidentOrder,
            'attempts' => fn ($query) => $query->where('status', TrialAttempt::RESET_STATUS)
                ->whereHas('securityIncidents', fn ($incidents) => $incidents
                    ->whereIn('category', TrialExamSecurityService::CLIENT_CATEGORIES)),
            'attempts.securityIncidents' => $incidentOrder,
        ]);
        $studentsByGrade = $this->activeRoster($exams->pluck('questionBank.grade_level')->unique()->all())
            ->get()->groupBy('schoolClass.grade_level');

        $reports = $this->examsByGradeAndSubject($exams)->map(function (TrialExam $exam) use ($studentsByGrade): array {
            $rows = $this->scoreRows(
                $studentsByGrade->get($exam->questionBank->grade_level, collect()),
                $exam->currentAttempts,
                $exam->attempts,
            );

            return ['exam' => $exam, 'rows' => $rows, 'statistics' => $this->participantStatistics($rows)];
        });

        return response()->view('tryouts.scores-print', [
            'singleExam' => $singleExam,
            'reports' => $reports,
            'school' => SchoolProfile::query()->first(),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function scoreRows(Collection $students, Collection $attempts, Collection $previousAttempts): Collection
    {
        $rows = $this->matchParticipants($students, $attempts);
        $matched = array_flip($rows->pluck('attempt.id')->filter()->all());

        // Keep historical results whose participant is now outside the active roster.
        foreach ($attempts->where('status', 'submitted') as $attempt) {
            if (! isset($matched[$attempt->id])) {
                $rows->push($this->participantRow($attempt->student, $attempt));
            }
        }

        $previousByStudent = $previousAttempts->whereNotNull('student_id')->groupBy('student_id');
        $previousByIdentifier = $previousAttempts->whereNull('student_id')
            ->whereNotNull('participant_identifier')->groupBy('participant_identifier');

        return $rows->map(function (array $row) use ($previousByStudent, $previousByIdentifier): array {
            $student = $row['student'];
            $attempt = $row['attempt'];
            $studentId = $student?->id ?? $attempt?->student_id;
            $identifiers = collect([$student?->nisn, $student?->student_number, $attempt?->participant_identifier])
                ->filter()->unique();

            $row['previousAttempts'] = $previousByStudent->get($studentId, collect())
                ->concat($identifiers->flatMap(fn ($identifier) => $previousByIdentifier->get($identifier, collect())))
                ->sortBy([['started_at', 'asc'], ['id', 'asc']]);

            return $row;
        })->sortBy([['student.schoolClass.name', 'asc'], ['name', 'asc']])->values();
    }

    public function links(Request $request): Response
    {
        $grades = [10, 11, 12];
        $filters = $request->validate(['grade' => ['nullable', 'integer', Rule::in($grades)]]);
        $grade = isset($filters['grade']) ? (int) $filters['grade'] : null;
        $exams = TrialExam::query()
            ->whereHas('questionBank', fn ($query) => $query->whereNull('archived_at')
                ->whereIn('grade_level', $grade ? [$grade] : $grades))
            ->with('questionBank.subject')
            ->get();

        $groups = $this->examsByGradeAndSubject($exams)->groupBy('questionBank.grade_level')
            ->map(fn ($exams) => $exams->map(fn (TrialExam $exam): array => [
                'subject' => $exam->questionBank->subject->name,
                'title' => $exam->title,
                'url' => route('tryout.entry', $exam),
                'code' => $exam->access_code,
                'status' => match (true) {
                    ! $exam->is_active => 'Nonaktif',
                    $exam->starts_at?->isFuture() => 'Dibuka '.$exam->starts_at->format('d/m/Y H:i'),
                    $exam->ends_at?->isPast() => 'Sudah ditutup',
                    default => 'Terbuka',
                },
            ]));
        $shareText = $groups->map(function ($rows, int $level): string {
            $links = $rows->map(fn (array $row): string =>
                "{$row['subject']} — {$row['title']} ({$row['status']})\n{$row['url']}\nKode akses {$row['code']}"
            )->implode("\n\n");

            return "Ujian Uji Coba Kelas {$level}\n\n{$links}\n\nMasuk menggunakan NIS atau NISN dan kode akses sesuai ujian.";
        })->implode("\n\n");

        return response()->view('tryouts.links', [
            'school' => SchoolProfile::query()->first(),
            'grades' => $grades,
            'grade' => $grade,
            'groups' => $groups,
            'shareText' => $shareText,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function cards(TrialExam $trialExam): Response
    {
        $trialExam->load('questionBank.subject');
        abort_if($trialExam->questionBank->archived_at !== null, 404);
        $students = Student::query()
            ->with(['schoolClass.academicYear', 'user' => fn ($users) => $users->where('role', UserRole::Student)->where('is_active', true)])
            ->where('is_active', true)
            ->whereHas('schoolClass', fn ($query) => $query
                ->where('grade_level', $trialExam->questionBank->grade_level)
                ->whereHas('academicYear', fn ($yearQuery) => $yearQuery->where('is_active', true)))
            ->orderBy(
                Student::query()
                    ->select('name')
                    ->from('school_classes')
                    ->whereColumn('school_classes.id', 'students.school_class_id')
                    ->limit(1),
            )
            ->orderBy('full_name')
            ->get();

        return response()->view('tryouts.cards', [
            'school' => SchoolProfile::query()->first(),
            'trialExam' => $trialExam,
            'students' => $students,
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function allCards(): Response
    {
        $trialExams = TrialExam::query()
            ->whereHas('questionBank', fn ($query) => $query->whereNull('archived_at'))
            ->with('questionBank.subject')
            ->get()
            ->sortBy(fn (TrialExam $trialExam): string => sprintf(
                '%02d-%s-%s',
                $trialExam->questionBank->grade_level,
                $trialExam->questionBank->subject->name,
                $trialExam->title,
            ))
            ->values();
        $gradeLevels = $trialExams
            ->pluck('questionBank.grade_level')
            ->unique()
            ->values();

        $students = Student::query()
            ->with(['schoolClass.academicYear', 'user' => fn ($users) => $users->where('role', UserRole::Student)->where('is_active', true)])
            ->where('is_active', true)
            ->whereHas('schoolClass', fn ($query) => $query
                ->whereIn('grade_level', $gradeLevels)
                ->whereHas('academicYear', fn ($yearQuery) => $yearQuery->where('is_active', true)))
            ->orderBy(
                Student::query()
                    ->select('name')
                    ->from('school_classes')
                    ->whereColumn('school_classes.id', 'students.school_class_id')
                    ->limit(1),
            )
            ->orderBy('full_name')
            ->get();

        return response()->view('tryouts.all-cards', [
            'school' => SchoolProfile::query()->first(),
            'trialExams' => $trialExams,
            'trialsByGrade' => $trialExams->groupBy('questionBank.grade_level'),
            'students' => $students,
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'trial_question_bank_id' => [
                'required',
                'integer',
                'exists:trial_question_banks,id',
                Rule::unique('trial_exams', 'trial_question_bank_id'),
            ],
        ]);
        $settings = $this->validateSettings($request);
        $settings['trial_location_id'] = $this->requiredLocationId($settings['trial_location_id'] ?? null);

        DB::transaction(function () use ($settings, $validated): void {
            // Lock the parent bank so two simultaneous clicks cannot create duplicates.
            $bank = TrialQuestionBank::query()
                ->lockForUpdate()
                ->whereKey($validated['trial_question_bank_id'])
                ->whereNull('archived_at')
                ->whereHas('questions')
                ->firstOrFail();

            if ($bank->trialExams()->exists()) {
                throw ValidationException::withMessages([
                    'trial_question_bank_id' => 'Bank soal ini sudah dipakai pada Ujian Uji Coba. Satu bank soal hanya dapat dibuat menjadi satu ujian.',
                ]);
            }

            // Prefer a component for the same grade. Trial banks are intentionally
            // independent from core-exam scheduling, so fall back to another class
            // of the same subject when that grade has not been scheduled yet.
            $component = AssessmentSubject::query()
                ->where('subject_id', $bank->subject_id)
                ->whereHas('schoolClass', fn ($query) => $query->where('grade_level', $bank->grade_level))
                ->whereHas('assessmentPeriod.academicYear', fn ($query) => $query->where('is_active', true))
                ->latest('id')
                ->first()
                ?? AssessmentSubject::query()
                    ->where('subject_id', $bank->subject_id)
                    ->whereHas('schoolClass', fn ($query) => $query->where('grade_level', $bank->grade_level))
                    ->latest('id')
                    ->first()
                ?? AssessmentSubject::query()
                    ->where('subject_id', $bank->subject_id)
                    ->whereHas('assessmentPeriod.academicYear', fn ($query) => $query->where('is_active', true))
                    ->latest('id')
                    ->first()
                ?? AssessmentSubject::query()
                    ->where('subject_id', $bank->subject_id)
                    ->latest('id')
                    ->first();

            if (! $component) {
                throw ValidationException::withMessages([
                    'trial_question_bank_id' => 'Mapel bank soal belum memiliki referensi pada data penjadwalan. Tambahkan mapel tersebut ke salah satu periode terlebih dahulu.',
                ]);
            }

            $slugBase = Str::slug($settings['title']) ?: 'uji-coba';
            $slug = $slugBase;
            while (TrialExam::query()->where('slug', $slug)->exists()) {
                $slug = $slugBase.'-'.Str::lower(Str::random(6));
            }

            TrialExam::create([
                ...$settings,
                'assessment_subject_id' => $component->id,
                'trial_question_bank_id' => $bank->id,
                'slug' => $slug,
            ]);
        });

        return back()->with('status', 'Ujian uji coba dibuat. Tautan dan kode sudah dapat dibagikan.');
    }

    public function edit(TrialExam $trialExam): View
    {
        $trialExam->load('questionBank.subject');

        return view('tryouts.edit', [
            'trialExam' => $trialExam,
            'trialLocations' => TrialLocation::query()->orderBy('name')->get(),
            'hasCheckins' => $trialExam->checkins()->exists(),
        ]);
    }

    public function update(Request $request, TrialExam $trialExam): RedirectResponse
    {
        $settings = $this->validateSettings($request, $trialExam);

        try {
            DB::transaction(function () use ($trialExam, $settings): void {
                // Starting an attempt takes the same bank lock before reading these settings.
                $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($trialExam->trial_question_bank_id);
                $exam = TrialExam::query()->lockForUpdate()->findOrFail($trialExam->id);
                if ($settings['is_active'] && $bank->archived_at) {
                    throw ValidationException::withMessages(['is_active' => 'Pulihkan bank soal sebelum mengaktifkan ujian.']);
                }

                $settings['trial_location_id'] = $this->requiredLocationId($settings['trial_location_id'] ?? $exam->trial_location_id);
                if ($settings['trial_location_id'] !== (int) $exam->trial_location_id
                    && $exam->checkins()->exists()) {
                    throw ValidationException::withMessages(['trial_location_id' => 'Lokasi ujian tidak dapat diganti setelah ada absensi simulasi.']);
                }

                $exam->update($settings);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['access_code' => 'Kode bersama sudah dipakai oleh ujian lain.']);
        }

        return redirect()->route('tryouts.edit', $trialExam)->with('status', 'Pengaturan Ujian Uji Coba disimpan.');
    }

    private function validateSettings(Request $request, ?TrialExam $trialExam = null): array
    {
        $code = $request->input('access_code');
        if (is_string($code)) {
            $request->merge(['access_code' => Str::upper(trim($code))]);
        }

        $uniqueCode = Rule::unique('trial_exams', 'access_code');
        if ($trialExam) {
            $uniqueCode->ignore($trialExam);
        }
        $settings = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'access_code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/', $uniqueCode],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:300'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', Rule::when($request->filled('starts_at'), ['after:starts_at'])],
            'is_active' => ['nullable', 'boolean'],
            'trial_location_id' => ['sometimes', 'required', 'integer', 'exists:trial_locations,id'],
        ], [
            'trial_location_id.required' => 'Pilih lokasi GPS untuk Ujian Uji Coba.',
        ]);

        return [
            ...$settings,
            'starts_at' => $settings['starts_at'] ?? null,
            'ends_at' => $settings['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function requiredLocationId(int|string|null $locationId): int
    {
        if ($locationId !== null) {
            return (int) $locationId;
        }

        $locations = TrialLocation::query()->orderBy('id')->limit(2)->pluck('id');
        if ($locations->count() === 1) {
            return $locations->first();
        }

        throw ValidationException::withMessages(['trial_location_id' => 'Pilih lokasi GPS untuk Ujian Uji Coba. Tambahkan lokasi simulasi jika belum tersedia.']);
    }

    public function restartParticipant(
        Request $request,
        TrialExam $trialExam,
        TrialAttempt $trialAttempt,
        TrialExamAttemptService $attempts,
    ): RedirectResponse {
        abort_unless($this->canRestart($request->user()), 403);
        abort_unless($trialAttempt->trial_exam_id === $trialExam->id, 404);
        $attempts->restart($trialExam, $trialAttempt, $request->user());

        return back()->with('status', 'Peserta sudah direset dan bisa mulai lagi saat simulasi dibuka.');
    }

    public function resetViolations(
        Request $request,
        TrialExam $trialExam,
        TrialAttempt $trialAttempt,
        TrialExamSecurityService $security,
    ): RedirectResponse {
        abort_unless($trialAttempt->trial_exam_id === $trialExam->id, 404);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $security->resetViolations($trialAttempt, $request->user(), $validated['reason']);

        return back()->with('status', 'Pelanggaran peserta uji coba direset. Waktu dan jawaban tetap berjalan.');
    }

    public function toggle(TrialExam $trialExam): RedirectResponse
    {
        DB::transaction(function () use ($trialExam): void {
            $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($trialExam->trial_question_bank_id);
            if ($bank->archived_at) {
                throw ValidationException::withMessages(['bank' => 'Pulihkan bank soal dari bagian Dihapus sebelum mengaktifkan ujian.']);
            }
            $trialExam->refresh();
            $trialExam->update($trialExam->is_active
                ? ['is_active' => false]
                : ['is_active' => true, 'trial_location_id' => $this->requiredLocationId($trialExam->trial_location_id)]);
        });

        return back()->with('status', $trialExam->is_active ? 'Ujian uji coba diaktifkan.' : 'Ujian uji coba dinonaktifkan.');
    }

    public function destroy(TrialExam $trialExam, TrialExamAttemptService $attempts): RedirectResponse
    {
        DB::transaction(function () use ($trialExam, $attempts): void {
            TrialQuestionBank::query()->lockForUpdate()->findOrFail($trialExam->trial_question_bank_id);
            $exam = TrialExam::query()->lockForUpdate()->findOrFail($trialExam->id);
            $hasRunningAttempt = $exam->attempts()->where('status', 'in_progress')
                ->lockForUpdate()->lazyById(100)
                ->contains(fn (TrialAttempt $attempt): bool => ! $attempts->isExpired($attempt->setRelation('trialExam', $exam)));
            if ($hasRunningAttempt) {
                throw ValidationException::withMessages([
                    'trial_exam' => 'Masih ada peserta yang sedang mengerjakan. Tunggu sampai mereka mengumpulkan jawaban atau waktu pengerjaannya habis, lalu hapus konfigurasi.',
                ]);
            }

            $exam->checkins()->delete();
            $exam->delete();
        }, 3);

        return back()->with('status', 'Konfigurasi ujian uji coba dihapus. Bank soal dapat digunakan kembali.');
    }

    public function attendanceReport(TrialExam $trialExam): Response
    {
        $trialExam->load(['questionBank.subject', 'location']);
        abort_unless($trialExam->location, 404);

        $checkins = $trialExam->checkins()->with('student')->get()->keyBy('student_id');
        $students = $this->activeRoster([$trialExam->questionBank->grade_level])->get()
            ->merge($checkins->pluck('student')->filter())
            ->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE);

        $rows = $students->map(function (Student $student) use ($checkins): array {
            $checkin = $checkins->get($student->id);

            return [
                'student' => $student,
                'class' => $checkin?->class_name ?? $student->schoolClass?->name ?? '—',
                'checkin' => $checkin,
            ];
        });

        return response()->view('tryouts.attendance-report', [
            'school' => SchoolProfile::query()->first(),
            'trialExam' => $trialExam,
            'presentCount' => $rows->whereNotNull('checkin')->count(),
            'total' => $rows->count(),
            'classes' => $rows->groupBy('class')->sortKeys(SORT_NATURAL),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function participantRows(TrialExam $trialExam, User $user, Collection $attempts): Collection
    {
        $students = $this->activeRoster([$trialExam->questionBank->grade_level])
            ->when($user->role === UserRole::Teacher, fn ($query) => $query->whereIn('school_class_id', AssessmentSubject::query()
                ->where('assessment_period_id', $trialExam->assessmentSubject->assessment_period_id)
                ->where('subject_id', $trialExam->assessmentSubject->subject_id)
                ->where('teacher_user_id', $user->id)->select('school_class_id')))
            ->get();

        return $this->matchParticipants($students, $attempts);
    }

    private function activeRoster(array $gradeLevels): Builder
    {
        return Student::query()
            ->with('schoolClass')
            ->where('is_active', true)
            ->whereHas('schoolClass', fn ($query) => $query
                ->whereIn('grade_level', $gradeLevels)
                ->whereHas('academicYear', fn ($year) => $year->where('is_active', true)))
            ->orderBy('full_name');
    }

    private function matchParticipants(Collection $students, Collection $attempts): Collection
    {
        $byStudent = $attempts->whereNotNull('student_id')->keyBy('student_id');
        $byIdentifier = $attempts->whereNull('student_id')->whereNotNull('participant_identifier')->keyBy('participant_identifier');

        return $students->map(fn (Student $student): array => $this->participantRow($student,
            $byStudent->get($student->id) ?? $byIdentifier->get($student->nisn) ?? $byIdentifier->get($student->student_number),
        ));
    }

    private function examsByGradeAndSubject(Collection $exams): Collection
    {
        return $exams->sortBy([
            ['questionBank.grade_level', 'asc'],
            ['questionBank.subject.name', 'asc'],
            ['title', 'asc'],
        ])->values();
    }

    private function participantRow(?Student $student, ?TrialAttempt $attempt): array
    {
        return [
            'student' => $student,
            'attempt' => $attempt,
            'name' => $student?->full_name ?? $attempt?->participant_name,
            'status' => match (true) {
                $attempt === null => 'not_started',
                $attempt->status === 'in_progress' && $attempt->security_locked_at !== null => 'locked',
                default => $attempt->status,
            },
        ];
    }

    private function participantStatistics(Collection $rows): array
    {
        $counts = $rows->countBy('status');

        return [
            'total' => $rows->count(),
            'not_started' => $counts->get('not_started', 0),
            'in_progress' => $counts->get('in_progress', 0),
            'locked' => $counts->get('locked', 0),
            'submitted' => $counts->get('submitted', 0),
        ];
    }

    private function canPrintScores(User $user): bool
    {
        return $this->canRestart($user) || $user->role === UserRole::Proctor;
    }

    private function canRestart(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Committee, UserRole::Principal], true);
    }

    private function canGrade(User $user): bool
    {
        return $this->canRestart($user) || $user->role === UserRole::Teacher;
    }

    private function hasWrittenAnswersToGrade(?TrialAttempt $attempt): bool
    {
        return $attempt?->status === 'submitted'
            && in_array($attempt->grading_status, [GradingStatus::Pending, GradingStatus::Graded], true);
    }

    private function authorizeGrading(Request $request, TrialExam $exam, TrialAttempt $attempt): void
    {
        abort_unless($this->canGrade($request->user()), 403);
        $this->authorizeMonitor($request, $exam);
        abort_unless($attempt->trial_exam_id === $exam->id && $this->hasWrittenAnswersToGrade($attempt), 404);
    }

    private function authorizeMonitor(Request $request, TrialExam $trialExam): void
    {
        if ($request->user()->role === UserRole::Teacher) {
            abort_unless($trialExam->assessmentSubject?->teacher_user_id === $request->user()->id, 403);
        }
    }
}
