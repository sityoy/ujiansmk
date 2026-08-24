<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AcademicDataController extends Controller
{
    public function index(): View
    {
        return view('academic.index', [
            'academicYears' => AcademicYear::query()
                ->withCount('classes')
                ->orderByDesc('starts_on')
                ->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'classes' => SchoolClass::query()
                ->with('academicYear')
                ->withCount('students')
                ->orderBy('name')
                ->get(),
            'students' => Student::query()
                ->with(['schoolClass.academicYear', 'user'])
                ->orderBy('full_name')
                ->paginate(20),
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

    public function storeStudent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'student_number' => ['required', 'string', 'max:50', 'unique:students,student_number'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'nullable',
                'required_with:email',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ]);

        DB::transaction(function () use ($validated): void {
            $user = null;

            if (! empty($validated['email'])) {
                $user = User::create([
                    'name' => $validated['full_name'],
                    'email' => Str::lower($validated['email']),
                    'password' => $validated['password'],
                    'role' => UserRole::Student,
                    'is_active' => true,
                ]);
            }

            Student::create([
                'user_id' => $user?->id,
                'school_class_id' => $validated['school_class_id'],
                'student_number' => trim($validated['student_number']),
                'full_name' => trim($validated['full_name']),
                'is_active' => true,
            ]);
        });

        return back()->with('status', 'Data siswa berhasil ditambahkan.');
    }

    public function toggleStudent(Student $student): RedirectResponse
    {
        DB::transaction(function () use ($student): void {
            $student->update(['is_active' => ! $student->is_active]);
            $student->user?->update(['is_active' => $student->is_active]);
        });

        return back()->with('status', 'Status siswa berhasil diperbarui.');
    }

    public function destroyStudent(Student $student): RedirectResponse
    {
        return $this->deleteSafely(
            function () use ($student): void {
                DB::transaction(function () use ($student): void {
                    $user = $student->user;
                    $student->delete();
                    $user?->delete();
                });
            },
            'Data siswa berhasil dihapus.',
            'Siswa tidak dapat dihapus karena sudah memiliki penugasan, nilai, atau absensi.',
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
