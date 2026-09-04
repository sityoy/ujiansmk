<?php

namespace App\Services\Exams;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamSecurityService
{
    public const LIMIT = 2;

    public function state(ExamAttempt $attempt): array
    {
        return [
            'status' => $attempt->status->value,
            'enabled' => $attempt->security_enabled,
            'locked' => $attempt->status === AttemptStatus::InProgress && $attempt->security_locked_at !== null,
            'violations' => (int) $attempt->violation_count,
            'limit' => self::LIMIT,
            'deadline' => app(ExamAttemptService::class)->deadline($attempt)->toIso8601String(),
            'server_time' => now()->toIso8601String(),
        ];
    }

    public function record(ExamAttempt $attempt, string $category, string $eventId): array
    {
        return DB::transaction(function () use ($attempt, $category, $eventId): array {
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $attempts = app(ExamAttemptService::class);
            if ($attempt->status !== AttemptStatus::InProgress) {
                return $this->state($attempt);
            }
            if ($attempts->isExpired($attempt)) {
                return $this->state($attempts->submit($attempt));
            }
            if ($attempt->security_locked_at || $attempt->securityIncidents()->where('event_id', $eventId)->exists()) {
                return $this->state($attempt);
            }

            // A tab switch may also exit fullscreen. Group signals received within 3 seconds.
            $duplicateSignal = $attempt->securityIncidents()->where('severity', 1)
                ->where('occurred_at', '>=', now()->subSeconds(3))->exists();
            $attempt->securityIncidents()->create([
                'event_id' => $eventId, 'category' => $category,
                'severity' => $duplicateSignal ? 0 : 1,
                'details' => ['counted' => ! $duplicateSignal], 'occurred_at' => now(),
            ]);

            if (! $duplicateSignal) {
                $count = $attempt->security_enabled
                    ? min(self::LIMIT, $attempt->violation_count + 1)
                    : $attempt->violation_count + 1;
                $attempt->violation_count = $count;
                if ($attempt->security_enabled && $count >= self::LIMIT) {
                    $attempt->security_locked_at = now();
                    $attempt->security_lock_version++;
                }
                $attempt->save();
            }

            return $this->state($attempt);
        });
    }

    public function review(ExamAttempt $attempt, User $reviewer, string $action, string $reason, int $version): void
    {
        DB::transaction(function () use ($attempt, $reviewer, $action, $reason, $version): void {
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status !== AttemptStatus::InProgress || ! $attempt->security_locked_at
                || $attempt->security_lock_version !== $version) {
                throw ValidationException::withMessages(['security' => 'Status kunci sudah berubah. Muat ulang halaman sebelum memeriksa.']);
            }
            $attempts = app(ExamAttemptService::class);
            if ($action === 'resume' && $attempts->isExpired($attempt)) {
                throw ValidationException::withMessages(['security' => 'Waktu ujian sudah habis. Pilih kumpulkan jawaban; ujian tidak dapat dibuka ulang.']);
            }

            $attempt->securityIncidents()->create([
                'category' => 'supervisor_'.$action, 'severity' => 0, 'occurred_at' => now(),
                'details' => ['reviewer_id' => $reviewer->id, 'reviewer_name' => $reviewer->name,
                    'reason' => $reason, 'lock_version' => $version],
            ]);
            if ($action === 'submit') {
                $attempts->submit($attempt);
            } else {
                // Keep the counter, answers and original deadline. Another incident locks again.
                $attempt->update(['security_locked_at' => null]);
            }
        });
    }
}
