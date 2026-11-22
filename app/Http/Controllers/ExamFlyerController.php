<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentType;
use App\Enums\Semester;
use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Models\AssessmentPeriod;
use App\Models\ExamSession;
use App\Models\TrialExam;
use App\Services\Exams\ExamResetAllowance;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ExamFlyerController extends Controller
{
    public function core(Request $request): Response
    {
        $validated = $request->validate(['assessment_period_id' => ['required', 'integer', 'exists:assessment_periods,id']]);
        $period = AssessmentPeriod::with('academicYear')->findOrFail($validated['assessment_period_id']);
        $sessions = ExamSession::query()
            ->whereHas('assessmentSubject', fn ($query) => $query->where('assessment_period_id', $period->id))
            ->where('status', '!=', SessionStatus::Draft)
            ->with(['assessmentSubject.subject', 'assessmentSubject.schoolClass', 'campus'])
            ->orderBy('starts_at')->orderBy('id')->get();

        return $this->render([
            'kind' => $this->assessmentTitle($period), 'period' => $period, 'headline' => $period->name,
            'subtitle' => 'UJIAN BERBASIS KOMPUTER', 'entryUrl' => route('login'), 'code' => null,
            'details' => [
                ['Pelaksanaan', $period->starts_on->translatedFormat('d F Y').' – '.$period->ends_on->translatedFormat('d F Y')],
                ['Jadwal dan ruangan', $sessions->isEmpty() ? 'Jadwal mapel belum diterbitkan.' : 'Lihat lampiran jadwal. Ikuti rombel dan ruangan pada kartu peserta.'],
            ],
            'schedule' => $sessions->map(fn (ExamSession $session) => [
                $session->assessmentSubject->subject->name.' · '.$session->assessmentSubject->schoolClass->name,
                $this->sessionKind($session), $this->window($session->starts_at, $session->ends_at),
                $this->sessionLocation($session),
            ]),
        ]);
    }

    public function coreSession(ExamSession $examSession): Response
    {
        $examSession->load(['assessmentSubject.assessmentPeriod.academicYear', 'assessmentSubject.subject', 'assessmentSubject.schoolClass', 'campus']);
        $component = $examSession->assessmentSubject;
        $period = $component->assessmentPeriod;

        return $this->render([
            'kind' => $this->assessmentTitle($period), 'period' => $period,
            'headline' => $component->subject->name.' · '.$component->schoolClass->name,
            'subtitle' => 'UJIAN INTI · '.$this->sessionKind($examSession),
            'entryUrl' => route('login'), 'code' => null,
            'details' => [
                ['Jadwal sesi', $this->window($examSession->starts_at, $examSession->ends_at)],
                ['Durasi', $examSession->duration_minutes.' menit'],
                ['Lokasi dan ruangan', $this->sessionLocation($examSession)],
            ],
            'notice' => $examSession->status === SessionStatus::Draft ? 'Draf jadwal. Tunggu jadwal resmi dari panitia.' : null,
        ]);
    }

    public function trial(TrialExam $trialExam): Response
    {
        $trialExam->load(['assessmentSubject.assessmentPeriod.academicYear', 'questionBank.subject', 'location']);

        return $this->render($this->trialFlyer($trialExam));
    }

    public function trials(): Response
    {
        $exams = TrialExam::query()
            ->whereHas('questionBank', fn ($banks) => $banks->whereNull('archived_at'))
            ->with(['assessmentSubject.assessmentPeriod.academicYear', 'questionBank.subject', 'location'])
            ->get()
            ->sortBy([['questionBank.grade_level', 'asc'], ['questionBank.subject.name', 'asc'], ['title', 'asc'], ['id', 'asc']]);

        return $this->renderPages(
            $exams->map(fn (TrialExam $exam): array => $this->trialFlyer($exam)),
            'Flyer Semua Simulasi Ujian',
            'Flyer Semua Simulasi Ujian · '.$exams->count().' ujian',
        );
    }

    private function trialFlyer(TrialExam $trialExam): array
    {
        $period = $trialExam->assessmentSubject?->assessmentPeriod;
        abort_unless($period, 422, 'Ujian uji coba belum terhubung dengan periode dan tahun ajaran.');
        $details = [
            ['Mata pelajaran', $trialExam->questionBank->subject->name.' · Kelas '.$trialExam->questionBank->grade_level],
            ['Jadwal akses', $this->window($trialExam->starts_at, $trialExam->ends_at)],
            ['Durasi', $trialExam->duration_minutes.' menit'],
        ];
        if ($trialExam->location) {
            $details[] = ['Lokasi absensi GPS', $trialExam->location->name];
        }

        return [
            'kind' => 'Simulasi '.$this->assessmentTitle($period), 'period' => $period, 'headline' => $trialExam->title,
            'subtitle' => 'UJIAN UJI COBA', 'entryUrl' => route('tryout.entry', $trialExam),
            'code' => $trialExam->access_code, 'details' => $details,
            'trial' => true, 'locationRequired' => $trialExam->location !== null,
            'notice' => $trialExam->is_active ? null : 'Simulasi nonaktif. Tunggu panitia membuka akses.',
        ];
    }

    private function assessmentTitle(AssessmentPeriod $period): string
    {
        $semester = $period->semester === Semester::Odd ? 'Ganjil' : 'Genap';

        return match ($period->type) {
            AssessmentType::ASAS => 'Asesmen Sumatif Akhir Semester '.$semester,
            AssessmentType::ATS => 'Asesmen Tengah Semester '.$semester,
            AssessmentType::AAT => 'Asesmen Akhir Tahun',
            default => $period->name,
        };
    }

    private function render(array $data): Response
    {
        $year = $data['period']->academicYear->name;
        $label = ($data['trial'] ?? false) ? 'Flyer Simulasi Ujian' : 'Flyer Asesmen';

        return $this->renderPages(collect([$data]), 'Flyer '.$data['kind'].' · '.$year, $label.' · '.$year);
    }

    private function renderPages(Collection $flyers, string $printTitle, string $toolbarLabel): Response
    {
        return response()->view('flyers.exam', [
            'trial' => false, 'locationRequired' => true,
            'notice' => null, 'schedule' => collect(),
            'flyers' => $flyers, 'printTitle' => $printTitle, 'toolbarLabel' => $toolbarLabel,
            'resetLimit' => ExamResetAllowance::LIMIT, 'schoolName' => 'SMK ISLAM BAHAGIA',
        ])
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function window(?Carbon $start, ?Carbon $end): string
    {
        $start = $start?->copy()->timezone('Asia/Jakarta');
        $end = $end?->copy()->timezone('Asia/Jakarta');

        if ($start && $end) {
            $endFormat = $end->isSameDay($start) ? 'H:i' : 'd/m/Y H:i';

            return $start->format('d/m/Y H:i').' – '.$end->format($endFormat).' WIB';
        }
        if ($start) {
            return 'Mulai '.$start->format('d/m/Y H:i').' WIB';
        }
        if ($end) {
            return 'Sampai '.$end->format('d/m/Y H:i').' WIB';
        }

        return 'Tanggal dan jam belum ditetapkan.';
    }

    private function sessionKind(ExamSession $session): string
    {
        return $session->kind === SessionKind::Makeup ? 'Susulan' : 'Reguler';
    }

    private function sessionLocation(ExamSession $session): string
    {
        return $session->campus->name.($session->room_name ? ' · '.$session->room_name : '');
    }
}
