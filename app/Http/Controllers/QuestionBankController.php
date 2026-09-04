<?php

namespace App\Http\Controllers;

use App\Models\AssessmentSubject;
use App\Models\ExamQuestion;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionBankController extends Controller
{
    public function index(AssessmentSubject $assessmentSubject): View
    {
        $assessmentSubject->load([
            'assessmentPeriod.academicYear',
            'subject',
            'schoolClass',
            'questions',
        ]);

        $isLocked = $assessmentSubject->assignments()->whereHas('attempt')->exists();

        return view('questions.index', compact('assessmentSubject', 'isLocked'));
    }

    public function store(Request $request, AssessmentSubject $assessmentSubject): RedirectResponse
    {
        $validated = $request->validate([
            'question_text' => ['required', 'string', 'max:5000'],
            'option_a' => ['required', 'string', 'max:2000'],
            'option_b' => ['required', 'string', 'max:2000'],
            'option_c' => ['required', 'string', 'max:2000'],
            'option_d' => ['required', 'string', 'max:2000'],
            'correct_answer' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'points' => ['required', 'numeric', 'between:0.01,1000'],
        ]);

        DB::transaction(function () use ($assessmentSubject, $validated): void {
            $this->assertEditable($assessmentSubject);
            $position = (int) ExamQuestion::query()
                ->where('assessment_subject_id', $assessmentSubject->id)
                ->lockForUpdate()
                ->max('position') + 1;

            $assessmentSubject->questions()->create([
                'question_text' => trim($validated['question_text']),
                'options' => [
                    'A' => trim($validated['option_a']),
                    'B' => trim($validated['option_b']),
                    'C' => trim($validated['option_c']),
                    'D' => trim($validated['option_d']),
                ],
                'correct_answer' => $validated['correct_answer'],
                'points' => $validated['points'],
                'position' => $position,
            ]);
        });

        return back()->with('status', 'Soal berhasil ditambahkan.');
    }

    public function destroy(AssessmentSubject $assessmentSubject, ExamQuestion $question): RedirectResponse
    {
        if ($question->assessment_subject_id !== $assessmentSubject->id) {
            abort(404);
        }

        if ($question->answers()->exists()) {
            throw ValidationException::withMessages([
                'question' => 'Soal tidak dapat dihapus karena sudah memiliki jawaban siswa.',
            ]);
        }

        try {
            DB::transaction(function () use ($assessmentSubject, $question): void {
                $this->assertEditable($assessmentSubject);
                $question->delete();
            });
        } catch (QueryException) {
            return back()->withErrors(['question' => 'Soal tidak dapat dihapus karena sudah digunakan.']);
        }

        return back()->with('status', 'Soal berhasil dihapus.');
    }

    private function assertEditable(AssessmentSubject $component): void
    {
        $component = AssessmentSubject::query()->lockForUpdate()->findOrFail($component->id);
        if ($component->assignments()->whereHas('attempt')->exists()) {
            throw ValidationException::withMessages([
                'question' => 'Bank soal terkunci karena sudah ada siswa yang mulai ujian, termasuk untuk sesi susulan.',
            ]);
        }
    }
}
