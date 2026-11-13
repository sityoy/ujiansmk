<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'username', 'password', 'role', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'remember_token', 'student_card_credentials'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'student_card_credentials' => 'encrypted:array',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function requiresPasswordChange(): bool
    {
        return $this->must_change_password && $this->role !== UserRole::Student;
    }

    public function setStudentPassword(string $password): void
    {
        $this->password = $password;
        $this->student_card_credentials = ['password' => $password, 'hash' => $this->password];
        $this->must_change_password = false;
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function homeroomClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'homeroom_teacher_user_id');
    }

    public function coachedExtracurriculars(): HasMany
    {
        return $this->hasMany(Extracurricular::class, 'coach_user_id');
    }
}
