<?php

namespace App\Services\Exams;

use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\CheckinStatus;
use App\Enums\SessionStatus;
use App\Models\DailyCheckin;
use App\Models\ExamAnswer;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamAttemptService
{
    public function start(
        ExamAssignment $assignment,
        DailyCheckin $checkin,
        string $deviceSessionHash,
        ?string $ipAddress,
        ?string $userAgent,
    ): ExamAttempt {
        $assignment->loadMissing(['examSession', 'assessmentSubject.questions']);
        $session = $assignment->examSession;
        $existing = $assignment->attempt;

        if ($existing) {
            $this->assertDevice($existing, $deviceSessionHash);

            return $existing;
        }

        if (! in_array($assignment->status, [AssignmentStatus::Scheduled, AssignmentStatus::Started], true)) {
            throw ValidationException::withMessages(['exam' => 'Penugasan ujian tidak dapat dimulai.']);
        }

        if (! in_array($session->status, [SessionStatus::Published, SessionStatus::Active], true)) {
            throw ValidationException::withMessages(['exam' => 'Sesi ujian belum diterbitkan atau sudah ditutup.']);
        }

        if (now()->lt($session->starts_at)) {
            throw ValidationException::withMessages(['exam' => 'Ujian belum memasuki waktu mulai.']);
        }

        if (now()->gte($session->ends_at)) {
            throw ValidationException::withMessages(['exam' => 'Waktu masuk ujian telah berakhir.']);
        }

        if (
            $checkin->student_id !== $assignment->student_id
            || $checkin->campus_id !== $session->campus_id
            || $checkin->status !== CheckinStatus::Verified
            || ! $checkin->attendance_date->isToday()
        ) {
            throw ValidationException::withMessages(['attendance' => 'Absensi terverifikasi di lokasi ujian wajib dilakukan terlebih dahulu.']);
        }

        if ($assignment->assessmentSubject->questions->isEmpty()) {
            throw ValidationException::withMessages(['exam' => 'Soal belum tersedia. Hubungi panitia ujian.']);
        }

        return DB::transaction(function () use ($assignment, $checkin, $deviceSessionHash, $ipAddress, $userAgent): ExamAttempt {
            $lockedAssignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $attempt = $lockedAssignment->attempt;

            if (! $attempt) {
                $attempt = ExamAttempt::create([
                    'exam_assignment_id' => $lockedAssignment->id,
                    'daily_checkin_id' => $checkin->id,
                    'status' => AttemptStatus::InProgress,
                    'started_at' => now(),
                    'last_seen_at' => now(),
                    'device_session_hash' => $deviceSessionHash,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);

                $lockedAssignment->update(['status' => AssignmentStatus::Started]);
            }

            $this->assertDevice($attempt, $deviceSessionHash);

            return $attempt->refresh();
        });
    }

    public function saveAnswer(ExamAttempt $attempt, ExamQuestion $question, string $answer): ExamAnswer
    {
        if ($attempt->status !== AttemptStatus::InProgress) {
            throw ValidationException::withMessages(['exam' => 'Ujian sudah tidak menerima jawaban.']);
        }

        if ($this->isExpired($attempt)) {
            $this->submit($attempt);
            throw ValidationException::withMessages(['exam' => 'Waktu ujian telah berakhir dan jawaban dikumpulkan otomatis.']);
        }

        $attempt->loadMissing('assignment');
        if ($question->assessment_subject_id !== $attempt->assignment->assessment_subject_id) {
            throw ValidationException::withMessages(['answer' => 'Soal tidak termasuk dalam ujian ini.']);
        }

        if (! array_key_exists($answer, $question->options)) {
            throw ValidationException::withMessages(['answer' => 'Pilihan jawaban tidak tersedia.']);
        }

        $saved = ExamAnswer::query()->updateOrCreate(
            [
                'exam_attempt_id' => $attempt->id,
                'exam_question_id' => $question->id,
            ],
            [
                'answer' => $answer,
                'is_correct' => $answer === $question->correct_answer,
                'answered_at' => now(),
            ],
        );

        $attempt->update(['last_seen_at' => now()]);

        return $saved;
    }

    public function submit(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt): ExamAttempt {
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($lockedAttempt->status === AttemptStatus::Submitted) {
                return $lockedAttempt;
            }

            $lockedAttempt->load(['assignment.assessmentSubject.questions', 'answers']);
            $questions = $lockedAttempt->assignment->assessmentSubject->questions;
            $answers = $lockedAttempt->answers->keyBy('exam_question_id');
            $totalPoints = (float) $questions->sum(fn (ExamQuestion $question) => (float) $question->points);
            $earnedPoints = (float) $questions->sum(function (ExamQuestion $question) use ($answers): float {
                return $answers->get($question->id)?->is_correct ? (float) $question->points : 0.0;
            });
            $correct = $answers->where('is_correct', true)->count();

            $lockedAttempt->update([
                'status' => AttemptStatus::Submitted,
                'submitted_at' => now(),
                'last_seen_at' => now(),
                'score' => $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0,
                'correct_answers' => $correct,
                'incorrect_answers' => max(0, $questions->count() - $correct),
            ]);
            $lockedAttempt->assignment->update(['status' => AssignmentStatus::Completed]);

            return $lockedAttempt->refresh();
        });
    }

    public function deadline(ExamAttempt $attempt): Carbon
    {
        $attempt->loadMissing('assignment.examSession');
        $session = $attempt->assignment->examSession;
        $personalDeadline = $attempt->started_at->copy()->addMinutes($session->duration_minutes);

        return $personalDeadline->lt($session->ends_at) ? $personalDeadline : $session->ends_at->copy();
    }

    public function isExpired(ExamAttempt $attempt): bool
    {
        $attempt->loadMissing('assignment.examSession');

        return $attempt->assignment->examSession->status === SessionStatus::Closed
            || now()->gte($this->deadline($attempt));
    }

    public function assertDevice(ExamAttempt $attempt, string $deviceSessionHash): void
    {
        if (! hash_equals($attempt->device_session_hash, $deviceSessionHash)) {
            throw ValidationException::withMessages([
                'device' => 'Ujian sudah dibuka pada sesi perangkat lain. Hubungi pengawas untuk pemeriksaan.',
            ]);
        }
    }
}
