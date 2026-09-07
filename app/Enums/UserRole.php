<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Committee = 'committee';
    case Teacher = 'teacher';
    case Proctor = 'proctor';
    case Principal = 'principal';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Committee => 'Panitia',
            self::Teacher => 'Guru',
            self::Proctor => 'Pengawas',
            self::Principal => 'Kepala Sekolah',
            self::Student => 'Siswa',
        };
    }
}
