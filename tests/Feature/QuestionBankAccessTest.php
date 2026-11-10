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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_multiple_choice_supports_option_e_and_optional_image(): void
    {
        Storage::fake('public');
        [$teacher, , $assigned] = $this->makeComponents();
        $assigned->assessmentPeriod()->update(['type' => AssessmentType::AAS]);

        $this->actingAs($teacher)
            ->post(route('scheduling.questions.store', $assigned), [
                'question_type' => ExamQuestionType::MultipleChoice->value,
                'statement' => "(1) Contoh pernyataan pertama.\n(2) Contoh pernyataan kedua.",
                'question_text' => 'Pilih jawaban yang benar.',
                'option_a' => 'Pilihan A',
                'option_b' => 'Pilihan B',
                'option_c' => 'Pilihan C',
                'option_d' => 'Pilihan D',
                'option_e' => 'Pilihan E',
                'correct_answer' => 'E',
                'question_image' => UploadedFile::fake()->image('soal-arab.png', 800, 600),
                'points' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $question = $assigned->questions()->firstOrFail();
        $this->assertSame("(1) Contoh pernyataan pertama.\n(2) Contoh pernyataan kedua.", $question->statement);
        $this->assertSame('Pilihan E', $question->options['E']);
        $this->assertSame('E', $question->correct_answer);
        $this->assertNotNull($question->question_image_path);
        Storage::disk('public')->assertExists($question->question_image_path);
        $this->actingAs($teacher)
            ->get(route('scheduling.questions.export', $assigned))
            ->assertOk()->assertDownload();

        $this->actingAs($teacher)
            ->get(route('scheduling.questions.index', $assigned))
            ->assertOk()
            ->assertSee('Soal Bahasa Arab')
            ->assertSeeInOrder([$question->statement, Storage::url($question->question_image_path), $question->question_text, 'Pilihan E']);
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

    public function test_deleting_questions_renumbers_remaining_questions_without_gaps(): void
    {
        [$teacher, , $assigned] = $this->makeComponents();

        foreach (range(1, 4) as $position) {
            $assigned->questions()->create([
                'question_type' => ExamQuestionType::ShortAnswer,
                'question_text' => 'Pertanyaan '.$position,
                'points' => 1,
                'position' => $position,
            ]);
        }

        $questions = $assigned->questions()->get();
        $this->actingAs($teacher)
            ->delete(route('scheduling.questions.destroy', [$assigned, $questions[0]]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Soal berhasil dihapus dan nomor soal telah dirapikan.');

        $this->assertSame(
            [
                ['question_text' => 'Pertanyaan 2', 'position' => 1],
                ['question_text' => 'Pertanyaan 3', 'position' => 2],
                ['question_text' => 'Pertanyaan 4', 'position' => 3],
            ],
            $assigned->questions()->get(['question_text', 'position'])->toArray(),
        );

        $middleQuestion = $assigned->questions()->where('position', 2)->firstOrFail();
        $this->delete(route('scheduling.questions.destroy', [$assigned, $middleQuestion]))
            ->assertRedirect();

        $this->assertSame(
            [
                ['question_text' => 'Pertanyaan 2', 'position' => 1],
                ['question_text' => 'Pertanyaan 4', 'position' => 2],
            ],
            $assigned->questions()->get(['question_text', 'position'])->toArray(),
        );
    }

    public function test_core_question_bank_is_shared_only_with_classes_of_the_same_teacher(): void
    {
        $this->withoutVite();
        [$teacher, $otherTeacher, $assigned] = $this->makeComponents();
        $committee = User::factory()->create(['role' => UserRole::Committee]);
        $base = [
            'assessment_period_id' => $assigned->assessment_period_id,
            'subject_id' => $assigned->subject_id,
        ];
        $otherClass = SchoolClass::create([
            'academic_year_id' => $assigned->schoolClass->academic_year_id,
            'name' => 'X-3',
            'grade_level' => 10,
        ]);
        $other = AssessmentSubject::create([
            ...$base,
            'school_class_id' => $otherClass->id,
            'teacher_user_id' => $otherTeacher->id,
        ]);

        $this->actingAs($otherTeacher)->post(route('scheduling.questions.store', $other), [
            'question_type' => ExamQuestionType::ShortAnswer->value,
            'question_text' => 'Soal khusus X-3.',
            'points' => 3,
        ])->assertSessionHasNoErrors();

        $this->actingAs($teacher)->post(route('scheduling.questions.store', $assigned), [
            'question_type' => ExamQuestionType::ShortAnswer->value,
            'question_text' => 'Soal bersama X-1 dan X-2.',
            'points' => 3,
        ])->assertSessionHasNoErrors();

        $secondClass = SchoolClass::create([
            'academic_year_id' => $assigned->schoolClass->academic_year_id,
            'name' => 'X-2',
            'grade_level' => 10,
        ]);
        $this->actingAs($committee)->post(route('scheduling.components.store'), [
            ...$base,
            'school_class_id' => $secondClass->id,
            'teacher_user_id' => $teacher->id,
        ])->assertSessionHasNoErrors();
        $sibling = AssessmentSubject::query()->where('school_class_id', $secondClass->id)->firstOrFail();
        $this->assertSame('Soal bersama X-1 dan X-2.', $sibling->questions()->firstOrFail()->question_text);

        $this->get(route('question-bank.index'))
            ->assertOk()
            ->assertSee('Rombel X-1, X-2')
            ->assertSee('Rombel X-3');
        $this->patch(route('scheduling.components.teacher', $other), [
            'teacher_user_id' => $teacher->id,
        ])->assertSessionHasErrors('component');
        $this->assertSame($otherTeacher->id, $other->fresh()->teacher_user_id);

        $otherQuestion = $other->questions()->firstOrFail();
        $this->actingAs($teacher)->get(route('question-bank.index'))
            ->assertOk()->assertSee('Rombel X-1, X-2')->assertDontSee('Rombel X-3');
        $this->get(route('scheduling.questions.index', $other))->assertForbidden();
        $this->get(route('scheduling.questions.export', $other))->assertForbidden();
        $this->delete(route('scheduling.questions.destroy', [$other, $otherQuestion]))->assertForbidden();

        $question = $sibling->questions()->firstOrFail();
        $this->put(route('scheduling.questions.update', [$sibling, $question]), [
            'question_type' => ExamQuestionType::ShortAnswer->value,
            'question_text' => 'Soal revisi guru yang sama.',
            'statement' => 'Khusus PG.',
            'points' => 3,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Soal revisi guru yang sama.', $assigned->questions()->firstOrFail()->question_text);
        $this->assertNull($question->fresh()->statement);
        $this->assertSame('Soal khusus X-3.', $otherQuestion->fresh()->question_text);

        $this->delete(route('scheduling.questions.destroy', [$sibling, $question]))->assertSessionHasNoErrors();
        $this->assertFalse($assigned->questions()->exists());
        $this->assertFalse($sibling->questions()->exists());
        $this->assertTrue($other->questions()->exists());
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

