<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Models\AssessmentPeriod;
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ScheduleRecapController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    private const WEEKDAYS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    private const KINDS = ['regular' => 'Reguler', 'makeup' => 'Susulan'];

    private const STATUSES = [
        'published' => 'Terbit',
        'active' => 'Aktif',
        'draft' => 'Draf',
        'closed' => 'Selesai',
    ];

    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'session_period_id' => ['nullable', 'integer', 'min:1'],
            'session_class_id' => ['nullable', 'integer', 'min:1'],
            'session_grade_level' => ['nullable', 'integer', 'min:1'],
            'session_weekday' => ['nullable', 'integer', 'between:1,7'],
            'session_search' => ['nullable', 'string', 'max:255'],
            'session_kind' => ['nullable', Rule::enum(SessionKind::class)],
            'session_status' => ['nullable', Rule::in(['ready', ...array_keys(self::STATUSES)])],
        ]);
        $periodId = $filters['session_period_id'] ?? null;
        $classId = $filters['session_class_id'] ?? null;
        $gradeLevel = $filters['session_grade_level']
            ?? ($classId ? SchoolClass::whereKey($classId)->value('grade_level') : null);
        $gradeLevel = $gradeLevel === null ? null : (int) $gradeLevel;
        $weekday = isset($filters['session_weekday']) ? (int) $filters['session_weekday'] : null;
        $weekdayLabel = $weekday === null ? null : self::WEEKDAYS[$weekday];
        $search = trim($filters['session_search'] ?? '');
        $kind = $filters['session_kind'] ?? null;
        $status = $filters['session_status'] ?? null;

        $sessions = ExamSession::query()
            ->whereHas('assessmentSubject', function (Builder $query) use ($periodId, $classId, $gradeLevel, $search): void {
                $query->when($periodId, fn (Builder $subjects) => $subjects->where('assessment_period_id', $periodId))
                    ->when($gradeLevel !== null, fn (Builder $subjects) => $subjects
                        ->whereHas('schoolClass', fn (Builder $classes) => $classes->where('grade_level', $gradeLevel)))
                    ->when($classId && $gradeLevel === null, fn (Builder $subjects) => $subjects->where('school_class_id', $classId));

                if ($search !== '') {
                    $query->whereHas('subject', fn (Builder $subject) => $subject
                        ->where(fn (Builder $matches) => $matches->where('name', 'like', '%'.$search.'%')
                            ->orWhere('code', 'like', '%'.$search.'%')));
                }
            })
            ->when($kind, fn (Builder $query) => $query->where('kind', $kind))
            ->when($status, fn (Builder $query) => $status === 'ready'
                ? $query->whereIn('status', [SessionStatus::Published, SessionStatus::Active])
                : $query->where('status', $status))
            ->with(['assessmentSubject.assessmentPeriod.academicYear', 'assessmentSubject.schoolClass', 'assessmentSubject.subject', 'campus'])
            ->withCount(['assignments as participant_count' => fn (Builder $query) => $query
                ->visibleInRoster()->where('status', '!=', AssignmentStatus::Cancelled)])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->when($weekday !== null, fn ($sessions) => $sessions
                ->filter(fn (ExamSession $session) => $session->starts_at->copy()->timezone(self::TIMEZONE)->isoWeekday() === $weekday));

        $periods = AssessmentPeriod::with('academicYear')->orderByDesc('starts_on')->get();
        $period = $periods->find($periodId);
        $gradeOptions = SchoolClass::query()->distinct()->orderBy('grade_level')->pluck('grade_level')
            ->mapWithKeys(fn (int $level) => [$level => $this->gradeLabel($level)]);
        $school = SchoolProfile::query()->first();
        $schoolName = $school?->name ?? config('app.name');
        $statuses = ['ready' => 'Terbit atau aktif', ...self::STATUSES];
        $filterLabel = collect([
            $weekdayLabel ? 'Hari '.$weekdayLabel : null,
            $kind ? self::KINDS[$kind] : null,
            $status ? $statuses[$status] : null,
            $search !== '' ? 'Pencarian “'.$search.'”' : null,
        ])->filter()->implode(' · ');
        $hasDraft = $sessions->contains('status', SessionStatus::Draft);
        $groups = $sessions->groupBy('assessmentSubject.assessment_period_id')
            ->map(fn (Collection $group) => [
                'period' => $this->periodLabel($group->first()->assessmentSubject->assessmentPeriod),
                'rows' => $group->groupBy($this->scheduleKey(...))->map($this->row(...)),
            ]);
        $rows = $groups->flatMap(fn (array $group) => $group['rows']->values());

        $shareLines = ['*Jadwal Ujian*', $schoolName];
        if ($hasDraft) {
            $shareLines[] = 'Rekap memuat draf. Jadwal draf masih dapat berubah dan menunggu penerbitan panitia.';
        }
        foreach ($groups as $group) {
            $shareLines[] = "\n*".$group['period'].'*';
            foreach ($group['rows']->groupBy('date') as $date => $dateRows) {
                $shareLines[] = "*{$date}*";
                foreach ($dateRows as $row) {
                    $shareLines[] = "• {$row['time']} WIB · {$row['subject']} · {$row['grade']} · {$row['kind']} ({$row['status']}) · {$row['duration']} menit";
                    foreach ($row['locations'] as $location) {
                        $shareLines[] = "  {$location['campus']} · {$location['rooms']}";
                    }
                }
            }
        }

        return response()->view('scheduling.recap', [
            'school' => $school,
            'periods' => $periods,
            'gradeOptions' => $gradeOptions,
            'gradeLevel' => $gradeLevel,
            'weekdays' => self::WEEKDAYS,
            'weekday' => $weekday,
            'filename' => 'rekap-jadwal-ujian'.($weekdayLabel ? '-'.strtolower($weekdayLabel) : ''),
            'kinds' => self::KINDS,
            'statuses' => $statuses,
            'filters' => $filters,
            'filterLabel' => $filterLabel,
            'backUrl' => route('scheduling.index', Arr::only($filters, ['session_period_id', 'session_class_id', 'session_search'])).'#sesi-ujian',
            'groups' => $groups,
            'scope' => ($period ? $this->periodLabel($period) : 'Semua periode').' · '.($gradeLevel === null ? 'Semua kelas' : $this->gradeLabel($gradeLevel)),
            'hasDraft' => $hasDraft,
            'sessionCount' => $rows->count(),
            'makeupCount' => $rows->where('kind', self::KINDS[SessionKind::Makeup->value])->count(),
            'shareText' => implode("\n", $shareLines),
            'generatedAt' => now()->timezone(self::TIMEZONE)->format('d/m/Y H:i').' WIB',
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function periodLabel(AssessmentPeriod $period): string
    {
        return $period->name.' · '.$period->academicYear->name;
    }

    private function gradeLabel(int $level): string
    {
        return 'Kelas '.match ($level) {
            10 => 'X / 10',
            11 => 'XI / 11',
            12 => 'XII / 12',
            default => (string) $level,
        };
    }

    private function scheduleKey(ExamSession $session): string
    {
        return implode('|', [
            $session->assessmentSubject->subject_id,
            $session->assessmentSubject->schoolClass->grade_level,
            $session->starts_at->getTimestamp(),
            $session->ends_at->getTimestamp(),
            $session->duration_minutes,
            $session->kind->value,
            $session->status->value,
        ]);
    }

    private function row(Collection $sessions): array
    {
        $session = $sessions->first();
        $start = $session->starts_at->copy()->timezone(self::TIMEZONE);
        $end = $session->ends_at->copy()->timezone(self::TIMEZONE);
        $endFormat = $start->isSameDay($end) ? 'H:i' : 'd/m/Y H:i';

        return [
            'date' => $start->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'time' => $start->format('H:i').' – '.$end->format($endFormat),
            'subject' => $session->assessmentSubject->subject->name,
            'grade' => $this->gradeLabel($session->assessmentSubject->schoolClass->grade_level),
            'kind' => self::KINDS[$session->kind->value],
            'status' => self::STATUSES[$session->status->value],
            'draft' => $session->status === SessionStatus::Draft,
            'locations' => $sessions->groupBy('campus_id')->map(fn (Collection $campusSessions) => [
                'campus' => $campusSessions->first()->campus->name,
                'rooms' => $campusSessions->map(fn (ExamSession $item) => filled($item->room_name)
                    ? $item->room_name : 'Ruangan belum ditetapkan')->unique()->sort(SORT_NATURAL)->implode(', '),
            ])->sortBy('campus', SORT_NATURAL)->values(),
            'duration' => $session->duration_minutes,
            'participants' => $sessions->sum('participant_count'),
        ];
    }
}
