<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'exam_assignment_id', 'daily_checkin_id', 'status', 'started_at', 'submitted_at',
    'last_seen_at', 'score', 'correct_answers', 'incorrect_answers', 'violation_count',
    'device_session_hash', 'ip_address', 'user_agent',
])]
#[Hidden(['device_session_hash'])]
class ExamAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class, 'exam_assignment_id');
    }

    public function dailyCheckin(): BelongsTo
    {
        return $this->belongsTo(DailyCheckin::class);
    }

    public function securityIncidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }
}
