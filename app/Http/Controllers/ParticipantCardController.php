<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParticipantCardController extends Controller
{
    public function generate(Request $request): Response
    {
        $validated = $request->validate([
            'school_class_id' => [
                'required',
                Rule::when($request->input('school_class_id') !== 'all', ['integer', 'exists:school_classes,id']),
            ],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ]);
        $allClasses = $validated['school_class_id'] === 'all';
        $classes = SchoolClass::query()->with('academicYear')
            ->when(! $allClasses, fn ($query) => $query->whereKey($validated['school_class_id']))
            ->when($validated['academic_year_id'] ?? null, fn ($query, $year) => $query->where('academic_year_id', $year))
            ->orderByDesc('academic_year_id')->orderBy('grade_level')->orderBy('name')->get();
        if (! $allClasses && $classes->isEmpty()) {
            throw ValidationException::withMessages(['school_class_id' => 'Kelas yang dipilih tidak berada pada tahun ajaran tersebut.']);
        }

        $students = Student::query()->with('user')
            ->whereIn('school_class_id', $classes->modelKeys())
            ->where('is_active', true)
            ->whereHas('user', fn ($users) => $users->where('role', UserRole::Student)->where('is_active', true))
            ->orderBy('full_name')->orderBy('id')->get();
        $byClass = $students->groupBy('school_class_id');
        $classGroups = $classes->filter(fn ($class): bool => $byClass->has($class->id))
            ->each(fn ($class) => $class->setRelation('cardStudents', $byClass->get($class->id)));
        $printLabel = $allClasses ? 'Seluruh kelas' : $classes->first()->name;

        return response()->view('academic.participant-cards', [
            'school' => SchoolProfile::query()->first(),
            'printLabel' => $printLabel,
            'classGroups' => $classGroups,
            'students' => $students,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
