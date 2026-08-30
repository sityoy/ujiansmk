<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentType;
use App\Enums\AssignmentStatus;
use App\Enums\PeriodStatus;
use App\Enums\Semester;
use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Campus;
use App\Models\ExamAssignment;
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\Exams\ExamAssignmentService;
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
    public function index(): View
    {
        return view('scheduling.index', [
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'campuses' => Campus::query()->orderBy('name')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'classes' => SchoolClass::query()->with('academicYear')->orderBy('name')->get(),
            'periods' => AssessmentPeriod::query()
                ->with('academicYear')
                ->withCount('assessmentSubjects')
                ->orderByDesc('starts_on')
                ->get(),
            'components' => AssessmentSubject::query()
                ->with([
                    'assessmentPeriod.academicYear',
                    'subject',
                    'schoolClass',
                    'examSessions' => fn ($query) => $query
                        ->with('campus')
                        ->withCount('assignments')
                        ->orderBy('starts_at'),
                ])
                ->latest()
                ->get(),
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
        ]);
    }

    public function storeCampus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'between:10,5000'],
            'max_accuracy_meters' => ['required', 'integer', 'between:5,500'],
        ]);

        Campus::create([...$validated, 'is_active' => true]);

        return back()->with('status', 'Lokasi ujian berhasil ditambahkan.');
    }

    public function toggleCampus(Campus $campus): RedirectResponse
    {
        $campus->update(['is_active' => ! $campus->is_active]);

        return back()->with('status', 'Status lokasi berhasil diperbarui.');
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
            'school_class_id' => [
                'required',
                'exists:school_classes,id',
                Rule::unique('assessment_subjects')->where(fn ($query) => $query
                    ->where('assessment_period_id', $request->integer('assessment_period_id'))
                    ->where('subject_id', $request->integer('subject_id'))),
            ],
        ]);

        $period = AssessmentPeriod::findOrFail($validated['assessment_period_id']);
        $schoolClass = SchoolClass::findOrFail($validated['school_class_id']);

        if ($period->academic_year_id !== $schoolClass->academic_year_id) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Kelas dan periode asesmen harus berada pada tahun ajaran yang sama.',
            ]);
        }

        AssessmentSubject::create($validated);

        return back()->with('status', 'Mapel dan kelas berhasil dimasukkan ke periode asesmen.');
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
        $validated = $request->validate([
            'assessment_subject_id' => ['required', 'exists:assessment_subjects,id'],
            'campus_id' => ['required', Rule::exists('campuses', 'id')->where('is_active', true)],
            'kind' => ['required', Rule::enum(SessionKind::class)],
            'source_session_id' => ['nullable', 'exists:exam_sessions,id'],
            'room_name' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(SessionStatus::class)],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'between:10,600'],
        ]);

        $component = AssessmentSubject::query()
            ->with('assessmentPeriod')
            ->findOrFail($validated['assessment_subject_id']);
        $sourceSession = ! empty($validated['source_session_id'])
            ? ExamSession::findOrFail($validated['source_session_id'])
            : null;
        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = $startsAt->copy()->addMinutes((int) $validated['duration_minutes']);
        $period = $component->assessmentPeriod;
        $isMakeup = $validated['kind'] === SessionKind::Makeup->value;

        if (! $isMakeup && (
            $startsAt->toDateString() < $period->starts_on->toDateString()
            || $endsAt->toDateString() > $period->ends_on->toDateString()
        )) {
            throw ValidationException::withMessages([
                'starts_at' => 'Jadwal reguler harus berada di dalam rentang tanggal periode asesmen.',
            ]);
        }

        if ($isMakeup && $startsAt->toDateString() < $period->starts_on->toDateString()) {
            throw ValidationException::withMessages([
                'starts_at' => 'Jadwal susulan tidak boleh mendahului periode asesmen.',
            ]);
        }

        $classConflict = ExamSession::query()
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
            $roomConflict = ExamSession::query()
                ->where('campus_id', $validated['campus_id'])
                ->whereRaw('LOWER(room_name) = ?', [Str::lower(trim($validated['room_name']))])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();

            if ($roomConflict) {
                throw ValidationException::withMessages([
                    'room_name' => 'Ruangan tersebut sudah dipakai oleh sesi lain pada waktu yang sama.',
                ]);
            }
        }

        if ($isMakeup) {
            if (
                ! $sourceSession
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
        }

        $validated['room_name'] = filled($validated['room_name'] ?? null)
            ? trim($validated['room_name'])
            : null;
        $validated['starts_at'] = $startsAt;
        $validated['ends_at'] = $endsAt;

        ExamSession::create($validated);

        return back()->with('status', 'Sesi ujian berhasil dijadwalkan.');
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

        $students = $assessmentSubject->schoolClass()
            ->firstOrFail()
            ->students()
            ->where('is_active', true)
            ->get();

        DB::transaction(function () use ($students, $assessmentSubject, $examSession, $assignmentService): void {
            foreach ($students as $student) {
                $assignmentService->assign($student, $assessmentSubject, $examSession);
            }
        });

        return back()->with('status', $students->count().' siswa aktif berhasil ditempatkan tanpa data ganda.');
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

    public function destroySession(ExamSession $examSession): RedirectResponse
    {
        return $this->deleteSafely(
            fn () => $examSession->delete(),
            'Sesi ujian berhasil dihapus.',
            'Sesi tidak dapat dihapus karena sudah memiliki peserta atau aktivitas ujian.',
        );
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
