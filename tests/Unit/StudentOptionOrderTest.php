<?php

namespace Tests\Unit;

use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\TrialAttempt;
use App\Services\Exams\StudentOptionOrder;
use Tests\TestCase;

class StudentOptionOrderTest extends TestCase
{
    public function test_permutations_are_stable_vary_by_attempt_and_preserve_the_original_keys(): void
    {
        $service = app(StudentOptionOrder::class);
        $question = new ExamQuestion(['options' => ['A' => 'Teks A', 'B' => 'Teks B', 'C' => 'Teks C', 'D' => 'Teks D', 'E' => 'Teks E']]);
        $question->id = 15;
        $orders = [];
        foreach (range(1, 20) as $id) {
            $attempt = new ExamAttempt(['started_at' => '2026-11-16 08:00:00']);
            $attempt->id = $id;
            $ordered = $service->forQuestion($attempt, $question);
            $this->assertSame($ordered->all(), $service->forQuestion(clone $attempt, $question)->all());
            $this->assertSame($question->options, $ordered->sortKeys()->all());
            $orders[] = $ordered->keys()->join('');
        }
        $this->assertGreaterThan(1, count(array_unique($orders)));
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], array_keys($question->options));
        $trial = new TrialAttempt(['started_at' => '2026-11-16 08:00:00']);
        $trial->id = 20;
        $this->assertSame($question->options, $service->forQuestion($trial, $question)->sortKeys()->all());
    }
}
