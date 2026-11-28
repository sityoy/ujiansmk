<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentType;
use App\Enums\AssignmentStatus;
use App\Enums\PeriodStatus;
use App\Enums\Semester;
use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Campus;
use App\Models\ExamAssignment;
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Exams\CoreQuestionBank;
use App\Services\Exams\ExamAssignmentService;
use App\Services\Exams\ExamAttemptResetService;
use App\Services\Exams\ExamSessionService;
use App\Support\TablePagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SchedulingController extends Controller
{
    public function __construct(private readonly CoreQuestionBank $questionBanks) {}

    public function index(Request $request): View
    {
        $sessionPeriodId = $request->integer('session_period_id') ?: null;
        $sessionClassId = $request->integer('session_class_id') ?: null;
        $sessionSearch = trim((string) $request->query('session_search'));

        $catalog = AssessmentSubject::query()
            ->with([
                'assessmentPeriod.academicYear',
                'subject',
                'schoolClass', 'teacher',
                'examSessions' => fn ($query) => $query
                    ->with(['campus', 'assessmentSubject.schoolClass'])
                    ->withExists('makeupSessions')
                    ->withCount([
                        'assignments',
                        'assignments as finished_count' => fn ($assignments) => $assignments->whereHas('attempt', fn ($attempts) => $attempts->where('status', 'submitted')),
                        'assignments as working_count' => fn ($assignments) => $assignments->whereHas('attempt', fn ($attempts) => $attempts->where('status', 'in_progress')),
                        'assignments as pending_count' => fn ($assignments) => $assignments->whereDoesntHave('attempt')->where('status', '!=', 'cancelled'),
                        'assignments as deletion_blocker_count' => $this->withUnresetAttempts(...),
                        'assignments as reset_participant_count' => fn ($assignments) => $assignments->whereHas('attemptResets'),
                    ])
                    ->orderBy('starts_at'),
            ])
            ->withCount(['assignments', 'questions'])
            ->when($sessionPeriodId, fn ($query) => $query->where('assessment_period_id', $sessionPeriodId))
            ->when($sessionSearch, fn ($query) => $query->whereHas(
                'subject',
                fn ($query) => $query->where('name', 'like', '%'.$sessionSearch.'%')
                    ->orWhere('code', 'like', '%'.$sessionSearch.'%'),
            ))
            ->latest()
            ->get()
            ->groupBy(fn (AssessmentSubject $component): string => implode('|', [
                $component->assessment_period_id, $component->subject_id, $component->schoolClass->grade_level,
            ]))
            ->filter(fn ($group): bool => ! $sessionClassId || $group->contains('school_class_id', $sessionClassId))
            ->map(function ($group): AssessmentSubject {
                $representative = clone $group->first();
                $representative->setRelation('scheduledClasses', $group->sortBy('schoolClass.name', SORT_NATURAL)->values());
                $representative->setAttribute('grade_level', $representative->schoolClass->grade_level);

                return $representative;
            })->values();
        $components = TablePagination::collection($catalog, 'session_page');

        return view('scheduling.index', [
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'campuses' => Campus::query()
                ->withCount(['examSessions', 'dailyCheckins'])
                ->orderBy('name')
                ->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'teachers' => User::query()->where('role', UserRole::Teacher)->where('is_active', true)->orderBy('name')->get(),
            'classes' => SchoolClass::query()->with('academicYear')->orderBy('name')->get(),
            'periods' => AssessmentPeriod::query()
                ->with('academicYear')
                ->withCount('assessmentSubjects')
                ->orderByDesc('starts_on')
                ->get(),
            'components' => $components,
            'movableAssignments' => ExamAssignment::query()
                ->with(['student.schoolClass', 'assessmentSubject.subject'])
                ->whereIn('status', [AssignmentStatus::Scheduled, AssignmentStatus::Absent])
                ->orderByDesc('assigned_at')
                ->get(),
            'makeupSessions' => ExamSession::query()
                ->with(['assessmentSubject.subject', 'assessmentSubject.schoolClass'])
                ->where('kind', SessionKind::Makeup)
                ->whereIn('status', [SessionStatus::Draft, SessionStatus::Published, SessionStatus::Active])
                ->orderBy('starts_at')
                ->get(),
            'assessmentTypes' => AssessmentType::cases(),
            'semesters' => Semester::cases(),
            'periodStatuses' => PeriodStatus::cases(),
            'sessionStatuses' => SessionStatus::cases(),
            'sessionPeriodId' => $sessionPeriodId,
            'sessionClassId' => $sessionClassId,
            'sessionSearch' => $sessionSearch,
        ]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
        ]);

        $validated = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('assessment_periods')->where(
                    fn ($query) => $query->where('academic_year_id', $request->integer('academic_year_id')),
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AssessmentType::class)],
            'semester' => ['required', Rule::enum(Semester::class)],
            'sequence_no' => ['nullable', 'integer', 'between:1,20'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        AssessmentPeriod::create([...$validated, 'status' => PeriodStatus::Draft]);

        return back()->with('status', 'Periode asesmen berhasil dibuat.');
    }

    public function updatePeriod(Request $request, AssessmentPeriod $assessmentPeriod): RedirectResponse
    {
        $dates = $request->validateWithBag('periodDates', [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ], [
            'starts_on.required' => 'Isi tanggal mulai periode.',
            'ends_on.required' => 'Isi tanggal akhir periode.',
            'ends_on.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
        ]);
        $assessmentPeriod->update($dates);

        return back()->with('status', 'Tanggal periode asesmen berhasil diperbarui.');
    }

    public function updatePeriodStatus(Request $request, AssessmentPeriod $assessmentPeriod): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(PeriodStatus::class)],
        ]);

        $assessmentPeriod->update($validated);

        return back()->with('status', 'Status periode berhasil diperbarui.');
    }

    public function destroyPeriod(AssessmentPeriod $assessmentPeriod): RedirectResponse
    {
        if ($assessmentPeriod->assessmentSubjects()->exists()) {
            return back()->withErrors([
                'period' => 'Periode tidak dapat dihapus karena sudah memiliki mapel, kelas, atau jadwal ujian.',
            ]);
        }

        return $this->deleteSafely(
            fn () => $assessmentPeriod->delete(),
            'Periode asesmen berhasil dihapus.',
            'Periode tidak dapat dihapus karena sudah memiliki jadwal atau hasil ujian.',
        );
    }

    public function storeComponent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assessment_period_id' => ['required', 'exists:assessment_periods,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', UserRole::Teacher->value)
                    ->where('is_active', true)),
            ],
            'school_class_id' => [
                'required',
                'exists:school_classes,id',
                Rule::unique('assessment_subjects')->where(fn ($query) => $query
                    ->where('assessment_period_id', $request->integer('assessment_period_id'))
                    ->where('subject_id', $request->integer('subject_id'))),
            ],
        ]);

        $this->assertComponentAcademicYear($validated);

        DB::transaction(function () use ($validated): void {
            AssessmentPeriod::query()->lockForUpdate()->findOrFail($validated['assessment_period_id']);
            $component = AssessmentSubject::create($validated);
            $this->synchronizeQuestionBank($component);
        });

        return back()->with('status', 'Mapel dan kelas berhasil dimasukkan ke periode asesmen.');
    }

    public function updateComponent(
        Request $request,
        AssessmentSubject $assessmentSubject,
    ): RedirectResponse {
        $validated = $request->validate([
            'assessment_period_id' => ['required', 'exists:assessment_periods,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'school_class_id' => [
                'required',
                'exists:school_classes,id',
                Rule::unique('assessment_subjects')->where(fn ($query) => $query
                    ->where('assessment_period_id', $request->integer('assessment_period_id'))
                    ->where('subject_id', $request->integer('subject_id')))
                    ->ignore($assessmentSubject->id),
            ],
        ]);

        $this->assertComponentAcademicYear($validated);
        DB::transaction(function () use ($assessmentSubject, $validated): void {
            AssessmentPeriod::query()
                ->whereKey([$assessmentSubject->assessment_period_id, (int) $validated['assessment_period_id']])
                ->orderBy('id')->lockForUpdate()->get();
            $component = AssessmentSubject::query()->lockForUpdate()->findOrFail($assessmentSubject->id);
            $component->fill($validated);

            if ($component->isDirty(['assessment_period_id', 'subject_id', 'school_class_id'])) {
                if ($component->examSessions()->exists()
                    || $component->assignments()->exists()
                    || $component->questions()->exists()) {
                    throw ValidationException::withMessages([
                        'component' => 'Periode, mapel, atau kelas tidak dapat diubah setelah komponen memiliki sesi, peserta, atau soal.',
                    ]);
                }

                $component->save();
                $this->synchronizeQuestionBank($component);
            }
        });

        return back()->with('status', 'Komponen asesmen berhasil diperbarui.');
    }

    public function updateComponentTeacher(Request $request, AssessmentSubject $assessmentSubject): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', UserRole::Teacher->value)
                    ->where('is_active', true)),
            ],
        ]);
        DB::transaction(function () use ($assessmentSubject, $validated): void {
            AssessmentPeriod::query()->lockForUpdate()->findOrFail($assessmentSubject->assessment_period_id);
            $component = AssessmentSubject::query()->lockForUpdate()->findOrFail($assessmentSubject->id);
            $component->teacher_user_id = $validated['teacher_user_id'] ?? null;
            if ($component->isDirty('teacher_user_id')) {
                $component->save();
                $this->synchronizeQuestionBank($component);
            }
        });

        return back()->with('status', 'Guru pengoreksi berhasil diperbarui.');
    }

    private function synchronizeQuestionBank(AssessmentSubject $component): void
    {
        if ($component->teacher_user_id !== null) {
            $group = $component->questionBankGroup()->orderBy('id')->lockForUpdate()->get();
            $this->questionBanks->synchronize($group, 'component');
        }
    }

    public function destroyComponent(AssessmentSubject $assessmentSubject): RedirectResponse
    {
        if ($assessmentSubject->examSessions()->exists() || $assessmentSubject->questions()->exists()) {
            return back()->withErrors([
                'component' => 'Komponen tidak dapat dihapus karena sudah memiliki sesi atau bank soal.',
            ]);
        }

        return $this->deleteSafely(
            fn () => $assessmentSubject->delete(),
            'Komponen asesmen berhasil dihapus.',
            'Komponen tidak dapat dihapus karena ujian sudah memiliki aktivitas atau nilai.',
        );
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $identity = $request->validate([
            'assessment_subject_id' => ['required', 'exists:assessment_subjects,id'],
            'scope' => ['nullable', Rule::in(['class', 'grade'])],
            'campus_id' => ['required', Rule::exists('campuses', 'id')->where('is_active', true)],
            'rooms' => ['nullable', 'array'],
            'rooms.*' => ['nullable', 'string', 'max:100'],
        ]);
        $component = AssessmentSubject::query()->findOrFail($identity['assessment_subject_id']);
        $wholeGrade = ($identity['scope'] ?? 'class') === 'grade';

        DB::transaction(function () use ($request, $component, $wholeGrade): void {
            Campus::query()->lockForUpdate()->findOrFail($request->integer('campus_id'));
            $period = AssessmentPeriod::query()->lockForUpdate()->findOrFail($component->assessment_period_id);
            $component->refresh();
            if ($component->assessment_period_id !== $period->id) {
                throw ValidationException::withMessages([
                    'assessment_subject_id' => 'Periode komponen baru saja berubah. Muat ulang Penjadwalan Ujian dan pilih kembali mapel.',
                ]);
            }
            $components = $wholeGrade
                ? AssessmentSubject::query()->where('assessment_period_id', $component->assessment_period_id)
                    ->where('subject_id', $component->subject_id)
                    ->whereHas('schoolClass', fn ($classes) => $classes->where('grade_level', $component->schoolClass->grade_level))
                    ->orderBy('id')->lockForUpdate()->get()
                : AssessmentSubject::query()->whereKey($component->id)->lockForUpdate()->get();
            SchoolClass::query()->whereKey($components->pluck('school_class_id'))
                ->orderBy('id')->lockForUpdate()->get();
            $groupId = $wholeGrade ? (string) Str::uuid() : null;
            $created = new Collection;
            foreach ($components as $member) {
                $data = $wholeGrade ? $this->sessionRequestForClass($request, $member) : $request;
                $validated = $this->validatedSessionData($data, $member);
                if ($wholeGrade && $validated['status'] === SessionStatus::Closed->value) {
                    throw ValidationException::withMessages(['status' => 'Buat jadwal sebagai draf, terbit, atau aktif terlebih dahulu.']);
                }
                $created->push(ExamSession::create([
                    ...$validated,
                    'assessment_subject_id' => $member->id,
                    'schedule_group_id' => $groupId,
                ]));
            }

            if ($wholeGrade && $created->first()->kind === SessionKind::Regular) {
                $this->assignStudents($created, app(ExamAssignmentService::class));
            }
        }, 3);

        return back()->with('status', $wholeGrade
            ? 'Jadwal bersama berhasil dibuat. Siswa aktif yang belum memiliki penugasan ditempatkan ke sesi reguler masing-masing rombel.'
            : 'Sesi ujian berhasil dijadwalkan.');
    }

    public function updateSession(Request $request, ExamSession $examSession): RedirectResponse
    {
        $request->validate([
            'campus_id' => ['required', Rule::exists('campuses', 'id')->where('is_active', true)],
            'rooms' => ['nullable', 'array'], 'rooms.*' => ['nullable', 'string', 'max:100'],
        ]);
        DB::transaction(function () use ($request, $examSession): void {
            Campus::query()->whereKey([$examSession->campus_id, $request->integer('campus_id')])
                ->orderBy('id')->lockForUpdate()->get();
            $examSession->loadMissing('assessmentSubject');
            AssessmentPeriod::query()->lockForUpdate()->findOrFail($examSession->assessmentSubject->assessment_period_id);
            $group = $examSession->scheduleGroup()->with('assessmentSubject')->orderBy('id')->lockForUpdate()->get();
            SchoolClass::query()->whereKey($group->pluck('assessmentSubject.school_class_id'))
                ->orderBy('id')->lockForUpdate()->get();
            $attributes = [];
            $rooms = [];
            foreach ($group as $session) {
                $data = $this->sessionRequestForClass($request, $session->assessmentSubject, $session);
                $validated = $this->validatedSessionData($data, $session->assessmentSubject, $session, $group->modelKeys());
                $this->assertSessionUpdate($session, $validated);
                $room = Str::lower($validated['room_name'] ?? '');
                if ($room !== '' && in_array($room, $rooms, true)) {
                    throw ValidationException::withMessages(['rooms' => 'Gunakan ruangan berbeda untuk rombel yang mengikuti jadwal bersamaan.']);
                }
                $rooms[] = $room;
                $attributes[$session->id] = $validated;
            }

            app(ExamSessionService::class)->update($examSession, [], $attributes);
        }, 3);

        return back()->with('status', 'Jadwal seluruh rombel diperbarui. Durasi dihitung dari waktu mulai dan selesai.');
    }

    private function sessionRequestForClass(Request $request, AssessmentSubject $component, ?ExamSession $session = null): Request
    {
        $data = clone $request;
        $source = $request->integer('source_session_id') ? ExamSession::findOrFail($request->integer('source_session_id')) : null;
        $classSource = $source?->scheduleGroup()->where('assessment_subject_id', $component->id)->lockForUpdate()->first();
        $data->merge([
            'room_name' => $request->input('rooms.'.$component->id, $request->input('room_name', $session?->room_name)),
            'source_session_id' => $classSource?->id,
        ]);

        return $data;
    }

    private function assertSessionUpdate(ExamSession $session, array $validated): void
    {
        if ($session->kind->value !== $validated['kind']
            && ($session->assignments()->exists() || ExamSession::query()->where('source_session_id', $session->id)->exists())) {
            throw ValidationException::withMessages([
                'kind' => 'Jenis sesi tidak dapat diubah setelah memiliki peserta atau menjadi sumber sesi susulan.',
            ]);
        }

        $assignedStudentIds = $session->assignments()->pluck('student_id');
        if ($assignedStudentIds->isNotEmpty() && ExamAssignment::query()
            ->whereIn('student_id', $assignedStudentIds)
            ->where('exam_session_id', '!=', $session->id)
            ->whereIn('status', [AssignmentStatus::Scheduled, AssignmentStatus::Started])
            ->whereHas('examSession', fn ($query) => $query
                ->where('starts_at', '<', $validated['ends_at'])->where('ends_at', '>', $validated['starts_at']))->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => 'Perubahan ditolak karena ada peserta yang mempunyai ujian lain pada waktu tersebut.',
            ]);
        }
    }

    private function validatedSessionData(
        Request $request,
        AssessmentSubject $component,
        ?ExamSession $currentSession = null,
        array $excludedSessionIds = [],
    ): array {
        $validated = $request->validate([
            'campus_id' => ['required', Rule::exists('campuses', 'id')->where('is_active', true)],
            'kind' => ['required', Rule::enum(SessionKind::class)],
            'source_session_id' => ['nullable', 'exists:exam_sessions,id'],
            'room_name' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(SessionStatus::class)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'required_without:duration_minutes', 'date', 'after:starts_at'],
            'duration_minutes' => [
                Rule::excludeIf($request->filled('ends_at')),
                'required_without:ends_at',
                'integer',
                'between:'.ExamSession::MIN_DURATION_MINUTES.','.ExamSession::MAX_DURATION_MINUTES,
            ],
        ], [
            'ends_at.required_without' => 'Isi tanggal dan jam selesai.',
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);

        $sourceSession = ! empty($validated['source_session_id'])
            ? ExamSession::query()->lockForUpdate()->findOrFail($validated['source_session_id'])
            : null;
        $timezone = config('app.timezone');
        $startsAt = Carbon::parse($validated['starts_at'], $timezone)->setTimezone($timezone);
        // Older requests supplied a duration instead of an end time.
        $explicitEnd = isset($validated['ends_at']);
        $endsAt = $explicitEnd
            ? Carbon::parse($validated['ends_at'], $timezone)->setTimezone($timezone)
            : $startsAt->copy()->addMinutes((int) $validated['duration_minutes']);
        $seconds = $endsAt->getTimestamp() - $startsAt->getTimestamp();
        $duration = intdiv($seconds, 60);
        $isMakeup = $validated['kind'] === SessionKind::Makeup->value;

        if (! $startsAt->isSameDay($endsAt)) {
            throw ValidationException::withMessages([
                $explicitEnd ? 'ends_at' : 'duration_minutes' => 'Tanggal selesai harus sama dengan tanggal mulai karena absensi tercatat per hari.',
            ]);
        }

        if ($seconds % 60 !== 0 || $duration < ExamSession::MIN_DURATION_MINUTES || $duration > ExamSession::MAX_DURATION_MINUTES) {
            throw ValidationException::withMessages([
                'ends_at' => 'Selisih waktu mulai dan selesai harus '
                    .ExamSession::MIN_DURATION_MINUTES.'–'.ExamSession::MAX_DURATION_MINUTES.' menit penuh.',
            ]);
        }

        $classConflict = ExamSession::query()->lockForUpdate()
            ->whereNotIn('id', $excludedSessionIds)
            ->when($currentSession, fn ($query) => $query->where('id', '!=', $currentSession->id))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->whereHas('assessmentSubject', fn ($query) => $query
                ->where('school_class_id', $component->school_class_id))
            ->exists();

        if ($classConflict) {
            throw ValidationException::withMessages([
                'starts_at' => 'Kelas tersebut sudah memiliki ujian lain pada waktu yang bertabrakan.',
            ]);
        }

        if (! empty($validated['room_name'])) {
            $overlappingRoomSessions = ExamSession::query()->lockForUpdate()
                ->whereNotIn('id', $excludedSessionIds)
                ->when($currentSession, fn ($query) => $query->where('id', '!=', $currentSession->id))
                ->where('campus_id', $validated['campus_id'])
                ->whereRaw('LOWER(room_name) = ?', [Str::lower(trim($validated['room_name']))])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt);

            if ($this->hasRoomConflict($overlappingRoomSessions, $component, $isMakeup)) {
                throw ValidationException::withMessages([
                    'room_name' => 'Ruangan tersebut sudah dipakai oleh sesi lain pada waktu yang sama.',
                ]);
            }
        }

        if ($isMakeup) {
            if (
                ! $sourceSession
                || ($currentSession && $sourceSession->is($currentSession))
                || $sourceSession->kind !== SessionKind::Regular
                || $sourceSession->assessment_subject_id !== $component->id
            ) {
                throw ValidationException::withMessages([
                    'source_session_id' => 'Sesi susulan harus merujuk sesi reguler dari mapel dan kelas yang sama.',
                ]);
            }

            if ($startsAt->lt($sourceSession->ends_at)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Sesi susulan harus dimulai setelah sesi reguler selesai.',
                ]);
            }
        } else {
            $validated['source_session_id'] = null;

            if ($currentSession && ExamSession::query()
                ->where('source_session_id', $currentSession->id)
                ->where('starts_at', '<', $endsAt)
                ->exists()) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Sesi reguler harus selesai sebelum seluruh sesi susulannya dimulai.',
                ]);
            }
        }

        $validated['room_name'] = filled($validated['room_name'] ?? null)
            ? trim($validated['room_name'])
            : null;
        $validated['starts_at'] = $startsAt;
        $validated['ends_at'] = $endsAt;
        $validated['duration_minutes'] = $duration;

        return $validated;
    }

    private function hasRoomConflict(Builder $overlappingSessions, AssessmentSubject $component, bool $isMakeup): bool
    {
        // A trailing variant label identifies parallel versions of the same mapel.
        $pattern = '/^(.+) \(([^()]*[^()\s][^()]*)\)$/u';
        $normalizedName = fn (string $name): string => Str::lower(Str::squish($name));

        if ($isMakeup || preg_match($pattern, $normalizedName($component->subject->name), $requestedVariant) !== 1) {
            return $overlappingSessions->exists();
        }

        return $overlappingSessions->with(['assessmentSubject.schoolClass', 'assessmentSubject.subject'])->get()
            ->contains(function (ExamSession $session) use ($component, $pattern, $normalizedName, $requestedVariant): bool {
                $other = $session->assessmentSubject;

                return $session->kind !== SessionKind::Regular
                    || $other->assessment_period_id !== $component->assessment_period_id
                    || $other->school_class_id === $component->school_class_id
                    || $other->schoolClass->grade_level !== $component->schoolClass->grade_level
                    || $other->subject_id === $component->subject_id
                    || preg_match($pattern, $normalizedName($other->subject->name), $existingVariant) !== 1
                    || $existingVariant[1] !== $requestedVariant[1]
                    || trim($existingVariant[2]) === trim($requestedVariant[2]);
            });
    }

    public function assignClass(
        AssessmentSubject $assessmentSubject,
        ExamSession $examSession,
        ExamAssignmentService $assignmentService,
    ): RedirectResponse {
        if (
            $examSession->assessment_subject_id !== $assessmentSubject->id
            || $examSession->kind !== SessionKind::Regular
        ) {
            throw ValidationException::withMessages([
                'session' => 'Penempatan massal hanya dapat dilakukan ke sesi reguler yang sesuai.',
            ]);
        }

        $count = DB::transaction(function () use ($examSession, $assignmentService): int {
            $group = $examSession->scheduleGroup()->orderBy('id')->lockForUpdate()->get();
            if ($group->contains(fn (ExamSession $session): bool => $session->kind !== SessionKind::Regular || $session->status === SessionStatus::Closed)) {
                throw ValidationException::withMessages(['session' => 'Penempatan siswa hanya tersedia untuk sesi reguler yang belum ditutup.']);
            }

            return $this->assignStudents($group, $assignmentService);
        }, 3);

        return back()->with('status', $count.' siswa aktif baru berhasil ditempatkan tanpa data ganda.');
    }

    private function assignStudents(Collection $sessions, ExamAssignmentService $assignmentService): int
    {
        $sessions->loadMissing('assessmentSubject');
        $byClass = $sessions->keyBy('assessmentSubject.school_class_id');
        $students = Student::query()->whereIn('school_class_id', $byClass->keys())
            ->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
        $count = 0;
        foreach ($students as $student) {
            $session = $byClass->get($student->school_class_id);
            $assignment = $assignmentService->assign($student, $session->assessmentSubject, $session);
            $count += (int) $assignment->wasRecentlyCreated;
        }

        return $count;
    }

    public function moveToMakeup(Request $request, ExamAssignmentService $assignmentService): RedirectResponse
    {
        $validated = $request->validate([
            'assignment_id' => ['required', 'exists:exam_assignments,id'],
            'makeup_session_id' => ['required', 'exists:exam_sessions,id'],
        ]);

        $assignment = ExamAssignment::findOrFail($validated['assignment_id']);
        $makeupSession = ExamSession::findOrFail($validated['makeup_session_id']);

        $assignmentService->moveToMakeup($assignment, $makeupSession);

        return back()->with('status', 'Peserta dipindahkan ke sesi susulan tanpa membuat penugasan baru.');
    }

    public function destroySession(ExamSession $examSession, ExamAttemptResetService $resets): RedirectResponse
    {
        return $this->deleteSafely(
            fn () => DB::transaction(function () use ($examSession, $resets): void {
                $group = $examSession->scheduleGroup()->orderBy('id')->lockForUpdate()->get(['id', 'kind']);
                abort_unless($group->contains('id', $examSession->id), 404);
                $sessionIds = $group->modelKeys();

                // Exam starts also lock the session and assignment before writing an attempt.
                $assignments = ExamAssignment::query()->whereIn('exam_session_id', $sessionIds);
                $assignmentIds = (clone $assignments)->orderBy('id')->lockForUpdate()->pluck('id');
                if ($this->withUnresetAttempts(clone $assignments)->exists()) {
                    throw ValidationException::withMessages([
                        'session' => 'Jadwal masih memiliki peserta yang mengerjakan atau sudah menyelesaikan ujian. Gunakan Reset dan ulang pada peserta ini untuk setiap peserta terkait sebelum menghapus jadwal.',
                    ]);
                }
                if (ExamSession::query()->whereIn('source_session_id', $sessionIds)->exists()) {
                    throw ValidationException::withMessages([
                        'session' => 'Jadwal reguler ini masih digunakan oleh sesi susulan. Hapus sesi susulan terkait terlebih dahulu.',
                    ]);
                }
                if ($assignmentIds->isNotEmpty() && $group->contains('kind', SessionKind::Makeup)
                    && ! $assignments->whereHas('attemptResets')->exists()) {
                    throw ValidationException::withMessages([
                        'session' => 'Sesi susulan ini masih memiliki peserta. Pindahkan peserta ke sesi susulan lain terlebih dahulu.',
                    ]);
                }

                $resets->archiveForDeletedSchedule($assignmentIds->all());
                ExamAssignment::query()->whereKey($assignmentIds)->delete();
                ExamSession::query()->whereKey($sessionIds)->delete();
            }, 3),
            'Sesi ujian berhasil dihapus.',
            'Sesi ujian tidak dapat dihapus karena masih terkait dengan data ujian lain.',
        );
    }

    private function withUnresetAttempts(Builder $assignments): Builder
    {
        return $assignments->where(fn ($query) => $query
            ->whereIn('status', [AssignmentStatus::Started, AssignmentStatus::Completed])
            ->orWhereHas('attempt'));
    }

    private function assertComponentAcademicYear(array $validated): void
    {
        $period = AssessmentPeriod::findOrFail($validated['assessment_period_id']);
        $schoolClass = SchoolClass::findOrFail($validated['school_class_id']);

        if ($period->academic_year_id !== $schoolClass->academic_year_id) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Kelas dan periode asesmen harus berada pada tahun ajaran yang sama.',
            ]);
        }
    }

    private function deleteSafely(callable $callback, string $success, string $failure): RedirectResponse
    {
        try {
            $callback();

            return back()->with('status', $success);
        } catch (QueryException) {
            return back()->withErrors(['data' => $failure]);
        }
    }
}
