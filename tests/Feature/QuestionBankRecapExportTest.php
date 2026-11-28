<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuestionBankRecapExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_recap_export_contains_all_filtered_banks_not_only_the_visible_page(): void
    {
        [$admin, , $period, $bank] = $this->fixture();
        SchoolProfile::create(['name' => 'SMK ISLAM BAHAGIA', 'address' => 'Alamat Sekolah']);
        for ($i = 1; $i <= 14; $i++) {
            $subject = Subject::create(['name' => 'Mapel Tambahan '.$i, 'code' => 'TAM'.$i, 'is_active' => true]);
            $copy = $bank->replicate();
            $copy->subject_id = $subject->id;
            $copy->save();
        }
        $this->actingAs($admin)->get(route('question-bank.recap', ['period_id' => $period->id, 'per_page' => 10, 'page' => 2]))->assertOk()
            ->assertSee('SMK ISLAM BAHAGIA')->assertSee('Unduh PDF')->assertSee('Unduh gambar PNG')
            ->assertViewHas('totals', ['banks' => 15, 'filled' => 1, 'empty' => 14, 'questions' => 3])
            ->assertViewHas('groups', fn ($groups): bool => count($groups[0]['rows']) === 15)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('question-bank.recap', ['period_id' => $period->id, 'question_status' => 'filled']))->assertOk()
            ->assertViewHas('totals', ['banks' => 1, 'filled' => 1, 'empty' => 0, 'questions' => 3]);
        $this->get(route('question-bank.recap', ['q' => 'Tambahan 14']))->assertOk()
            ->assertViewHas('totals', ['banks' => 1, 'filled' => 0, 'empty' => 1, 'questions' => 0]);
    }

    public function test_shared_banks_count_once_and_export_supports_one_or_all_grades(): void
    {
        [$admin, , $period, $bank] = $this->fixture();
        $newClass = SchoolClass::create(['academic_year_id' => $period->academic_year_id, 'name' => 'X-2', 'grade_level' => 10]);
        $sibling = $bank->replicate();
        $sibling->school_class_id = $newClass->id;
        $sibling->save();
        foreach ($bank->questions as $question) {
            $sibling->questions()->save($question->replicate());
        }
        $class11 = SchoolClass::create(['academic_year_id' => $period->academic_year_id, 'name' => 'XI-1', 'grade_level' => 11]);
        $other = $bank->replicate();
        $other->school_class_id = $class11->id;
        $other->save();
        $this->actingAs($admin)->get(route('question-bank.recap', ['grade_level' => 10]))->assertOk()
            ->assertViewHas('gradeLabel', 'Kelas 10')
            ->assertViewHas('totals', ['banks' => 1, 'filled' => 1, 'empty' => 0, 'questions' => 3])
            ->assertViewHas('groups', fn ($groups): bool => $groups->count() === 1 && $groups[0]['rows'][0][2] === 'X-1, X-2' && array_slice($groups[0]['rows'][0], 3, 4) === [1, 1, 1, 3]);
        $this->get(route('question-bank.recap'))->assertOk()->assertViewHas('gradeLabel', 'Semua tingkat')
            ->assertViewHas('groups', fn ($groups): bool => $groups->count() === 2)
            ->assertViewHas('totals', ['banks' => 2, 'filled' => 1, 'empty' => 1, 'questions' => 3]);
        $this->get(route('question-bank.recap', ['grade_level' => 12]))->assertOk()->assertSee('Tidak ada bank soal sesuai filter.')
            ->assertViewHas('totals', ['banks' => 0, 'filled' => 0, 'empty' => 0, 'questions' => 0]);
    }

    public function test_teacher_export_preserves_permissions_and_never_includes_question_content(): void
    {
        [, $teacher, $period, $bank] = $this->fixture();
        $otherTeacher = User::factory()->create(['name' => 'Guru Lain', 'role' => UserRole::Teacher]);
        $subject = Subject::create(['name' => 'Mapel Rahasia Guru Lain', 'code' => 'PRIVATE', 'is_active' => true]);
        $copy = $bank->replicate();
        $copy->teacher_user_id = $otherTeacher->id;
        $copy->subject_id = $subject->id;
        $copy->save();
        $this->actingAs($teacher)->get(route('question-bank.recap'))->assertOk()
            ->assertSee($teacher->name)->assertDontSee('Mapel Rahasia Guru Lain')->assertDontSee('Isi soal rahasia')
            ->assertViewHas('totals', ['banks' => 1, 'filled' => 1, 'empty' => 0, 'questions' => 3]);
        $periodCopy = $period->replicate();
        $periodCopy->code = 'OTHER';
        $periodCopy->name = 'Periode Guru Lain';
        $periodCopy->save();
        $this->get(route('question-bank.recap', ['period_id' => $periodCopy->id]))->assertForbidden();
        $student = User::factory()->create(['role' => UserRole::Student]);
        $this->actingAs($student)->get(route('question-bank.recap'))->assertForbidden();
        $this->getJson(route('question-bank.recap', ['grade_level' => 9]))->assertForbidden();
    }

    public function test_recap_includes_each_period_deadline_in_wib_even_when_the_selected_period_has_no_results(): void
    {
        [$admin, , $period, $bank] = $this->fixture();
        $otherPeriod = $period->replicate(['core_question_deadline_at']);
        $otherPeriod->forceFill(['code' => 'UUB', 'name' => 'UUB Ganjil'])->save();
        $otherBank = $bank->replicate();
        $otherBank->assessment_period_id = $otherPeriod->id;
        $otherBank->save();
        $period->core_question_deadline_at = Carbon::parse('2026-11-28 17:30', 'Asia/Jakarta')->setTimezone(config('app.timezone'));
        $period->save();

        $this->actingAs($admin)->get(route('question-bank.recap'))->assertOk()
            ->assertSeeInOrder(['ASAS Ganjil', 'Batas pembuatan soal:', '28/11/2026 17:30 WIB', 'UUB Ganjil', 'Belum ditetapkan'])
            ->assertViewHas('groups', fn ($groups): bool => $groups->pluck('deadline', 'title')->all() === [
                'Kelas 10 · ASAS Ganjil' => '28/11/2026 17:30 WIB',
                'Kelas 10 · UUB Ganjil' => 'Belum ditetapkan',
            ]);

        $this->get(route('question-bank.recap', ['period_id' => $period->id, 'grade_level' => 12]))->assertOk()
            ->assertSeeInOrder(['Tidak ada bank soal sesuai filter.', 'Batas pembuatan soal:', '28/11/2026 17:30 WIB'])
            ->assertViewHas('emptyDeadline', '28/11/2026 17:30 WIB');
    }

    public function test_export_validates_filters_and_escapes_untrusted_names(): void
    {
        [$admin, , , $bank] = $this->fixture();
        $bank->subject->update(['name' => '</script><script>alert(1)</script>']);
        $this->actingAs($admin)->get(route('question-bank.recap'))->assertOk()
            ->assertSee('&lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('</script><script>alert(1)</script>', false);
        $this->getJson(route('question-bank.recap', ['grade_level' => 9]))->assertUnprocessable()->assertJsonValidationErrors('grade_level');
        $this->getJson(route('question-bank.recap', ['period_id' => 99999]))->assertUnprocessable()->assertJsonValidationErrors('period_id');
        $this->getJson(route('question-bank.recap', ['question_status' => 'wrong']))->assertUnprocessable()->assertJsonValidationErrors('question_status');
    }

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $teacher = User::factory()->create(['role' => UserRole::Teacher, 'name' => 'Guru Bahasa Indonesia']);
        $year = AcademicYear::create(['name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $period = AssessmentPeriod::create(['academic_year_id' => $year->id, 'name' => 'ASAS Ganjil', 'code' => 'ASAS', 'type' => 'aas', 'semester' => 'odd', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-10', 'status' => 'draft']);
        $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'X-1', 'grade_level' => 10]);
        $subject = Subject::create(['name' => 'Bahasa Indonesia', 'code' => 'BIN', 'is_active' => true]);
        $bank = AssessmentSubject::create(['assessment_period_id' => $period->id, 'subject_id' => $subject->id, 'school_class_id' => $class->id, 'teacher_user_id' => $teacher->id]);
        foreach (['multiple_choice', 'essay', 'short_answer'] as $position => $type) {
            $bank->questions()->create(['question_text' => 'Isi soal rahasia', 'question_type' => $type, 'correct_answer' => 'A', 'points' => 2, 'position' => $position + 1]);
        }

        return [$admin, $teacher, $period, $bank];
    }
}
