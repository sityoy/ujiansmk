<?php

namespace Tests\Feature;

use App\Models\TrialQuestionBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialSampleQuestionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_three_idempotent_trial_banks_with_ten_questions_each(): void
    {
        $this->artisan('trial-questions:sample', ['--grade' => 10])->assertSuccessful();

        $this->assertDatabaseCount('trial_question_banks', 3);
        $this->assertDatabaseCount('trial_questions', 30);
        $this->assertSame(
            [10, 10, 10],
            TrialQuestionBank::query()->withCount('questions')->orderBy('name')
                ->pluck('questions_count')->map(fn ($count) => (int) $count)->all(),
        );
        $this->assertSame(
            ['7.00'],
            TrialQuestionBank::query()->firstOrFail()->questions()->pluck('points')->unique()->values()->all(),
        );

        $this->artisan('trial-questions:sample', ['--grade' => 10])->assertSuccessful();
        $this->assertDatabaseCount('trial_question_banks', 3);
        $this->assertDatabaseCount('trial_questions', 30);
    }

    public function test_command_rejects_unsupported_grade(): void
    {
        $this->artisan('trial-questions:sample', ['--grade' => 9])->assertFailed();
        $this->assertDatabaseCount('trial_question_banks', 0);
    }
}
