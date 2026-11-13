<?php

namespace App\Services\Exams;

use App\Models\Subject;
use App\Models\TrialQuestionBank;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrialQuestionPack
{
    public function sets(?int $grade = null): array
    {
        $sets = json_decode(file_get_contents(database_path('data/trial-question-pack.json')), true, 512, JSON_THROW_ON_ERROR);

        return array_values(array_filter($sets, fn ($set) => $grade === null || $set['grade'] === $grade));
    }

    /** Existing nonempty or archived banks are intentionally never replaced. */
    public function import(?int $grade = null): array
    {
        return DB::transaction(function () use ($grade): array {
            $result = ['created' => 0, 'questions' => 0, 'skipped' => 0];
            foreach ($this->sets($grade) as $set) {
                $names = match ($set['code']) {
                    'SJR' => ['sejarah', 'sejarah indonesia'],
                    'MTK' => ['matematika', 'matematika wajib'],
                    default => [mb_strtolower($set['subject'])],
                };
                $subject = Subject::query()->whereIn(DB::raw('LOWER(TRIM(name))'), $names)->orderBy('id')->first()
                    ?? Subject::query()->where('code', $set['code'])->first()
                    ?? Subject::create(['code' => $set['code'], 'name' => $set['subject'], 'is_active' => true]);
                $bank = TrialQuestionBank::query()->firstOrCreate([
                    'subject_id' => $subject->id,
                    'grade_level' => $set['grade'],
                    'name' => $set['name'],
                ], ['teacher_user_id' => null]);
                $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($bank->id);
                if ($bank->archived_at || $bank->questions()->exists() || $bank->trialExams()->whereHas('attempts')->exists()) {
                    $result['skipped']++;

                    continue;
                }
                foreach ($set['questions'] as $index => $question) {
                    $bank->questions()->create([
                        'question_text' => $question['question'],
                        'text_format' => 'plain',
                        'options' => $question['options'],
                        'correct_answer' => $question['answer'],
                        'points' => ExamScoreCalculator::MULTIPLE_CHOICE_POINTS,
                        'position' => $index + 1,
                    ]);
                }
                $result['created']++;
                $result['questions'] += count($set['questions']);
            }

            return $result;
        });
    }

    public function archiveHistoryGrade12(): array
    {
        $result = ['archived' => 0, 'blocked' => 0];
        $banks = TrialQuestionBank::query()->whereNull('archived_at')->where('grade_level', 12)
            ->whereHas('subject', fn ($query) => $query->whereIn(DB::raw('LOWER(TRIM(name))'), ['sejarah', 'sejarah indonesia']))
            ->get();
        foreach ($banks as $bank) {
            try {
                app(TrialQuestionBankArchive::class)->archive($bank);
                $result['archived']++;
            } catch (ValidationException) {
                $result['blocked']++;
            }
        }

        return $result;
    }
}
