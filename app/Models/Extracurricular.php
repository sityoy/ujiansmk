<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_year_id', 'name', 'coach_user_id', 'is_active'])]
class Extracurricular extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'extracurricular_participants')->withTimestamps();
    }

    public function grades(): HasMany
    {
        return $this->hasMany(ExtracurricularGrade::class);
    }
}
