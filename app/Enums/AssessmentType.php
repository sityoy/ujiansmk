<?php

namespace App\Enums;

enum AssessmentType: string
{
    case ATS = 'ats';
    // Preserve the persisted value so existing periods and filters remain valid.
    case ASAS = 'aas';
    case AAT = 'aat';
    case UUB = 'uub';
    case Other = 'other';

    public function label(): string
    {
        return $this === self::ASAS ? 'ASAS' : ($this === self::Other ? 'Lainnya' : strtoupper($this->value));
    }
}
