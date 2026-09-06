<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\ExamQuestionType;
use App\Enums\PeriodStatus;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_catalog_only_lists_assigned_components(): void
    {
        $this->withoutVite();
        [$teacher, $otherTeacher, $assigned, $other] = $this->makeComponents();

        $this->actingAs($teacher)
            ->get(route('question-bank.index'))
            ->assertOk()
            ->assertSee($assigned->subject->name)
            ->assertDontSee($other->subject->name)
            ->assertSee('Total bobot');

        $this->actingAs($otherTeacher)
            ->get(route('question-bank.index'))
            ->assertOk()
            ->assertSee($other->subject->name)
            ->assertDontSee($assigned->subject->name);
    }

    public function test_teacher_can_manage_only_their_assigned_question_bank(): void
    {
        $this->withoutVite();
        [$teacher, , $assigned, $other] = $this->makeComponents();

        $this->actingAs($teacher)
            ->get(route('scheduling.questions.index', $assigned))
            ->assertOk()
            ->assertSee('10 isian × 3 poin + 10 esai × 7 poin = total 100');

        $this->post(route('scheduling.questions.store', $assigned), [
            'question_type' => ExamQuestionType::ShortAnswer->value,
            'question_text' => 'Tuliskan kepanjangan HTTP.',
            'points' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $question = $assigned->questions()->firstOrFail();
        $this->assertSame('3.00', $question->points);

        $this->put(route('scheduling.questions.update', [$assigned, $question]), [
            'question_type' => ExamQuestionType::Essay->value,
            'question_text' => 'Jelaskan fungsi protokol HTTP.',
            'points' => 7,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $question->refresh();
        $this->assertSame(ExamQuestionType::Essay, $question->question_type);
        $this->assertSame('Jelaskan fungsi protokol HTTP.', $question->question_text);
        $this->assertSame('7.00', $question->points);

        $this->get(route('scheduling.questions.index', $other))->assertForbidden();
        $this->post(route('scheduling.questions.store', $other), [
            'question_type' => ExamQuestionType::Essay->value,
            'question_text' => 'Soal yang tidak boleh dibuat.',
            'points' => 7,
        ])->assertForbidden();

        $this->put(route('scheduling.questions.update', [$other, $question]), [
            'question_type' => ExamQuestionType::Essay->value,
            'question_text' => 'Perubahan yang tidak diizinkan.',
            'points' => 7,
        ])->assertForbidden();

        $this->delete(route('scheduling.questions.destroy', [$other, $question]))->assertForbidden();
        $this->assertDatabaseCount('exam_questions', 1);
    }

    public function test_committee_can_oversee_all_question_banks(): void
    {
        $this->withoutVite();
        [, , $assigned, $other] = $this->makeComponents();
        $committee = User::factory()->create(['role' => UserRole::Committee]);

        $this->actingAs($committee)
            ->get(route('question-bank.index'))
            ->assertOk()
            ->assertSee($assigned->subject->name)
            ->assertSee($other->subject->name);
        $this->get(route('scheduling.questions.index', $other))->assertOk();
    }

    /** @return array{User, User, AssessmentSubject, AssessmentSubject} */
    private function makeComponents(): array
    {
        $teacher = User::factory()->create(['role' => UserRole::Teacher, 'is_active' => true]);
        $otherTeacher = User::factory()->create(['role' => UserRole::Teacher, 'is_active' => true]);
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);
        $period = AssessmentPeriod::create([
            'academic_year_id' => $year->id,
            'code' => 'ATS-GANJIL',
            'name' => 'ATS Semester Ganjil',
            'type' => AssessmentType::ATS,
            'semester' => Semester::Odd,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-10',
            'status' => PeriodStatus::Published,
        ]);
        $schoolClass = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'X-1',
            'grade_level' => 10,
        ]);
        $firstSubject = Subject::create(['code' => 'INF', 'name' => 'Informatika', 'is_active' => true]);
        $secondSubject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'is_active' => true]);
        $assigned = AssessmentSubject::create([
            'assessment_period_id' => $period->id,
            'subject_id' => $firstSubject->id,
            'school_class_id' => $schoolClass->id,
            'teacher_user_id' => $teacher->id,
        ])->load('subject');
        $other = AssessmentSubject::create([
            'assessment_period_id' => $period->id,
            'subject_id' => $secondSubject->id,
            'school_class_id' => $schoolClass->id,
            'teacher_user_id' => $otherTeacher->id,
        ])->load('subject');

        return [$teacher, $otherTeacher, $assigned, $other];
    }
}
