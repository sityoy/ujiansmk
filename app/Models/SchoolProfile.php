<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'npsn', 'address', 'city', 'phone', 'email', 'principal_name', 'letterhead_path', 'logo_path',
])]
class SchoolProfile extends Model
{
    public static function logoUrl(?self $profile): ?string
    {
        if ($profile?->logo_path) {
            return route('school.logo', ['v' => basename($profile->logo_path)]);
        }

        return self::usesLegacyBrand($profile) ? asset('images/logo-ypmi-bahagia.jpeg') : null;
    }

    public static function usesLegacyBrand(?self $profile): bool
    {
        if (config('tenant.npsn') || config('tenant.school_code')) {
            return false;
        }

        return $profile === null || in_array(Str::lower(Str::squish((string) $profile->name)), [
            'smk islam bahagia', 'smks islam bahagia', 'smp islam bahagia',
        ], true);
    }

    public static function letterheadFile(?self $profile): ?string
    {
        if ($profile?->letterhead_path && Storage::exists($profile->letterhead_path)) {
            return Storage::path($profile->letterhead_path);
        }

        return ! $profile?->logo_path && self::usesLegacyBrand($profile)
            ? public_path('images/kop-surat-smk-islam-bahagia.png') : null;
    }

    public static function letterheadData(?self $profile): ?string
    {
        $path = self::letterheadFile($profile);
        if ($path === null || ! is_readable($path)) {
            return null;
        }

        return 'data:'.mime_content_type($path).';base64,'.base64_encode((string) file_get_contents($path));
    }
}
