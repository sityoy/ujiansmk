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
}
