<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParticipantCardController extends Controller
{
    public function generate(Request $request): View
    {
        $validated = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
        ]);

        $schoolClass = SchoolClass::query()->with('academicYear')->findOrFail($validated['school_class_id']);
        $students = Student::query()
            ->with('user')
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->whereHas('user')
            ->orderBy('full_name')
            ->get();

        return view('academic.participant-cards', [
            'school' => SchoolProfile::query()->first(),
            'schoolClass' => $schoolClass,
            'students' => $students,
        ]);
    }
}
