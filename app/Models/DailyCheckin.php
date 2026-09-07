<?php

namespace App\Models;

use App\Enums\CheckinMethod;
use App\Enums\CheckinStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'student_id', 'campus_id', 'attendance_date', 'method', 'status', 'card_reference_hash',
    'selfie_path', 'latitude', 'longitude', 'accuracy_meters', 'distance_meters',
    'verified_at', 'verified_by',
])]
#[Hidden(['card_reference_hash', 'selfie_path'])]
class DailyCheckin extends Model
{
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'method' => CheckinMethod::class,
            'status' => CheckinStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_meters' => 'decimal:2',
            'distance_meters' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
