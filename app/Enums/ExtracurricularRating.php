<?php

namespace App\Enums;

enum ExtracurricularRating: string
{
    case VeryGood = 'very_good';
    case Good = 'good';
    case Sufficient = 'sufficient';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::VeryGood => 'Sangat Baik',
            self::Good => 'Baik',
            self::Sufficient => 'Cukup',
            self::Poor => 'Kurang',
        };
    }

    public function defaultDescription(string $activity): string
    {
        return match ($this) {
            self::VeryGood => "Menunjukkan keaktifan, kedisiplinan, dan penguasaan yang sangat baik dalam kegiatan {$activity}.",
            self::Good => "Menunjukkan keaktifan, kedisiplinan, dan kemampuan yang baik dalam kegiatan {$activity}.",
            self::Sufficient => "Cukup aktif mengikuti kegiatan {$activity} dan perlu meningkatkan konsistensi serta keterampilan.",
            self::Poor => "Perlu meningkatkan keaktifan, kedisiplinan, dan keterampilan dalam kegiatan {$activity}.",
        };
    }
}
