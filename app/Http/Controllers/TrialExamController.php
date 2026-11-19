<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AssessmentSubject;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use App\Models\TrialQuestionBank;
use App\Models\User;
use App\Services\Exams\TrialExamAttemptService;
use App\Services\Exams\TrialExamSecurityService;
use App\Support\TablePagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
                ->with(['questionBank.subject', 'assessmentSubject.assessmentPeriod.academicYear'])
                ->withCount('currentAttempts as attempts_count')
                ->latest()
                ->paginateForDisplay(),
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
        ]);
    }

    public function switchParticipants(Request $request): RedirectResponse
    {
        $validated = $request->validate(['exam' => ['required', 'string', 'max:255']]);
        $exam = TrialExam::query()->visibleToMonitor($request->user())
            ->where('slug', $validated['exam'])->firstOrFail();

        return redirect()->route('tryouts.participants', $exam);
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
        $participants = $rows
            ->filter(fn (array $row): bool => $requested === null || isset($requested[$row['student']->id]))
            ->mapWithKeys(function (array $row) use ($canRestart, $trialExam): array {
                $attempt = $row['attempt'];

                return [(string) $row['student']->id => [
                    'status' => $row['status'],
                    'started_at' => $attempt?->started_at?->format('d/m/Y H:i:s'),
                    'submitted_at' => $attempt?->submitted_at?->format('d/m/Y H:i:s'),
                    'violations' => $attempt?->violation_count,
                    'reset_used' => (int) ($attempt?->security_reset_count ?? 0),
                    'incidents' => $attempt?->security_incidents_count,
                    ...($canRestart ? ['restart_url' => $attempt ? route('tryouts.attempts.restart', [$trialExam, $attempt]) : null] : []),
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

    public function cards(TrialExam $trialExam): View
    {
        $trialExam->load('questionBank.subject');
        abort_if($trialExam->questionBank->archived_at !== null, 404);
        $students = Student::query()
            ->with('schoolClass.academicYear')
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

        return view('tryouts.cards', [
            'school' => SchoolProfile::query()->first(),
            'trialExam' => $trialExam,
            'students' => $students,
        ]);
    }

    public function allCards(): View
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
            ->with('schoolClass.academicYear')
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

        return view('tryouts.all-cards', [
            'school' => SchoolProfile::query()->first(),
            'trialExams' => $trialExams,
            'trialsByGrade' => $trialExams->groupBy('questionBank.grade_level'),
            'students' => $students,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['access_code' => Str::upper(trim((string) $request->input('access_code')))]);
        $validated = $request->validate([
            'trial_question_bank_id' => [
                'required',
                'integer',
                'exists:trial_question_banks,id',
                Rule::unique('trial_exams', 'trial_question_bank_id'),
            ],
            'title' => ['required', 'string', 'max:150'],
            'access_code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/', Rule::unique('trial_exams', 'access_code')],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:300'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
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

            $slugBase = Str::slug($validated['title']) ?: 'uji-coba';
            $slug = $slugBase;
            while (TrialExam::query()->where('slug', $slug)->exists()) {
                $slug = $slugBase.'-'.Str::lower(Str::random(6));
            }

            TrialExam::create([
                ...$validated,
                'assessment_subject_id' => $component->id,
                'trial_question_bank_id' => $bank->id,
                'slug' => $slug,
                'is_active' => $request->boolean('is_active'),
            ]);
        });

        return back()->with('status', 'Ujian uji coba dibuat. Tautan dan kode sudah dapat dibagikan.');
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
            $trialExam->refresh()->update(['is_active' => ! $trialExam->is_active]);
        });

        return back()->with('status', $trialExam->is_active ? 'Ujian uji coba diaktifkan.' : 'Ujian uji coba dinonaktifkan.');
    }

    public function destroy(TrialExam $trialExam): RedirectResponse
    {
        if ($trialExam->attempts()->exists()) {
            return back()->withErrors(['trial_exam' => 'Ujian uji coba yang sudah memiliki peserta tidak dapat dihapus. Nonaktifkan saja agar riwayat tetap tersimpan.']);
        }

        $trialExam->delete();

        return back()->with('status', 'Ujian uji coba dihapus.');
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

    private function authorizeMonitor(Request $request, TrialExam $trialExam): void
    {
        if ($request->user()->role === UserRole::Teacher) {
            abort_unless($trialExam->assessmentSubject?->teacher_user_id === $request->user()->id, 403);
        }
    }
}
