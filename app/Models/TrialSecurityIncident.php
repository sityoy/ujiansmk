<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trial_attempt_id', 'event_id', 'category', 'details', 'severity', 'occurred_at'])]
class TrialSecurityIncident extends Model
{
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TrialAttempt::class, 'trial_attempt_id');
    }
}
