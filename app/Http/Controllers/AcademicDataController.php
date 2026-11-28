<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentExamRoom;
use App\Models\Subject;
use App\Models\User;
use App\Services\Exams\ExamStudentRoster;
use App\Services\Students\StudentDeletionService;
use App\Services\Students\StudentExamRoomService;
use App\Services\Students\StudentRosterRevision;
use App\Services\Students\StudentStatusService;
use App\Support\TablePagination;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicDataController extends Controller
{
    public function index(Request $request, StudentRosterRevision $roster): View
    {
        $rosterRevision = $roster->current();
        $rosterTotals = Student::query()->selectRaw('COUNT(*) AS student_count, MAX(id) AS last_id')->first();
        $request->validate(['room_period_id' => ['nullable', 'integer', 'exists:assessment_periods,id']]);
        $roomPeriod = $request->filled('room_period_id') ? AssessmentPeriod::with('academicYear')->findOrFail($request->integer('room_period_id')) : null;
        $classPerPage = TablePagination::selected('class_page');
        $classYearId = $request->filled('class_year') ? $request->integer('class_year') : null;
        $classQuery = SchoolClass::query()
            ->with(['academicYear', 'homeroomTeacher'])
            ->withCount('students')
            ->when($classYearId, fn ($query) => $query->where('academic_year_id', $classYearId))
            ->orderByDesc('academic_year_id')
            ->orderBy('name');

        $studentStatus = $request->validate(['student_status' => ['nullable', 'in:active,inactive']])['student_status'] ?? null;
        $studentSearch = trim($request->string('student_search')->toString());
        $studentClassId = $request->filled('student_class') ? $request->integer('student_class') : null;
        $studentPerPage = TablePagination::selected('student_page');
        $studentQuery = Student::query()
            ->with(['schoolClass.academicYear', 'user'])
            ->with(['examRooms' => fn ($query) => $query->where('assessment_period_id', $roomPeriod?->id ?? 0)])
            ->when($roomPeriod, fn ($query) => $query->whereHas('schoolClass', fn ($classes) => $classes->where('academic_year_id', $roomPeriod->academic_year_id)))
            ->when($studentSearch !== '', function ($query) use ($studentSearch, $roomPeriod): void {
                $query->where(function ($searchQuery) use ($studentSearch, $roomPeriod): void {
                    $searchQuery
                        ->where('full_name', 'like', '%'.$studentSearch.'%')
                        ->orWhere('student_number', 'like', '%'.$studentSearch.'%')
                        ->orWhere('nisn', 'like', '%'.$studentSearch.'%')
                        ->orWhereHas('examRooms', fn ($rooms) => $rooms->where('assessment_period_id', $roomPeriod?->id ?? 0)->where('room_name', 'like', '%'.$studentSearch.'%'))
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('username', 'like', '%'.$studentSearch.'%')
                            ->orWhere('email', 'like', '%'.$studentSearch.'%'));
                });
            })
            ->when($studentClassId, fn ($query) => $query->where('school_class_id', $studentClassId))
            ->when($studentStatus, fn ($query) => $query->where('is_active', $studentStatus === 'active'))
            ->orderBy('full_name')->orderBy('id');

        return view('academic.index', [
            'academicYears' => AcademicYear::query()
                ->withCount('classes')
                ->orderByDesc('starts_on')
                ->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'teachers' => User::query()
                ->where('role', UserRole::Teacher)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'classOptions' => SchoolClass::query()
                ->with('academicYear')
                ->orderByDesc('academic_year_id')
                ->orderBy('name')
                ->get(),
            'classes' => $classQuery->paginateForDisplay('class_page')->fragment('class-list'),
            'classPerPage' => $classPerPage,
            'classYearId' => $classYearId,
            'students' => $studentQuery->paginateForDisplay('student_page')->fragment('student-list'),
            'studentSearch' => $studentSearch,
            'studentStatus' => $studentStatus,
            'studentClassId' => $studentClassId,
            'rosterRevision' => $rosterRevision,
            'rosterTotals' => $rosterTotals,
            'studentPerPage' => $studentPerPage,
            'assessmentPeriods' => AssessmentPeriod::with('academicYear')->orderByDesc('starts_on')->orderByDesc('id')->get(),
            'roomPeriod' => $roomPeriod,
            'roomOptions' => StudentExamRoom::query()->where('assessment_period_id', $roomPeriod?->id ?? 0)->distinct()->orderBy('room_name')->pluck('room_name'),
        ]);
    }

    public function storeAcademicYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:academic_years,name'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            if ($request->boolean('is_active')) {
                AcademicYear::query()->update(['is_active' => false]);
            }

            AcademicYear::create([
                ...$validated,
                'is_active' => $request->boolean('is_active'),
            ]);
        });

        return back()->with('status', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function activateAcademicYear(AcademicYear $academicYear): RedirectResponse
    {
        DB::transaction(function () use ($academicYear): void {
            AcademicYear::query()->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });

        return back()->with('status', 'Tahun ajaran aktif berhasil diperbarui.');
    }

    public function destroyAcademicYear(AcademicYear $academicYear): RedirectResponse
    {
        return $this->deleteSafely(
            fn () => $academicYear->delete(),
            'Tahun ajaran berhasil dihapus.',
            'Tahun ajaran tidak dapat dihapus karena sudah digunakan oleh kelas atau periode asesmen.',
        );
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Subject::create([
            'code' => Str::upper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'is_active' => true,
        ]);

        return back()->with('status', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function toggleSubject(Subject $subject): RedirectResponse
    {
        $subject->update(['is_active' => ! $subject->is_active]);

        return back()->with('status', 'Status mata pelajaran berhasil diperbarui.');
    }

    public function destroySubject(Subject $subject): RedirectResponse
    {
        return $this->deleteSafely(
            fn () => $subject->delete(),
            'Mata pelajaran berhasil dihapus.',
            'Mata pelajaran tidak dapat dihapus karena sudah digunakan pada asesmen.',
        );
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('school_classes')->where(
                    fn ($query) => $query->where('academic_year_id', $request->integer('academic_year_id')),
                ),
            ],
            'grade_level' => ['required', 'integer', 'between:1,13'],
            'major' => ['nullable', 'string', 'max:80'],
        ]);

        SchoolClass::create($validated);

        return back()->with('status', 'Kelas berhasil ditambahkan.');
    }

    public function destroyClass(SchoolClass $schoolClass): RedirectResponse
    {
        return $this->deleteSafely(
            fn () => $schoolClass->delete(),
            'Kelas berhasil dihapus.',
            'Kelas tidak dapat dihapus karena sudah memiliki siswa atau data asesmen.',
        );
    }

    public function updateHomeroomTeacher(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $validated = $request->validate([
            'homeroom_teacher_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', UserRole::Teacher->value)
                    ->where('is_active', true)),
            ],
        ]);

        $schoolClass->update([
            'homeroom_teacher_user_id' => $validated['homeroom_teacher_user_id'] ?? null,
        ]);

        return back()->with('status', 'Wali kelas berhasil diperbarui.');
    }

    public function storeStudent(Request $request, ExamStudentRoster $examRoster): RedirectResponse
    {
        $request->merge([
            'nisn' => $request->filled('nisn') ? trim((string) $request->input('nisn')) : null,
            'email' => $request->filled('email') ? Str::lower(trim((string) $request->input('email'))) : null,
            'card_uid' => $request->filled('card_uid') ? trim((string) $request->input('card_uid')) : null,
        ]);

        $validated = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'student_number' => ['required', 'string', 'max:50', 'unique:students,student_number'],
            'nisn' => ['nullable', 'digits:10', 'unique:students,nisn'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'card_uid' => ['nullable', 'string', 'max:255'],
            'password' => [
                'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ]);

        $username = $validated['nisn'] ?: trim($validated['student_number']);

        if (User::query()->where('username', $username)->exists()) {
            return back()
                ->withErrors(['student_number' => 'NISN/NIS tersebut sudah digunakan sebagai login siswa lain.'])
                ->withInput();
        }

        if ($validated['card_uid'] && Student::query()
            ->where('card_uid_hash', hash('sha256', $validated['card_uid']))
            ->exists()) {
            return back()->withErrors(['card_uid' => 'UID kartu sudah digunakan siswa lain.'])->withInput();
        }

        DB::transaction(function () use ($validated, $examRoster): void {
            $username = $validated['nisn'] ?: trim($validated['student_number']);
            $user = new User([
                'name' => $validated['full_name'],
                'email' => $validated['email'] ?: null,
                'username' => $username,
                'role' => UserRole::Student,
                'is_active' => true,
            ]);
            $user->setStudentPassword($validated['password'] ?? trim($validated['student_number']));
            $user->save();

            $student = Student::create([
                'user_id' => $user->id,
                'school_class_id' => $validated['school_class_id'],
                'student_number' => trim($validated['student_number']),
                'nisn' => $validated['nisn'],
                'full_name' => trim($validated['full_name']),
                'card_uid_hash' => $validated['card_uid'] ? hash('sha256', $validated['card_uid']) : null,
                'is_active' => true,
            ]);
            $examRoster->sync(collect([$student]));
        }, 3);

        return back()->with('status', 'Data siswa berhasil ditambahkan.');
    }

    public function updateStudentRoom(Request $request, Student $student, StudentExamRoomService $rooms, StudentRosterRevision $roster): RedirectResponse
    {
        $validated = $request->validate([
            'room_name' => ['present', 'nullable', 'string', 'max:80'],
            'assessment_period_id' => ['required', 'integer', 'exists:assessment_periods,id'],
        ]);

        $roster->withCurrent(
            fn () => $rooms->assign(AssessmentPeriod::findOrFail($validated['assessment_period_id']), [$student->id], $validated['room_name']),
            $request->integer('roster_revision'),
        );

        return back()->with('status', 'Ruangan '.$student->full_name.' berhasil diperbarui.');
    }

    public function updateStudentRooms(Request $request, StudentExamRoomService $rooms, StudentRosterRevision $roster): RedirectResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'bulk_room_name' => ['present', 'nullable', 'string', 'max:80'],
            'assessment_period_id' => ['required', 'integer', 'exists:assessment_periods,id'],
        ], [
            'student_ids.required' => 'Pilih siswa yang akan diatur ruangannya.',
        ]);

        $roster->withCurrent(
            fn () => $rooms->assign(AssessmentPeriod::findOrFail($validated['assessment_period_id']), $validated['student_ids'], $validated['bulk_room_name']),
            $request->integer('roster_revision'),
        );

        return back()->with('status', 'Ruangan untuk '.count($validated['student_ids']).' siswa terpilih berhasil diperbarui.');
    }

    public function toggleStudent(
        Request $request,
        Student $student,
        StudentRosterRevision $roster,
        StudentStatusService $statuses,
    ): RedirectResponse {
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'inactive_reason' => ['required_if:is_active,0,false', 'nullable', 'string', 'max:500'],
        ], ['inactive_reason.required_if' => 'Tuliskan alasan penonaktifan yang akan ditampilkan kepada siswa.']);

        $active = $roster->withCurrent(function () use ($student, $validated, $statuses): bool {
            $students = Student::query()->whereKey($student->id)->lockForUpdate()->get();
            abort_if($students->isEmpty(), 404);
            $active = (bool) ($validated['is_active'] ?? ! $students->first()->is_active);
            $statuses->update($students, $active, $validated['inactive_reason'] ?? $students->first()->inactive_reason);

            return $active;
        }, $request->integer('roster_revision'));

        return back()->with('status', $active ? 'Siswa diaktifkan kembali.' : 'Siswa dinonaktifkan. Alasan ditampilkan saat siswa mencoba masuk.');
    }

    public function updateStudentStatuses(
        Request $request,
        StudentRosterRevision $roster,
        StudentStatusService $statuses,
    ): RedirectResponse {
        $validated = $request->validate([
            'scope' => ['required', 'in:selected,all'],
            'is_active' => ['required', 'boolean'],
            'inactive_reason' => ['required_if:is_active,0,false', 'nullable', 'string', 'max:500'],
            'student_ids' => ['exclude_unless:scope,selected', 'required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['integer', 'distinct'],
            'confirm_all' => ['exclude_unless:scope,all', 'accepted'],
            'expected_count' => ['exclude_unless:scope,all', 'required', 'integer', 'min:1'],
            'expected_last_id' => ['exclude_unless:scope,all', 'required', 'integer', 'min:1'],
        ], [
            'inactive_reason.required_if' => 'Tuliskan alasan penonaktifan yang akan ditampilkan kepada siswa.',
            'student_ids.required' => 'Centang siswa yang statusnya ingin diubah.',
            'confirm_all.accepted' => 'Konfirmasi perubahan status seluruh siswa.',
        ]);
        $active = (bool) $validated['is_active'];
        $reason = $validated['inactive_reason'] ?? null;

        $count = $roster->withCurrent(function () use ($validated, $active, $reason, $statuses): int {
            $query = Student::query()->select(['id', 'user_id', 'school_class_id'])->lockForUpdate();
            if ($validated['scope'] === 'selected') {
                $students = $query->whereKey($validated['student_ids'])->orderBy('id')->get();
                if ($students->count() !== count($validated['student_ids'])) {
                    throw ValidationException::withMessages(['student_ids' => 'Sebagian siswa sudah tidak tersedia. Muat ulang daftar siswa.']);
                }
                $statuses->update($students, $active, $reason);

                return $students->count();
            }

            $total = Student::query()->selectRaw('COUNT(*) AS student_count, MAX(id) AS last_id')->first();
            if ((int) $total->student_count !== (int) $validated['expected_count']
                || (int) $total->last_id !== (int) $validated['expected_last_id']) {
                throw ValidationException::withMessages(['student_ids' => 'Daftar siswa berubah. Muat ulang dan pastikan jumlah siswa sebelum mengubah status semua siswa.']);
            }

            $query->where('id', '<=', $total->last_id)
                ->chunkById(500, fn ($students) => $statuses->update($students, $active, $reason));

            return (int) $total->student_count;
        }, $request->integer('roster_revision'));

        $message = $active ? ' siswa diaktifkan.' : ' siswa dinonaktifkan. Alasan ditampilkan saat siswa mencoba masuk.';

        return $this->studentListRedirect($request)->with('status', $count.$message);
    }

    public function destroyStudents(Request $request, StudentDeletionService $deletion): RedirectResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
        ], ['student_ids.required' => 'Centang siswa yang akan dihapus terlebih dahulu.']);

        try {
            $count = $deletion->delete($validated['student_ids'], $request->integer('roster_revision'));
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors(['student_ids' => 'Penghapusan dibatalkan karena ada data terkait yang belum dapat dihapus. Tidak ada siswa yang dihapus.']);
        }

        return $this->studentListRedirect($request)->with('status', $count.' siswa terpilih beserta akun dan data terkait berhasil dihapus.');
    }

    public function destroyAllStudents(Request $request, StudentDeletionService $deletion): RedirectResponse
    {
        $validated = $request->validate([
            'confirmation' => ['required', 'string', Rule::in(['HAPUS SEMUA'])],
            'expected_count' => ['required', 'integer', 'min:1'],
            'expected_last_id' => ['required', 'integer', 'min:1'],
            'roster_revision' => ['required', 'integer', 'min:0'],
        ], ['confirmation.in' => 'Ketik HAPUS SEMUA untuk mengonfirmasi penghapusan.']);

        try {
            $result = $deletion->deleteAll((int) $validated['expected_count'], (int) $validated['expected_last_id'], (int) $validated['roster_revision']);
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors(['student_ids' => 'Penghapusan dibatalkan karena ada data terkait yang belum dapat dihapus. Tidak ada siswa yang dihapus.']);
        }

        $numbering = $result['reset']
            ? ' ID siswa baru akan dimulai dari 1.'
            : ' Urutan ID database belum bisa direset.';

        return $this->studentListRedirect($request)->with('status', $result['count'].' siswa beserta akun dan riwayat terkait berhasil dihapus.'.$numbering);
    }

    public function destroyStudent(Request $request, Student $student, StudentDeletionService $deletion): RedirectResponse
    {
        try {
            $deletion->delete([$student->id], $request->integer('roster_revision'));
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors(['student_ids' => 'Siswa tidak dapat dihapus karena masih ada data terkait. Penghapusan dibatalkan.']);
        }

        return $this->studentListRedirect($request)->with('status', 'Data siswa beserta akun dan data terkait berhasil dihapus.');
    }

    private function studentListRedirect(Request $request): RedirectResponse
    {
        $query = array_intersect_key($request->query(), array_flip([
            'student_search', 'student_class', 'student_status', 'student_per_page', 'room_period_id',
            'class_year', 'class_per_page', 'class_page',
        ]));

        // A deletion or status change can empty the last page in the current filter.
        return redirect()->to(route('academic.index', $query).'#student-list');
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
