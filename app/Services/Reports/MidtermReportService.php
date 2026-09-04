<?php

namespace App\Services\Reports;

use App\Enums\AssessmentType;
use App\Enums\AttemptStatus;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
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
            ->with('subject')
            ->where('assessment_period_id', $period->id)
            ->where('school_class_id', $schoolClass->id)
            ->get()
            ->sortBy(fn (AssessmentSubject $item) => $item->subject->name)
            ->values();

        $subjectIds = $subjects->pluck('id');

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

        $rows = $students->map(function (Student $student) use ($subjects): array {
            $assignments = $student->examAssignments->keyBy('assessment_subject_id');
            $scores = [];
            $total = 0.0;
            $submittedCount = 0;

            foreach ($subjects as $assessmentSubject) {
                $attempt = $assignments->get($assessmentSubject->id)?->attempt;
                $score = $attempt?->status === AttemptStatus::Submitted && $attempt->score !== null
                    ? (float) $attempt->score
                    : null;

                $scores[$assessmentSubject->id] = $score;

                if ($score !== null) {
                    $total += $score;
                    $submittedCount++;
                }
            }

            $subjectCount = $subjects->count();

            return [
                'student' => $student,
                'scores' => $scores,
                'total' => round($total, 2),
                'average' => $subjectCount > 0 ? round($total / $subjectCount, 2) : 0.0,
                'submitted_count' => $submittedCount,
                'subject_count' => $subjectCount,
                'is_complete' => $subjectCount > 0 && $submittedCount === $subjectCount,
                'rank' => null,
            ];
        });

        $rows = $this->applyRanking($rows, $subjects->isNotEmpty());

        return [
            'period' => $period->loadMissing('academicYear'),
            'schoolClass' => $schoolClass->loadMissing('academicYear'),
            'subjects' => $subjects,
            'rows' => $rows,
            'is_complete' => $rows->isNotEmpty() && $rows->every(fn (array $row) => $row['is_complete']),
        ];
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
