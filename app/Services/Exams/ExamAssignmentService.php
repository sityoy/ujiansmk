<?php

namespace App\Services\Exams;

use App\Enums\AssignmentStatus;
use App\Enums\SessionKind;
use App\Models\AssessmentSubject;
use App\Models\ExamAssignment;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamAssignmentService
{
    public function assign(
        Student $student,
        AssessmentSubject $assessmentSubject,
        ExamSession $session,
    ): ExamAssignment {
        $this->assertMatchingSubject($assessmentSubject, $session);
        $this->assertNoStudentConflict($student, $session, $assessmentSubject->id);

        return ExamAssignment::query()->firstOrCreate(
            [
                'student_id' => $student->id,
                'assessment_subject_id' => $assessmentSubject->id,
            ],
            [
                'exam_session_id' => $session->id,
                'status' => AssignmentStatus::Scheduled,
                'assigned_at' => now(),
            ],
        );
    }

    public function moveToMakeup(ExamAssignment $assignment, ExamSession $makeupSession): ExamAssignment
    {
        if ($makeupSession->kind !== SessionKind::Makeup) {
            throw ValidationException::withMessages([
                'exam_session' => 'Jadwal tujuan bukan sesi susulan.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $makeupSession): ExamAssignment {
            $lockedAssignment = ExamAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            $this->assertMatchingSubject($lockedAssignment->assessmentSubject, $makeupSession);
            $this->assertNoStudentConflict(
                $lockedAssignment->student,
                $makeupSession,
                $lockedAssignment->assessment_subject_id,
            );

            if (in_array($lockedAssignment->status, [AssignmentStatus::Started, AssignmentStatus::Completed], true)) {
                throw ValidationException::withMessages([
                    'assignment' => 'Ujian yang sudah dimulai atau selesai tidak dapat dipindahkan ke susulan.',
                ]);
            }

            $lockedAssignment->update([
                'exam_session_id' => $makeupSession->id,
                'status' => AssignmentStatus::Scheduled,
                'assigned_at' => now(),
            ]);

            return $lockedAssignment->refresh();
        });
    }

    private function assertMatchingSubject(
        AssessmentSubject $assessmentSubject,
        ExamSession $session,
    ): void {
        if ($session->assessment_subject_id !== $assessmentSubject->id) {
            throw ValidationException::withMessages([
                'exam_session' => 'Jadwal tidak sesuai dengan komponen asesmen siswa.',
            ]);
        }
    }

    private function assertNoStudentConflict(
        Student $student,
        ExamSession $session,
        int $exceptAssessmentSubjectId,
    ): void {
        $hasConflict = ExamAssignment::query()
            ->where('student_id', $student->id)
            ->where('assessment_subject_id', '!=', $exceptAssessmentSubjectId)
            ->whereIn('status', [AssignmentStatus::Scheduled, AssignmentStatus::Started])
            ->whereHas('examSession', fn ($query) => $query
                ->where('starts_at', '<', $session->ends_at)
                ->where('ends_at', '>', $session->starts_at))
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'student' => 'Siswa sudah memiliki ujian lain pada waktu yang bertabrakan.',
            ]);
        }
    }
}
