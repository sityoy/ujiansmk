<?php

namespace App\Services\Reports;

use App\Enums\UserRole;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Extracurricular;
use App\Models\SchoolClass;
use App\Models\User;

class MidtermReportAccess
{
    public function canView(User $user, AssessmentPeriod $period, SchoolClass $schoolClass): bool
    {
        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::Committee, UserRole::Principal], true)) {
            return true;
        }

        if ($user->role !== UserRole::Teacher) {
            return false;
        }

        return $this->canRecordAttendance($user, $schoolClass)
            || AssessmentSubject::query()
                ->where('assessment_period_id', $period->id)
                ->where('school_class_id', $schoolClass->id)
                ->where('teacher_user_id', $user->id)
                ->exists()
            || Extracurricular::query()
                ->where('academic_year_id', $period->academic_year_id)
                ->where('coach_user_id', $user->id)
                ->whereHas('participants', fn ($query) => $query->where('school_class_id', $schoolClass->id))
                ->exists();
    }

    public function canManageSubject(User $user, AssessmentSubject $subject): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Committee], true)
            || ($user->role === UserRole::Teacher && $subject->teacher_user_id === $user->id);
    }

    public function canRecordAttendance(User $user, SchoolClass $schoolClass): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Committee], true)
            || ($user->role === UserRole::Teacher && $schoolClass->homeroom_teacher_user_id === $user->id);
    }

    public function canManageExtracurricular(User $user, Extracurricular $extracurricular): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Committee], true)
            || ($user->role === UserRole::Teacher && $extracurricular->coach_user_id === $user->id);
    }

    public function canConfigure(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Committee], true);
    }
}
