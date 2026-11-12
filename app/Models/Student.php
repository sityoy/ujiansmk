<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

#[Fillable(['user_id', 'school_class_id', 'student_number', 'nisn', 'card_uid_hash', 'full_name', 'is_active'])]
#[Hidden(['card_uid_hash'])]
class Student extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function printablePassword(): ?string
    {
        $user = $this->user;
        if (! $user) {
            return null;
        }

        $credentials = $user->student_card_credentials;
        if ($credentials && hash_equals($user->password, $credentials['hash'])) {
            return $credentials['password'];
        }

        // Older accounts may have used NISN or NIS without retaining printable credentials.
        foreach (array_unique([$user->username, $this->student_number, $this->nisn]) as $candidate) {
            if (filled($candidate) && Hash::check($candidate, $user->password)) {
                return $candidate;
            }
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function examAssignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    public function examRooms(): HasMany
    {
        return $this->hasMany(StudentExamRoom::class);
    }

    public function dailyCheckins(): HasMany
    {
        return $this->hasMany(DailyCheckin::class);
    }

    public function midtermSubjectResults(): HasMany
    {
        return $this->hasMany(MidtermSubjectResult::class);
    }

    public function extracurriculars(): BelongsToMany
    {
        return $this->belongsToMany(Extracurricular::class, 'extracurricular_participants')->withTimestamps();
    }

    public function extracurricularGrades(): HasMany
    {
        return $this->hasMany(ExtracurricularGrade::class);
    }

    public function midtermAttendanceSummaries(): HasMany
    {
        return $this->hasMany(MidtermAttendanceSummary::class);
    }
}
