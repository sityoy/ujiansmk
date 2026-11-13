<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\TrialQuestionBank;
use App\Services\Exams\TrialQuestionPack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialSampleQuestionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_grade_specific_banks_without_replacing_edits(): void
    {
        $this->artisan('trial-questions:sample', ['--grade' => 10])->assertSuccessful();
        $this->assertDatabaseCount('trial_question_banks', 4);
        $this->assertDatabaseCount('trial_questions', 80);
        $bank = TrialQuestionBank::query()->firstOrFail();
        $question = $bank->questions()->firstOrFail();
        $question->update(['question_text' => 'Soal hasil revisi guru']);
        $this->assertSame(['2.00'], $bank->questions()->pluck('points')->unique()->values()->all());
        $this->artisan('trial-questions:sample', ['--grade' => 10])->assertSuccessful();
        $this->assertDatabaseCount('trial_question_banks', 4);
        $this->assertDatabaseCount('trial_questions', 80);
        $this->assertSame('Soal hasil revisi guru', $question->fresh()->question_text);
    }

    public function test_full_pack_contains_exact_requested_distribution_and_unique_questions(): void
    {
        $this->artisan('trial-questions:sample', ['--all' => true])->assertSuccessful();
        $this->assertDatabaseCount('trial_question_banks', 11);
        $this->assertDatabaseCount('trial_questions', 220);
        $banks = TrialQuestionBank::query()->with(['subject', 'questions'])->get();
        $this->assertSame([10, 11], $banks->where('subject.name', 'Sejarah')->pluck('grade_level')->sort()->values()->all());
        foreach (['Bahasa Indonesia', 'Bahasa Inggris'] as $subject) {
            $this->assertSame([10, 11, 12], $banks->where('subject.name', $subject)->pluck('grade_level')->sort()->values()->all());
        }
        $math = $banks->firstWhere('subject.name', 'Matematika');
        $this->assertSame(10, $math->grade_level);
        $this->assertCount(20, $math->questions);
        $this->assertSame(range(1, 20), $math->questions->pluck('position')->all());
        foreach ($banks as $bank) {
            $this->assertCount(20, $bank->questions);
            $this->assertSame('Try Out '.$bank->subject->name.' '.$bank->grade_level, $bank->name);
            foreach ($bank->questions as $question) {
                $this->assertSame(['A', 'B', 'C', 'D', 'E'], array_keys($question->options));
                $this->assertCount(5, array_unique($question->options));
                $this->assertArrayHasKey($question->correct_answer, $question->options);
            }
        }
        $this->assertCount(220, $banks->flatMap->questions->pluck('question_text')->unique());
        $math->forceFill(['archived_at' => now()])->save();
        $this->artisan('trial-questions:sample', ['--all' => true])->assertSuccessful();
        $this->assertDatabaseCount('trial_questions', 220);
        $this->assertNotNull($math->fresh()->archived_at);
    }

    public function test_grade_12_has_math_and_languages_but_no_history(): void
    {
        $this->artisan('trial-questions:sample', ['--grade' => 12])->assertSuccessful();
        $this->assertDatabaseCount('trial_question_banks', 3);
        $this->assertDatabaseCount('trial_questions', 60);
        $levels = array_column(collect(app(TrialQuestionPack::class)->sets(12))->firstWhere('id', 'mtk-12')['questions'], 'difficulty');
        $this->assertSame(array_merge(array_fill(0, 6, 'mudah'), array_fill(0, 8, 'sedang'), array_fill(0, 6, 'sulit')), $levels);
    }

    public function test_optional_history_cleanup_only_archives_history_grade_12_and_keeps_other_data(): void
    {
        $subject = Subject::create(['code' => 'SEJ', 'name' => 'Sejarah Indonesia', 'is_active' => true]);
        $old = TrialQuestionBank::create(['subject_id' => $subject->id, 'name' => 'Sejarah XII lama', 'grade_level' => 12]);
        $keep = TrialQuestionBank::create(['subject_id' => $subject->id, 'name' => 'Sejarah XI guru', 'grade_level' => 11]);
        $this->artisan('trial-questions:sample', ['--all' => true])->assertSuccessful();
        $this->assertNull($old->fresh()->archived_at);
        $this->artisan('trial-questions:sample', ['--all' => true, '--archive-history-12' => true])->assertSuccessful();
        $this->assertNotNull($old->fresh()->archived_at);
        $this->assertNull($keep->fresh()->archived_at);
        $this->assertDatabaseCount('subjects', 4);
        $this->assertDatabaseCount('trial_questions', 220);
    }

    public function test_one_named_selection_is_idempotent_even_after_renaming_the_bank(): void
    {
        $this->artisan('trial-questions:sample', ['--pack' => 'big-11'])->assertSuccessful();
        $this->assertDatabaseCount('trial_question_banks', 1);
        $this->assertDatabaseCount('trial_questions', 20);
        $bank = TrialQuestionBank::query()->sole();
        $this->assertSame('Try Out Bahasa Inggris 11', $bank->name);
        $bank->update(['name' => 'Latihan revisi guru']);
        $question = $bank->questions()->firstOrFail();
        $question->update(['question_text' => 'Revisi guru dipertahankan']);
        $this->artisan('trial-questions:sample', ['--pack' => 'big-11'])->assertSuccessful();
        $this->assertDatabaseCount('trial_question_banks', 1);
        $this->assertDatabaseCount('trial_questions', 20);
        $this->assertSame('Revisi guru dipertahankan', $question->fresh()->question_text);
    }

    public function test_all_catalog_selections_have_twenty_questions_in_increasing_difficulty(): void
    {
        $expected = array_merge(array_fill(0, 6, 'mudah'), array_fill(0, 8, 'sedang'), array_fill(0, 6, 'sulit'));
        foreach (app(TrialQuestionPack::class)->sets() as $set) {
            $this->assertCount(20, $set['questions']);
            $this->assertSame($expected, array_column($set['questions'], 'difficulty'));
            foreach ($set['questions'] as $question) {
                $this->assertNotEmpty($question['explanation']);
            }
        }
    }

    public function test_invalid_selection_and_mixed_bulk_flags_make_no_changes(): void
    {
        $this->artisan('trial-questions:sample', ['--pack' => 'sjr-12'])->assertFailed();
        $this->artisan('trial-questions:sample', ['--pack' => 'bin-10', '--all' => true])->assertFailed();
        $this->assertDatabaseCount('trial_question_banks', 0);
    }

    public function test_command_rejects_unsupported_grade(): void
    {
        foreach ([9, '10abc'] as $grade) {
            $this->artisan('trial-questions:sample', ['--grade' => $grade])->assertFailed();
        }
        $this->assertDatabaseCount('trial_question_banks', 0);
    }
}
