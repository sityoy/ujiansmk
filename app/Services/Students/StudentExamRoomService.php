<?php

namespace App\Services\Students;

use App\Models\AssessmentPeriod;
use App\Models\Student;
use App\Models\StudentExamRoom;
use Illuminate\Validation\ValidationException;

class StudentExamRoomService
{
    /** @param array<int, int|string> $studentIds */
    public function assign(AssessmentPeriod $period, array $studentIds, ?string $roomName): void
    {
        $studentIds = array_values(array_unique($studentIds));
        $count = Student::query()->whereKey($studentIds)
            ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $period->academic_year_id))->count();
        if ($count !== count($studentIds)) {
            throw ValidationException::withMessages([
                'assessment_period_id' => 'Seluruh siswa harus berada pada tahun ajaran periode ujian yang dipilih.',
            ]);
        }

        $roomName = trim($roomName ?? '');
        if ($roomName === '') {
            StudentExamRoom::query()->where('assessment_period_id', $period->id)->whereIn('student_id', $studentIds)->delete();

            return;
        }

        StudentExamRoom::upsert(array_map(fn ($studentId) => [
            'assessment_period_id' => $period->id,
            'student_id' => $studentId,
            'room_name' => $roomName,
        ], $studentIds), ['assessment_period_id', 'student_id'], ['room_name', 'updated_at']);
    }
}
