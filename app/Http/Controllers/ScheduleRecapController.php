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
use Illuminate\Validation\Rule;

class ScheduleRecapController extends Controller
{
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
            'session_search' => ['nullable', 'string', 'max:255'],
            'session_kind' => ['nullable', Rule::enum(SessionKind::class)],
            'session_status' => ['nullable', Rule::in(['ready', ...array_keys(self::STATUSES)])],
        ]);
        $periodId = $filters['session_period_id'] ?? null;
        $classId = $filters['session_class_id'] ?? null;
        $search = trim($filters['session_search'] ?? '');
        $kind = $filters['session_kind'] ?? null;
        $status = $filters['session_status'] ?? null;

        $sessions = ExamSession::query()
            ->whereHas('assessmentSubject', function (Builder $query) use ($periodId, $classId, $search): void {
                $query->when($periodId, fn (Builder $subjects) => $subjects->where('assessment_period_id', $periodId))
                    ->when($classId, fn (Builder $subjects) => $subjects->where('school_class_id', $classId));

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
                ->where('status', '!=', AssignmentStatus::Cancelled)])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $periods = AssessmentPeriod::with('academicYear')->orderByDesc('starts_on')->get();
        $classes = SchoolClass::with('academicYear')->orderBy('name')->get();
        $period = $periods->find($periodId);
        $schoolClass = $classes->find($classId);
        $school = SchoolProfile::query()->first();
        $schoolName = $school?->name ?? config('app.name');
        $statuses = ['ready' => 'Terbit atau aktif', ...self::STATUSES];
        $filterLabel = collect([
            $kind ? self::KINDS[$kind] : null,
            $status ? $statuses[$status] : null,
            $search !== '' ? 'Pencarian “'.$search.'”' : null,
        ])->filter()->implode(' · ');
        $hasDraft = $sessions->contains('status', SessionStatus::Draft);
        $groups = $sessions->groupBy('assessmentSubject.assessment_period_id')
            ->map(fn ($group) => [
                'period' => $this->periodLabel($group->first()->assessmentSubject->assessmentPeriod),
                'rows' => $group->map(fn (ExamSession $session) => $this->row($session)),
            ]);

        $shareLines = ['*Jadwal Ujian*', $schoolName];
        if ($hasDraft) {
            $shareLines[] = 'Rekap memuat draf. Jadwal draf masih dapat berubah dan menunggu penerbitan panitia.';
        }
        foreach ($groups as $group) {
            $shareLines[] = "\n*".$group['period'].'*';
            foreach ($group['rows']->groupBy('date') as $date => $rows) {
                $shareLines[] = "*{$date}*";
                foreach ($rows as $row) {
                    $shareLines[] = "• {$row['time']} WIB · {$row['subject']} · {$row['class']} · {$row['kind']} ({$row['status']})\n  {$row['campus']} · {$row['room']} · {$row['duration']} menit";
                }
            }
        }

        return response()->view('scheduling.recap', [
            'school' => $school,
            'periods' => $periods,
            'classes' => $classes,
            'kinds' => self::KINDS,
            'statuses' => $statuses,
            'filters' => $filters,
            'filterLabel' => $filterLabel,
            'backUrl' => route('scheduling.index', Arr::only($filters, ['session_period_id', 'session_class_id', 'session_search'])).'#sesi-ujian',
            'groups' => $groups,
            'scope' => ($period ? $this->periodLabel($period) : 'Semua periode').' · '.($schoolClass ? $schoolClass->name.' · '.$schoolClass->academicYear->name : 'Semua rombel'),
            'hasDraft' => $hasDraft,
            'sessionCount' => $sessions->count(),
            'makeupCount' => $sessions->where('kind', SessionKind::Makeup)->count(),
            'shareText' => implode("\n", $shareLines),
            'generatedAt' => now()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function periodLabel(AssessmentPeriod $period): string
    {
        return $period->name.' · '.$period->academicYear->name;
    }

    private function row(ExamSession $session): array
    {
        $start = $session->starts_at->copy()->timezone('Asia/Jakarta');
        $end = $session->ends_at->copy()->timezone('Asia/Jakarta');
        $endFormat = $start->isSameDay($end) ? 'H:i' : 'd/m/Y H:i';

        return [
            'date' => $start->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'time' => $start->format('H:i').' – '.$end->format($endFormat),
            'subject' => $session->assessmentSubject->subject->name,
            'class' => $session->assessmentSubject->schoolClass->name,
            'kind' => self::KINDS[$session->kind->value],
            'status' => self::STATUSES[$session->status->value],
            'draft' => $session->status === SessionStatus::Draft,
            'campus' => $session->campus->name,
            'room' => filled($session->room_name) ? $session->room_name : 'Ruangan belum ditetapkan',
            'duration' => $session->duration_minutes,
            'participants' => $session->participant_count,
        ];
    }
}
