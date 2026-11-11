<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ParticipantCardController extends Controller
{
    public function generate(Request $request): Response
    {
        $validated = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
        ]);

        $schoolClass = SchoolClass::query()->with('academicYear')->findOrFail($validated['school_class_id']);
        $students = Student::query()
            ->with('user')
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->whereHas('user', fn ($users) => $users->where('role', UserRole::Student)->where('is_active', true))
            ->orderBy('full_name')
            ->get();

        return response()->view('academic.participant-cards', [
            'school' => SchoolProfile::query()->first(),
            'schoolClass' => $schoolClass,
            'students' => $students,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
