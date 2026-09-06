<?php

namespace App\Services\Reports;

use App\Enums\AssessmentType;
use App\Enums\AttemptStatus;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Extracurricular;
use App\Models\MidtermAttendanceSummary;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class MidtermReportService
{
    public function build(AssessmentPeriod $period, SchoolClass $schoolClass): array
    {
        if ($period->type !== AssessmentType::ATS) {
            throw new InvalidArgumentException('Rapor hanya tersedia untuk Asesmen Tengah Semester.');
        }

        if ($period->academic_year_id !== $schoolClass->academic_year_id) {
            throw new InvalidArgumentException('Periode asesmen dan kelas berasal dari tahun ajaran yang berbeda.');
        }

        $subjects = AssessmentSubject::query()
            ->with(['subject', 'midtermResults'])
            ->where('assessment_period_id', $period->id)
            ->where('school_class_id', $schoolClass->id)
            ->get()
            ->sortBy(fn (AssessmentSubject $item) => $item->subject->name)
            ->values();

        $subjectIds = $subjects->pluck('id');
        $extracurriculars = Extracurricular::query()
            ->where('academic_year_id', $period->academic_year_id)
            ->where('is_active', true)
            ->whereHas('participants', fn ($query) => $query->where('school_class_id', $schoolClass->id))
            ->with([
                'participants' => fn ($query) => $query->where('school_class_id', $schoolClass->id),
                'grades' => fn ($query) => $query->where('assessment_period_id', $period->id),
            ])
            ->orderBy('name')
            ->get();
        $attendance = MidtermAttendanceSummary::query()
            ->where('assessment_period_id', $period->id)
            ->whereHas('student', fn ($query) => $query->where('school_class_id', $schoolClass->id))
            ->get()
            ->keyBy('student_id');

        $students = Student::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->with([
                'examAssignments' => fn ($query) => $query
                    ->whereIn('assessment_subject_id', $subjectIds)
                    ->with('attempt'),
            ])
            ->orderBy('full_name')
            ->get();

        $rows = $students->map(function (Student $student) use ($subjects, $extracurriculars, $attendance): array {
            $assignments = $student->examAssignments->keyBy('assessment_subject_id');
            $scores = [];
            $descriptions = [];
            $total = 0.0;
            $submittedCount = 0;

            foreach ($subjects as $assessmentSubject) {
                $attempt = $assignments->get($assessmentSubject->id)?->attempt;
                $result = $assessmentSubject->midtermResults->firstWhere('student_id', $student->id);
                $attemptScore = $attempt?->status === AttemptStatus::Submitted && $attempt->score !== null
                    ? (float) $attempt->score
                    : null;
                $score = $result ? (float) $result->score : $attemptScore;

                $scores[$assessmentSubject->id] = $score;
                $descriptions[$assessmentSubject->id] = $result?->description
                    ?: ($score !== null ? $this->subjectDescription($score, $assessmentSubject->learning_objective) : null);

                if ($score !== null) {
                    $total += $score;
                    $submittedCount++;
                }
            }

            $subjectCount = $subjects->count();

            return [
                'student' => $student,
                'scores' => $scores,
                'descriptions' => $descriptions,
                'total' => round($total, 2),
                'average' => $subjectCount > 0 ? round($total / $subjectCount, 2) : 0.0,
                'submitted_count' => $submittedCount,
                'subject_count' => $subjectCount,
                'is_complete' => $subjectCount > 0 && $submittedCount === $subjectCount,
                'rank' => null,
                'extracurriculars' => $extracurriculars->map(function (Extracurricular $extracurricular) use ($student): array {
                    $grade = $extracurricular->grades->firstWhere('student_id', $student->id);

                    return [
                        'activity' => $extracurricular,
                        'rating' => $grade?->rating,
                        'description' => $grade?->description,
                    ];
                })->filter(fn (array $item) => $item['activity']->participants->contains('id', $student->id))->values(),
                'attendance' => $attendance->get($student->id) ?? new MidtermAttendanceSummary([
                    'sick_days' => 0,
                    'excused_days' => 0,
                    'unexcused_days' => 0,
                ]),
            ];
        });

        $rows = $this->applyRanking($rows, $subjects->isNotEmpty());

        return [
            'period' => $period->loadMissing('academicYear'),
            'schoolClass' => $schoolClass->loadMissing(['academicYear', 'homeroomTeacher']),
            'subjects' => $subjects,
            'extracurriculars' => $extracurriculars,
            'rows' => $rows,
            'is_complete' => $rows->isNotEmpty() && $rows->every(fn (array $row) => $row['is_complete']),
        ];
    }

    public function subjectDescription(float $score, ?string $learningObjective): string
    {
        $objective = rtrim(trim((string) $learningObjective), ". \t\n\r\0\x0B");
        $objective = preg_replace('/^(peserta didik|siswa)\s+(mampu\s+)?/iu', '', $objective) ?? $objective;
        $target = $objective !== '' ? lcfirst($objective) : 'kompetensi yang dinilai pada ATS';

        return match (true) {
            $score >= 86 => 'Menunjukkan penguasaan sangat baik dalam '.$target.'.',
            $score >= 76 => 'Menunjukkan penguasaan baik dalam '.$target.'.',
            $score >= 66 => 'Menunjukkan penguasaan cukup dalam '.$target.' dan perlu meningkatkan konsistensi.',
            default => 'Perlu peningkatan dan bimbingan dalam '.$target.'.',
        };
    }

    private function applyRanking(Collection $rows, bool $hasSubjects): Collection
    {
        $rows = $rows
            ->sort(function (array $left, array $right): int {
                $scoreComparison = $right['total'] <=> $left['total'];

                return $scoreComparison !== 0
                    ? $scoreComparison
                    : strcasecmp($left['student']->full_name, $right['student']->full_name);
            })
            ->values();

        $lastTotal = null;
        $currentRank = 0;

        return $rows->map(function (array $row, int $index) use (&$lastTotal, &$currentRank, $hasSubjects): array {
            if (! $hasSubjects) {
                return $row;
            }

            if ($lastTotal === null || abs($row['total'] - $lastTotal) > 0.0001) {
                $currentRank = $index + 1;
                $lastTotal = $row['total'];
            }

            $row['rank'] = $currentRank;

            return $row;
        });
    }
}
