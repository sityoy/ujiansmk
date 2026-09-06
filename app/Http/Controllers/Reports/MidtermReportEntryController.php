<?php

namespace App\Http\Controllers\Reports;

use App\Enums\AssessmentType;
use App\Enums\ExtracurricularRating;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Extracurricular;
use App\Models\ExtracurricularGrade;
use App\Models\MidtermAttendanceSummary;
use App\Models\MidtermSubjectResult;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Reports\MidtermReportAccess;
use App\Services\Reports\MidtermReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MidtermReportEntryController extends Controller
{
    public function edit(
        Request $request,
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        MidtermReportService $reports,
        MidtermReportAccess $access,
    ): View {
        $this->assertContext($assessmentPeriod, $schoolClass);
        abort_unless($access->canView($request->user(), $assessmentPeriod, $schoolClass), 403);

        $report = $reports->build($assessmentPeriod, $schoolClass);
        $extracurriculars = Extracurricular::query()
            ->where('academic_year_id', $assessmentPeriod->academic_year_id)
            ->with([
                'coach',
                'participants' => fn ($query) => $query->where('school_class_id', $schoolClass->id),
                'grades' => fn ($query) => $query->where('assessment_period_id', $assessmentPeriod->id),
            ])
            ->orderBy('name')
            ->get();

        return view('reports.midterm.edit', [
            ...$report,
            'teachers' => User::query()->where('role', UserRole::Teacher)->where('is_active', true)->orderBy('name')->get(),
            'extracurriculars' => $extracurriculars,
            'ratings' => ExtracurricularRating::cases(),
            'canConfigure' => $access->canConfigure($request->user()),
            'canRecordAttendance' => $access->canRecordAttendance($request->user(), $schoolClass),
            'subjectPermissions' => $report['subjects']->mapWithKeys(
                fn (AssessmentSubject $subject) => [$subject->id => $access->canManageSubject($request->user(), $subject)],
            ),
            'extracurricularPermissions' => $extracurriculars->mapWithKeys(
                fn (Extracurricular $activity) => [$activity->id => $access->canManageExtracurricular($request->user(), $activity)],
            ),
        ]);
    }

    public function updateSubjectResults(
        Request $request,
        AssessmentSubject $assessmentSubject,
        MidtermReportAccess $access,
        MidtermReportService $reports,
    ): RedirectResponse {
        $assessmentSubject->loadMissing(['assessmentPeriod', 'schoolClass', 'subject']);
        $this->assertContext($assessmentSubject->assessmentPeriod, $assessmentSubject->schoolClass);
        abort_unless($access->canManageSubject($request->user(), $assessmentSubject), 403);

        $validated = $request->validate([
            'learning_objective' => ['required', 'string', 'max:2000'],
            'results' => ['required', 'array'],
            'results.*.score' => ['nullable', 'numeric', 'between:0,100'],
            'results.*.description' => ['nullable', 'string', 'max:2000'],
        ]);
        $studentIds = Student::query()
            ->where('school_class_id', $assessmentSubject->school_class_id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($validated, $studentIds, $assessmentSubject, $request, $reports): void {
            $objective = trim($validated['learning_objective']);
            $assessmentSubject->update(['learning_objective' => $objective]);

            foreach ($studentIds as $studentId) {
                $data = $validated['results'][$studentId] ?? null;
                if (! $data || $data['score'] === null || $data['score'] === '') {
                    continue;
                }

                $score = (float) $data['score'];
                $description = trim((string) ($data['description'] ?? ''));
                MidtermSubjectResult::query()->updateOrCreate(
                    ['assessment_subject_id' => $assessmentSubject->id, 'student_id' => $studentId],
                    [
                        'score' => $score,
                        'description' => $description !== '' ? $description : $reports->subjectDescription($score, $objective),
                        'recorded_by_user_id' => $request->user()->id,
                    ],
                );
            }
        });

        return back()->with('status', 'Nilai, tujuan pembelajaran, dan deskripsi '.$assessmentSubject->subject->name.' berhasil disimpan.');
    }

    public function updateAttendance(
        Request $request,
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        MidtermReportAccess $access,
    ): RedirectResponse {
        $this->assertContext($assessmentPeriod, $schoolClass);
        abort_unless($access->canRecordAttendance($request->user(), $schoolClass), 403);
        $validated = $request->validate([
            'attendance' => ['required', 'array'],
            'attendance.*.sick_days' => ['required', 'integer', 'between:0,366'],
            'attendance.*.excused_days' => ['required', 'integer', 'between:0,366'],
            'attendance.*.unexcused_days' => ['required', 'integer', 'between:0,366'],
            'attendance.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $studentIds = Student::query()->where('school_class_id', $schoolClass->id)->where('is_active', true)->pluck('id');

        DB::transaction(function () use ($validated, $studentIds, $assessmentPeriod, $request): void {
            foreach ($studentIds as $studentId) {
                $data = $validated['attendance'][$studentId] ?? null;
                if (! $data) {
                    continue;
                }

                MidtermAttendanceSummary::query()->updateOrCreate(
                    ['assessment_period_id' => $assessmentPeriod->id, 'student_id' => $studentId],
                    [
                        'sick_days' => $data['sick_days'],
                        'excused_days' => $data['excused_days'],
                        'unexcused_days' => $data['unexcused_days'],
                        'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                        'recorded_by_user_id' => $request->user()->id,
                    ],
                );
            }
        });

        return back()->with('status', 'Rekap ketidakhadiran kelas berhasil disimpan.');
    }

    public function storeExtracurricular(
        Request $request,
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        MidtermReportAccess $access,
    ): RedirectResponse {
        $this->assertContext($assessmentPeriod, $schoolClass);
        abort_unless($access->canConfigure($request->user()), 403);
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('extracurriculars')->where('academic_year_id', $assessmentPeriod->academic_year_id),
            ],
            'coach_user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', UserRole::Teacher->value)->where('is_active', true)),
            ],
        ]);

        Extracurricular::create([
            'academic_year_id' => $assessmentPeriod->academic_year_id,
            'name' => trim($validated['name']),
            'coach_user_id' => $validated['coach_user_id'],
            'is_active' => true,
        ]);

        return back()->with('status', 'Ekstrakurikuler dan guru pembina berhasil ditambahkan.');
    }

    public function syncExtracurricularParticipants(
        Request $request,
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        Extracurricular $extracurricular,
        MidtermReportAccess $access,
    ): RedirectResponse {
        $this->assertExtracurricularContext($assessmentPeriod, $schoolClass, $extracurricular);
        abort_unless($access->canConfigure($request->user()), 403);
        $validated = $request->validate([
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', Rule::exists('students', 'id')->where('school_class_id', $schoolClass->id)],
        ]);
        $selectedIds = collect($validated['participant_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $classParticipantIds = $extracurricular->participants()->where('school_class_id', $schoolClass->id)->pluck('students.id');

        DB::transaction(function () use ($extracurricular, $classParticipantIds, $selectedIds): void {
            $extracurricular->participants()->detach($classParticipantIds->diff($selectedIds));
            $extracurricular->participants()->syncWithoutDetaching($selectedIds->all());
        });

        return back()->with('status', 'Peserta ekstrakurikuler kelas berhasil diperbarui.');
    }

    public function updateExtracurricular(
        Request $request,
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        Extracurricular $extracurricular,
        MidtermReportAccess $access,
    ): RedirectResponse {
        $this->assertExtracurricularContext($assessmentPeriod, $schoolClass, $extracurricular);
        abort_unless($access->canConfigure($request->user()), 403);
        $validated = $request->validate([
            'coach_user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', UserRole::Teacher->value)->where('is_active', true)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $extracurricular->update([
            'coach_user_id' => $validated['coach_user_id'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Guru pembina dan status ekstrakurikuler berhasil diperbarui.');
    }

    public function updateExtracurricularGrades(
        Request $request,
        AssessmentPeriod $assessmentPeriod,
        SchoolClass $schoolClass,
        Extracurricular $extracurricular,
        MidtermReportAccess $access,
    ): RedirectResponse {
        $this->assertExtracurricularContext($assessmentPeriod, $schoolClass, $extracurricular);
        abort_unless($access->canManageExtracurricular($request->user(), $extracurricular), 403);
        $validated = $request->validate([
            'ratings' => ['required', 'array'],
            'ratings.*' => ['required', Rule::enum(ExtracurricularRating::class)],
            'descriptions' => ['nullable', 'array'],
            'descriptions.*' => ['nullable', 'string', 'max:1000'],
        ]);
        $participantIds = $extracurricular->participants()->where('school_class_id', $schoolClass->id)->pluck('students.id');

        DB::transaction(function () use ($validated, $participantIds, $assessmentPeriod, $extracurricular, $request): void {
            foreach ($participantIds as $studentId) {
                $ratingValue = $validated['ratings'][$studentId] ?? null;
                if (! $ratingValue) {
                    continue;
                }

                $rating = ExtracurricularRating::from($ratingValue);
                $description = trim((string) ($validated['descriptions'][$studentId] ?? ''));
                ExtracurricularGrade::query()->updateOrCreate(
                    [
                        'assessment_period_id' => $assessmentPeriod->id,
                        'extracurricular_id' => $extracurricular->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'rating' => $rating,
                        'description' => $description !== '' ? $description : $rating->defaultDescription($extracurricular->name),
                        'graded_by_user_id' => $request->user()->id,
                    ],
                );
            }
        });

        return back()->with('status', 'Nilai ekstrakurikuler '.$extracurricular->name.' berhasil disimpan.');
    }

    private function assertContext(AssessmentPeriod $period, SchoolClass $schoolClass): void
    {
        abort_unless($period->type === AssessmentType::ATS && $period->academic_year_id === $schoolClass->academic_year_id, 404);
    }

    private function assertExtracurricularContext(
        AssessmentPeriod $period,
        SchoolClass $schoolClass,
        Extracurricular $extracurricular,
    ): void {
        $this->assertContext($period, $schoolClass);
        abort_unless($extracurricular->academic_year_id === $period->academic_year_id, 404);
    }
}
