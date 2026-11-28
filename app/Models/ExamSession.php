<?php

namespace App\Models;

use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'assessment_subject_id', 'campus_id', 'source_session_id', 'room_name', 'kind', 'status',
    'starts_at', 'ends_at', 'duration_minutes', 'access_token_hash', 'schedule_group_id',
])]
#[Hidden(['access_token_hash'])]
class ExamSession extends Model
{
    public const MIN_DURATION_MINUTES = 10;

    public const MAX_DURATION_MINUTES = 600;

    public function formattedWindow(): string
    {
        $endFormat = $this->starts_at->isSameDay($this->ends_at) ? 'H:i' : 'd/m/Y H:i';

        return $this->starts_at->format('d/m/Y H:i').'–'.$this->ends_at->format($endFormat);
    }

    protected function casts(): array
    {
        return [
            'kind' => SessionKind::class,
            'status' => SessionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scheduleGroup(): Builder
    {
        return $this->schedule_group_id
            ? self::query()->where('schedule_group_id', $this->schedule_group_id)
            : self::query()->whereKey($this->id);
    }

    public function scopeAcceptsRegularRoster(Builder $query): Builder
    {
        return $query->where('kind', SessionKind::Regular)
            ->where('status', '!=', SessionStatus::Closed)
            ->where('ends_at', '>', now());
    }

    public function assessmentSubject(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubject::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function sourceSession(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_session_id');
    }

    public function makeupSessions(): HasMany
    {
        return $this->hasMany(self::class, 'source_session_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }
}
