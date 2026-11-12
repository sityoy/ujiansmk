<?php

namespace App\Services\Exams;

use App\Models\TrialAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrialExamSecurityService
{
    public const LIMIT = 2;

    public const CLIENT_CATEGORIES = [
        'tab_hidden',
        'fullscreen_exit',
        'window_blur',
        'system_gesture',
        'viewport_change',
        'restricted_action',
        'navigation_attempt',
    ];

    public function state(TrialAttempt $attempt): array
    {
        return [
            'status' => $attempt->status,
            'enabled' => true,
            'locked' => $attempt->status === 'in_progress' && $attempt->security_locked_at !== null,
            'violations' => (int) $attempt->violation_count,
            'limit' => self::LIMIT,
            'deadline' => app(TrialExamAttemptService::class)->deadline($attempt)->toIso8601String(),
            'server_time' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $context */
    public function record(TrialAttempt $attempt, string $category, string $eventId, array $context = []): array
    {
        return DB::transaction(function () use ($attempt, $category, $eventId, $context): array {
            $attempt = TrialAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $attempts = app(TrialExamAttemptService::class);

            if ($attempt->status !== 'in_progress') {
                return $this->state($attempt);
            }
            if ($attempts->isExpired($attempt)) {
                return $this->state($attempts->submit($attempt, [], true));
            }
            if ($attempt->security_locked_at || $attempt->securityIncidents()->where('event_id', $eventId)->exists()) {
                return $this->state($attempt);
            }

            $lastReset = $attempt->securityIncidents()->where('category', 'supervisor_reset')->max('id') ?? 0;
            $duplicateSignal = $attempt->securityIncidents()->where('severity', 1)->where('id', '>', $lastReset)
                ->where('occurred_at', '>=', now()->subSeconds(3))->exists();
            $attempt->securityIncidents()->create([
                'event_id' => $eventId,
                'category' => $category,
                'severity' => $duplicateSignal ? 0 : 1,
                'details' => ['counted' => ! $duplicateSignal, 'context' => $context],
                'occurred_at' => now(),
            ]);

            if (! $duplicateSignal) {
                $attempt->violation_count = min(self::LIMIT, $attempt->violation_count + 1);
                if ($attempt->violation_count >= self::LIMIT) {
                    $attempt->security_locked_at = now();
                    $attempt->security_lock_version++;
                }
                $attempt->last_seen_at = now();
                $attempt->save();
            }

            return $this->state($attempt);
        });
    }

    public function resetViolations(TrialAttempt $attempt, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($attempt, $reviewer, $reason): void {
            $attempt = TrialAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status !== 'in_progress') {
                throw ValidationException::withMessages(['security' => 'Pelanggaran hanya dapat direset ketika ujian masih berlangsung.']);
            }
            if (app(TrialExamAttemptService::class)->isExpired($attempt)) {
                throw ValidationException::withMessages(['security' => 'Waktu ujian sudah habis.']);
            }
            if ($attempt->violation_count < 1 && ! $attempt->security_locked_at) {
                throw ValidationException::withMessages(['security' => 'Peserta tidak memiliki pelanggaran yang perlu direset.']);
            }

            $resetNumber = app(ExamResetAllowance::class)->consume($attempt, $reviewer);
            $previousCount = (int) $attempt->violation_count;
            $previousVersion = (int) $attempt->security_lock_version;
            $attempt->forceFill([
                'violation_count' => 0,
                'security_locked_at' => null,
                'security_lock_version' => $previousVersion + 1,
                'last_seen_at' => now(),
            ])->save();
            $attempt->securityIncidents()->create([
                'category' => 'supervisor_reset',
                'severity' => 0,
                'occurred_at' => now(),
                'details' => [
                    'reviewer_id' => $reviewer->id,
                    'reviewer_name' => $reviewer->name,
                    'reason' => $reason,
                    'previous_count' => $previousCount,
                    'reset_number' => $resetNumber,
                ],
            ]);
        });
    }
}
