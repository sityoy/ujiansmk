<?php

namespace App\Enums;

enum AssessmentType: string
{
    case ATS = 'ats';
    case AAS = 'aas';
    case AAT = 'aat';
    case UUB = 'uub';
    case Other = 'other';
}
