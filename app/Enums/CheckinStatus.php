<?php

namespace App\Enums;

enum CheckinStatus: string
{
    case Verified = 'verified';
    case Review = 'review';
    case Rejected = 'rejected';
}
