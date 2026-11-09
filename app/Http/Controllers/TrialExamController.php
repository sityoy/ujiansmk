<?php

namespace App\Http\Controllers;

use App\Models\AssessmentSubject;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use App\Models\TrialQuestionBank;
use App\Enums\UserRole;
use App\Services\Exams\TrialExamSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
                ->withCount('attempts')
                ->latest()
                ->paginate(10),
            'banks' => TrialQuestionBank::query()
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
        if ($request->user()->role === UserRole::Teacher) {
            abort_unless($trialExam->assessmentSubject->teacher_user_id === $request->user()->id, 403);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'locked', 'submitted'])],
        ]);
        $students = Student::query()
            ->with('schoolClass')
            ->where('is_active', true)
            ->whereHas('schoolClass', fn ($query) => $query
                ->where('grade_level', $trialExam->questionBank->grade_level)
                ->whereHas('academicYear', fn ($year) => $year->where('is_active', true)))
            ->orderBy('full_name')
            ->get();
        $attempts = $trialExam->attempts()
            ->withCount('securityIncidents')
            ->get();
        $byStudent = $attempts->whereNotNull('student_id')->keyBy('student_id');
        $byIdentifier = $attempts->whereNotNull('participant_identifier')->keyBy('participant_identifier');

        $rows = $students->map(function (Student $student) use ($byStudent, $byIdentifier): array {
            $attempt = $byStudent->get($student->id)
                ?? $byIdentifier->get($student->nisn)
                ?? $byIdentifier->get($student->student_number);

            return ['student' => $student, 'attempt' => $attempt];
        });
        $statistics = [
            'total' => $rows->count(),
            'not_started' => $rows->where('attempt', null)->count(),
            'in_progress' => $rows->filter(fn (array $row): bool => $row['attempt']?->status === 'in_progress'
                && ! $row['attempt']?->security_locked_at)->count(),
            'locked' => $rows->filter(fn (array $row): bool => $row['attempt']?->status === 'in_progress'
                && (bool) $row['attempt']?->security_locked_at)->count(),
            'submitted' => $rows->filter(fn (array $row): bool => $row['attempt']?->status === 'submitted')->count(),
        ];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $rows = $rows->filter(fn (array $row): bool =>
                str_contains(mb_strtolower($row['student']->full_name), mb_strtolower($search))
                || str_contains((string) $row['student']->student_number, $search)
                || str_contains((string) $row['student']->nisn, $search)
            );
        }
        if ($status = $filters['status'] ?? null) {
            $rows = $rows->filter(function (array $row) use ($status): bool {
                $attempt = $row['attempt'];
                if ($status === 'not_started') {
                    return $attempt === null;
                }
                if ($status === 'locked') {
                    return $attempt?->status === 'in_progress' && (bool) $attempt?->security_locked_at;
                }

                return $attempt?->status === $status
                    && ($status !== 'in_progress' || ! $attempt?->security_locked_at);
            });
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $participants = new LengthAwarePaginator(
            $rows->forPage($page, 20)->values(),
            $rows->count(),
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('tryouts.participants', compact(
            'trialExam', 'participants', 'statistics', 'filters'
        ));
    }

    public function cards(TrialExam $trialExam): View
    {
        $trialExam->load('questionBank.subject');
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
        $trialExam->update(['is_active' => ! $trialExam->is_active]);

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
}
