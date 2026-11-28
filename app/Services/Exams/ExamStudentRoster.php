<?php

namespace App\Services\Exams;

use App\Enums\AssignmentStatus;
use App\Models\ExamAssignment;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExamStudentRoster
{
    public function __construct(private readonly ExamAssignmentService $assignments) {}

    /** @param Collection<int, Student> $students The caller holds the student rows in a transaction. */
    public function sync(Collection $students): void
    {
        foreach ($students->groupBy('school_class_id') as $classId => $peers) {
            $active = $peers->where('is_active', true);
            ExamAssignment::query()->whereIn('student_id', $peers->pluck('id'))
                ->whereHas('examSession', fn (Builder $sessions) => $sessions->acceptsRegularRoster())
                ->where('status', AssignmentStatus::Scheduled)
                ->whereDoesntHave('attempt')
                ->whereDoesntHave('attemptResets')
                ->where(fn (Builder $query) => $query
                    ->whereIn('student_id', $peers->where('is_active', false)->pluck('id'))
                    ->orWhereHas('assessmentSubject', fn (Builder $components) => $components->where('school_class_id', '!=', $classId)))
                ->delete();

            if ($active->isEmpty()) {
                continue;
            }

            $sessions = ExamSession::query()->acceptsRegularRoster()
                ->whereHas('assessmentSubject', fn (Builder $components) => $components->where('school_class_id', $classId))
                ->with('assessmentSubject')
                ->orderBy('starts_at')->orderBy('id')->lockForUpdate()->get()
                ->unique('assessment_subject_id');
            if ($sessions->isEmpty()) {
                continue;
            }

            $placed = ExamAssignment::query()->whereIn('student_id', $active->pluck('id'))
                ->whereIn('assessment_subject_id', $sessions->pluck('assessment_subject_id'))
                ->get(['student_id', 'assessment_subject_id'])->groupBy('student_id');
            foreach ($active as $student) {
                $assignedSubjects = $placed->get($student->id)?->pluck('assessment_subject_id') ?? collect();
                foreach ($sessions->whereNotIn('assessment_subject_id', $assignedSubjects) as $session) {
                    try {
                        $this->assignments->assign($student, $session->assessmentSubject, $session);
                    } catch (ValidationException $exception) {
                        // Keep an existing conflicting placement, such as a manually scheduled susulan.
                        if (! array_key_exists('student', $exception->errors())) {
                            throw $exception;
                        }
                    }
                }
            }
        }
    }
}
