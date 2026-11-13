<?php

namespace App\Services\Exams;

use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\SessionStatus;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamSessionService
{
    public function update(ExamSession $session, array $attributes, array $attributesBySession = []): void
    {
        DB::transaction(function () use ($session, $attributes, $attributesBySession): void {
            $group = $session->scheduleGroup()->orderBy('id')->lockForUpdate()->get();
            abort_unless($group->contains('id', $session->id), 404);
            if ($attributesBySession !== [] && $group->modelKeys() !== array_keys($attributesBySession)) {
                throw ValidationException::withMessages(['session' => 'Daftar rombel sesi berubah. Muat ulang Penjadwalan.']);
            }

            foreach ($group as $locked) {
                $this->updateLocked($locked, $attributesBySession[$locked->id] ?? $attributes);
            }
        }, 3);
    }

    private function updateLocked(ExamSession $locked, array $attributes): void
    {
        $attempts = ExamAttempt::query()->lockForUpdate()->whereHas('assignment', fn ($query) => $query
            ->where('exam_session_id', $locked->id));
        $hasAttempts = $attempts->exists();
        $locked->fill($attributes);

        if ($hasAttempts && $locked->isDirty([
            'assessment_subject_id', 'campus_id', 'kind', 'source_session_id',
            'room_name', 'starts_at', 'ends_at', 'duration_minutes',
        ])) {
            throw ValidationException::withMessages([
                'session' => 'Jadwal, lokasi, dan durasi terkunci karena siswa sudah mulai ujian. Gunakan Pelaksanaan ujian untuk menutup sesi.',
            ]);
        }

        $previousStatus = SessionStatus::from($locked->getRawOriginal('status'));
        if (($previousStatus === SessionStatus::Closed && $locked->status !== SessionStatus::Closed)
            || ($hasAttempts && $locked->status === SessionStatus::Draft)) {
            throw ValidationException::withMessages([
                'status' => 'Sesi yang ditutup tidak dapat dibuka ulang; sesi yang sudah dikerjakan tidak dapat dikembalikan ke draf.',
            ]);
        }

        $locked->save();

        if ($locked->status === SessionStatus::Closed) {
            $attempts->where('status', AttemptStatus::InProgress)
                ->eachById(fn (ExamAttempt $attempt) => app(ExamAttemptService::class)->submit($attempt));

            // No new assignment/attempt is created for participants who never started.
            // A session cancelled before its start does not imply student absence.
            if (now()->gte($locked->starts_at)) {
                $locked->assignments()->where('status', AssignmentStatus::Scheduled)
                    ->whereDoesntHave('attempt')->update(['status' => AssignmentStatus::Absent]);
            }
        }
    }
}
