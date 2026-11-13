<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Scheduled = 'scheduled';
    case Absent = 'absent';
    case Started = 'started';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
