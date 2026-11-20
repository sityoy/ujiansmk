<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Active = 'active';
    case Closed = 'closed';
}
