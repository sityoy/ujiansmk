<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPeriod;
use App\Models\SchoolProfile;
use App\Models\TrialExam;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExamFlyerController extends Controller
{
    public function core(Request $request): Response
    {
        $validated = $request->validate(['assessment_period_id' => ['required', 'integer', 'exists:assessment_periods,id']]);
        $period = AssessmentPeriod::with('academicYear')->findOrFail($validated['assessment_period_id']);

        return $this->render([
            'kind' => 'UJIAN INTI', 'period' => $period, 'headline' => $period->name,
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
            'kind' => 'UJIAN UJI COBA', 'period' => $period, 'headline' => $trialExam->title,
            'subtitle' => 'Kenali sistemnya, siapkan dirimu.', 'entryUrl' => route('tryout.entry', $trialExam),
            'code' => $trialExam->access_code, 'details' => $details,
        ]);
    }

    private function render(array $data): Response
    {
        return response()->view('flyers.exam', [...$data, 'school' => SchoolProfile::first()])
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
