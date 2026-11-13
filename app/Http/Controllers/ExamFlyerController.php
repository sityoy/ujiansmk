<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentType;
use App\Enums\Semester;
use App\Models\AssessmentPeriod;
use App\Models\TrialExam;
use App\Services\Exams\ExamResetAllowance;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExamFlyerController extends Controller
{
    public function core(Request $request): Response
    {
        $validated = $request->validate(['assessment_period_id' => ['required', 'integer', 'exists:assessment_periods,id']]);
        $period = AssessmentPeriod::with('academicYear')->findOrFail($validated['assessment_period_id']);

        return $this->render([
            'kind' => $this->assessmentTitle($period), 'period' => $period, 'headline' => $period->name,
            'subtitle' => 'UJIAN BERBASIS KOMPUTER', 'entryUrl' => route('login'), 'code' => null,
            'details' => [
                ['Pelaksanaan', $period->starts_on->translatedFormat('d F Y').' – '.$period->ends_on->translatedFormat('d F Y')],
                ['Jadwal dan ruangan', 'Sesuai jadwal serta kartu peserta pada periode ini'],
            ],
        ]);
    }

    public function trial(TrialExam $trialExam): Response
    {
        $trialExam->load(['assessmentSubject.assessmentPeriod.academicYear', 'questionBank.subject']);
        $period = $trialExam->assessmentSubject?->assessmentPeriod;
        abort_unless($period, 422, 'Ujian uji coba belum terhubung dengan periode dan tahun ajaran.');
        $details = [
            ['Mata pelajaran', $trialExam->questionBank->subject->name.' · Kelas '.$trialExam->questionBank->grade_level],
            ['Durasi', $trialExam->duration_minutes.' menit'],
        ];
        if ($trialExam->starts_at || $trialExam->ends_at) {
            $details[] = ['Akses uji coba', ($trialExam->starts_at?->format('d/m/Y H:i') ?? 'Sesuai informasi panitia').' – '.($trialExam->ends_at?->format('d/m/Y H:i') ?? 'Sesuai informasi panitia')];
        }

        return $this->render([
            'kind' => 'Simulasi '.$this->assessmentTitle($period), 'period' => $period, 'headline' => $trialExam->title,
            'subtitle' => 'Kenali sistemnya, siapkan dirimu.', 'entryUrl' => route('tryout.entry', $trialExam),
            'code' => $trialExam->access_code, 'details' => $details,
        ]);
    }

    public function warnings(Request $request): Response
    {
        $validated = $request->validate(['assessment_period_id' => ['required', 'integer', 'exists:assessment_periods,id']]);
        $period = AssessmentPeriod::with('academicYear')->findOrFail($validated['assessment_period_id']);

        return $this->render([
            'kind' => 'Peringatan & Tata Tertib Ujian', 'period' => $period,
            'headline' => $this->assessmentTitle($period), 'subtitle' => 'Berlaku untuk asesmen dan simulasi.',
            'warning' => true, 'resetLimit' => ExamResetAllowance::LIMIT,
        ]);
    }

    private function assessmentTitle(AssessmentPeriod $period): string
    {
        $semester = $period->semester === Semester::Odd ? 'Ganjil' : 'Genap';

        return match ($period->type) {
            AssessmentType::AAS => 'Asesmen Akhir Semester '.$semester,
            AssessmentType::ATS => 'Asesmen Tengah Semester '.$semester,
            AssessmentType::AAT => 'Asesmen Akhir Tahun',
            default => $period->name,
        };
    }

    private function render(array $data): Response
    {
        return response()->view('flyers.exam', [...$data, 'schoolName' => 'SMK ISLAM BAHAGIA'])
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
