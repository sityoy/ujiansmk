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
        $this->assertDatabaseCount('trial_question_banks', 3);
        $this->assertDatabaseCount('trial_questions', 30);
        $bank = TrialQuestionBank::query()->firstOrFail();
        $question = $bank->questions()->firstOrFail();
        $question->update(['question_text' => 'Soal hasil revisi guru']);
        $this->assertSame(['2.00'], $bank->questions()->pluck('points')->unique()->values()->all());
        $this->artisan('trial-questions:sample', ['--grade' => 10])->assertSuccessful();
        $this->assertDatabaseCount('trial_question_banks', 3);
        $this->assertDatabaseCount('trial_questions', 30);
        $this->assertSame('Soal hasil revisi guru', $question->fresh()->question_text);
    }

    public function test_full_pack_contains_exact_requested_distribution_and_unique_questions(): void
    {
        $this->artisan('trial-questions:sample', ['--all' => true])->assertSuccessful();
        $this->assertDatabaseCount('trial_question_banks', 9);
        $this->assertDatabaseCount('trial_questions', 100);
        $banks = TrialQuestionBank::query()->with(['subject', 'questions'])->get();
        $this->assertSame([10, 11], $banks->where('subject.name', 'Sejarah')->pluck('grade_level')->sort()->values()->all());
        foreach (['Bahasa Indonesia', 'Bahasa Inggris'] as $subject) {
            $this->assertSame([10, 11, 12], $banks->where('subject.name', $subject)->pluck('grade_level')->sort()->values()->all());
        }
        $math = $banks->firstWhere('subject.name', 'Matematika');
        $this->assertSame(12, $math->grade_level);
        $this->assertCount(20, $math->questions);
        $this->assertSame(range(1, 20), $math->questions->pluck('position')->all());
        foreach ($banks as $bank) {
            $this->assertCount($bank->subject->name === 'Matematika' ? 20 : 10, $bank->questions);
            foreach ($bank->questions as $question) {
                $this->assertSame(['A', 'B', 'C', 'D', 'E'], array_keys($question->options));
                $this->assertCount(5, array_unique($question->options));
                $this->assertArrayHasKey($question->correct_answer, $question->options);
            }
        }
        $this->assertCount(100, $banks->flatMap->questions->pluck('question_text')->unique());
        $math->forceFill(['archived_at' => now()])->save();
        $this->artisan('trial-questions:sample', ['--all' => true])->assertSuccessful();
        $this->assertDatabaseCount('trial_questions', 100);
        $this->assertNotNull($math->fresh()->archived_at);
    }

    public function test_grade_12_has_math_and_languages_but_no_history(): void
    {
        $this->artisan('trial-questions:sample', ['--grade' => 12])->assertSuccessful();
        $this->assertDatabaseCount('trial_question_banks', 3);
        $this->assertDatabaseCount('trial_questions', 40);
        $levels = array_column(app(TrialQuestionPack::class)->sets(12)[0]['questions'], 'difficulty');
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
        $this->assertDatabaseCount('trial_questions', 100);
    }

    public function test_command_rejects_unsupported_grade(): void
    {
        foreach ([9, '10abc'] as $grade) {
            $this->artisan('trial-questions:sample', ['--grade' => $grade])->assertFailed();
        }
        $this->assertDatabaseCount('trial_question_banks', 0);
    }
}
