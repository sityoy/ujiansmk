<?php

namespace App\Services\Exams;

use App\Models\Subject;
use App\Models\TrialQuestionBank;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrialQuestionPack
{
    public function sets(?int $grade = null): array
    {
        $sets = json_decode(file_get_contents(database_path('data/trial-question-pack.json')), true, 512, JSON_THROW_ON_ERROR);

        return array_values(array_filter($sets, fn ($set) => $grade === null || $set['grade'] === $grade));
    }

    /** Only metadata is exposed to the picker; answers stay on the server. */
    public function menu(?int $grade = null, string $search = ''): Collection
    {
        $sets = $this->sets();
        $existing = TrialQuestionBank::query()->whereNotNull('source_pack_key')->withCount('questions')->get()->keyBy('source_pack_key');
        $items = collect($sets)->map(fn ($set) => [
            'id' => $set['id'], 'name' => $set['name'], 'subject' => $set['subject'],
            'grade' => $set['grade'], 'ready' => true, 'bank' => $existing->get($set['id']),
        ]);
        // Include actual school subjects without inventing questions for unknown subjects.
        $subjects = Subject::query()->where('is_active', true)->with('assessmentSubjects.schoolClass')->orderBy('name')->get();
        foreach ($subjects as $subject) {
            if (collect($sets)->contains(fn ($set) => in_array(mb_strtolower(trim($subject->name)), $this->subjectNames($set), true)
                || mb_strtoupper($subject->code) === $set['code'])) {
                continue;
            }
            $grades = $subject->assessmentSubjects->pluck('schoolClass.grade_level')->filter(fn ($value) => in_array($value, [10, 11, 12], true))->unique()->values();
            if ($grades->isEmpty()) {
                $grades = collect([10, 11, 12]);
            }
            foreach ($grades as $level) {
                $items->push([
                    'id' => null, 'name' => 'Try Out '.$subject->name.' '.$level,
                    'subject' => $subject->name, 'grade' => $level, 'ready' => false, 'bank' => null,
                ]);
            }
        }

        return $items->filter(fn ($item) => ($grade === null || $item['grade'] === $grade)
            && ($search === '' || str_contains(mb_strtolower($item['name']), mb_strtolower($search))))
            ->sortBy([['subject', 'asc'], ['grade', 'asc']])->values();
    }

    private function subjectNames(array $set): array
    {
        return match ($set['code']) {
            'SJR' => ['sejarah', 'sejarah indonesia'],
            'MTK' => ['matematika', 'matematika wajib'],
            'BIN' => ['bahasa indonesia', 'b. indonesia', 'b indonesia'],
            'BIG' => ['bahasa inggris', 'b. inggris', 'b inggris', 'english'],
            default => [mb_strtolower($set['subject'])],
        };
    }

    public function importSelected(string $key): array
    {
        $set = collect($this->sets())->firstWhere('id', $key);
        if (! $set || count($set['questions']) !== 20) {
            throw ValidationException::withMessages(['pack_key' => 'Pilihan paket 20 soal tidak tersedia.']);
        }

        return DB::transaction(function () use ($set): array {
            $bank = TrialQuestionBank::query()->where('source_pack_key', $set['id'])->first();
            if (! $bank) {
                $subject = Subject::query()->whereIn(DB::raw('LOWER(TRIM(name))'), $this->subjectNames($set))->orderBy('id')->first()
                    ?? Subject::query()->where('code', $set['code'])->first()
                    ?? Subject::query()->firstOrCreate(['code' => $set['code']], ['name' => $set['subject'], 'is_active' => true]);
                $bank = TrialQuestionBank::query()->firstOrCreate([
                    'subject_id' => $subject->id, 'grade_level' => $set['grade'], 'name' => $set['name'],
                ], ['teacher_user_id' => null, 'source_pack_key' => $set['id']]);
            }
            $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($bank->id);
            if ($bank->source_pack_key !== $set['id']) {
                throw ValidationException::withMessages(['pack_key' => 'Nama bank ini sudah dipakai bank buatan pengelola. Ubah nama bank tersebut sebelum menambahkan paket.']);
            }
            if ($bank->archived_at || $bank->questions()->exists() || $bank->trialExams()->whereHas('attempts')->exists()) {
                return ['bank' => $bank, 'created' => false];
            }
            foreach ($set['questions'] as $index => $question) {
                $bank->questions()->create([
                    'question_text' => $question['question'], 'text_format' => 'plain',
                    'options' => $question['options'], 'correct_answer' => $question['answer'],
                    'points' => ExamScoreCalculator::MULTIPLE_CHOICE_POINTS, 'position' => $index + 1,
                ]);
            }

            return ['bank' => $bank, 'created' => true];
        });
    }

    /** Terminal bulk imports remain explicit; the web picker always imports one selection. */
    public function import(?int $grade = null): array
    {
        return DB::transaction(function () use ($grade): array {
            $result = ['created' => 0, 'questions' => 0, 'skipped' => 0];
            foreach ($this->sets($grade) as $set) {
                $import = $this->importSelected($set['id']);
                $result[$import['created'] ? 'created' : 'skipped']++;
                if ($import['created']) {
                    $result['questions'] += 20;
                }
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
