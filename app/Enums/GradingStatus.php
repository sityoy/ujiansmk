<?php

namespace App\Enums;

enum GradingStatus: string
{
    case Automatic = 'automatic';
    case Pending = 'pending';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Dinilai otomatis',
            self::Pending => 'Menunggu koreksi',
            self::Graded => 'Selesai dikoreksi',
        };
    }
}
