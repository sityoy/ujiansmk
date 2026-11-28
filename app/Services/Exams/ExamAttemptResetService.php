<?php

namespace App\Services\Exams;

use App\Enums\AssignmentStatus;
use App\Enums\SessionStatus;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptReset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamAttemptResetService
{
    public function archiveForDeletedSchedule(array $assignmentIds): void
    {
        ExamAttemptReset::query()->whereIn('exam_assignment_id', $assignmentIds)
            ->with('assignment.examSession')
            ->eachById(function (ExamAttemptReset $reset): void {
                $assignment = $reset->assignment;
                $session = $assignment->examSession;

                $reset->update([
                    'exam_assignment_id' => null,
                    'snapshot' => [...$reset->snapshot, 'deleted_schedule' => [
                        'assignment_id' => $assignment->id,
                        'student_id' => $assignment->student_id,
                        'assessment_subject_id' => $assignment->assessment_subject_id,
                        'session_id' => $session->id,
                        'starts_at' => $session->starts_at->toIso8601String(),
                        'ends_at' => $session->ends_at->toIso8601String(),
                    ]],
                ]);
            });
    }

    public function reset(ExamAssignment $assignment, User $actor, string $reason): void
    {
        DB::transaction(function () use ($assignment, $actor, $reason): void {
            $assignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $assignment->load('examSession');
            $attempt = ExamAttempt::query()->where('exam_assignment_id', $assignment->id)
                ->lockForUpdate()->first();
            if (! $attempt) {
                throw ValidationException::withMessages(['attempt' => 'Peserta belum pernah memulai ujian ini.']);
            }
            $attempt->load(['answers', 'securityIncidents']);
            if ($assignment->examSession->status === SessionStatus::Closed || now()->gte($assignment->examSession->ends_at)) {
                throw ValidationException::withMessages([
                    'attempt' => 'Sesi sudah ditutup atau berakhir. Pindahkan peserta ke sesi susulan sebelum mengulang ujian.',
                ]);
            }

            $resetNumber = app(ExamResetAllowance::class)->consume($assignment, $actor);
            ExamAttemptReset::create([
                'exam_assignment_id' => $assignment->id,
                'original_attempt_id' => $attempt->id,
                'performed_by_user_id' => $actor->id,
                'reason' => $reason,
                'snapshot' => [
                    'reset_number' => $resetNumber,
                    'status' => $attempt->status->value,
                    'started_at' => $attempt->started_at?->toIso8601String(),
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    'score' => $attempt->score,
                    'violation_count' => (int) $attempt->violation_count,
                    'answers' => $attempt->answers->map(fn ($answer) => [
                        'question_id' => $answer->exam_question_id,
                        'answer' => $answer->answer,
                        'points_awarded' => $answer->points_awarded,
                    ])->values()->all(),
                    'security_incidents' => $attempt->securityIncidents->map(fn ($incident) => [
                        'category' => $incident->category,
                        'occurred_at' => $incident->occurred_at?->toIso8601String(),
                        'details' => $incident->details,
                    ])->values()->all(),
                ],
            ]);

            $attempt->delete();
            $assignment->update(['status' => AssignmentStatus::Scheduled, 'assigned_at' => now()]);
        });
    }
}
