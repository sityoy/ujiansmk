<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Campus;
use App\Models\DailyCheckin;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TrialAttempt;
use App\Models\TrialExam;
use App\Models\TrialQuestionBank;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_bulk_delete_removes_only_selected_students_and_accounts_and_keeps_internal_ids(): void
    {
        [$admin, $students] = $this->students();
        $selected = [$students[0]->id, $students[2]->id];
        $remaining = $students[1];
        $query = ['student_per_page' => 'all', 'student_search' => 'Siswa', 'student_page' => 8, 'class_page' => 2];
        $this->actingAs($admin)->delete(route('academic.students.bulk-destroy', $query), ['student_ids' => $selected])
            ->assertRedirect(route('academic.index', ['student_per_page' => 'all', 'student_search' => 'Siswa', 'class_page' => 2]).'#student-list')
            ->assertSessionHasNoErrors();
        foreach ([$students[0], $students[2]] as $student) {
            $this->assertDatabaseMissing('students', ['id' => $student->id]);
            $this->assertDatabaseMissing('users', ['id' => $student->user_id]);
        }
        $this->assertDatabaseHas('students', ['id' => $remaining->id, 'student_number' => $remaining->student_number]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $response = $this->get(route('academic.index', ['student_per_page' => 'all']))->assertOk();
        $this->assertSame(['1'], $this->rowNumbers($response->getContent()));
    }

    public function test_deletion_cleans_related_history_and_selfies_without_touching_question_banks(): void
    {
        [$admin, $students] = $this->students();
        [$attempt, $component, $checkin] = $this->exam($students[0]);
        $question = $component->questions()->create(['question_text' => 'Bank tetap tersimpan', 'question_type' => 'multiple_choice', 'options' => ['A' => 'Benar'], 'correct_answer' => 'A', 'points' => 2, 'position' => 1]);
        $attempt->answers()->create(['exam_question_id' => $question->id, 'answer' => 'A', 'is_correct' => true, 'answered_at' => now()]);
        $attempt->securityIncidents()->create(['category' => 'blur', 'severity' => 1, 'occurred_at' => now()]);
        $students[0]->examRooms()->create(['assessment_period_id' => $component->assessment_period_id, 'room_name' => 'Ruang 01']);
        $trial = $this->trial($students[0], $component);
        $trialQuestion = $trial->trialExam->questionBank->questions()->create(['question_text' => 'Uji coba tetap tersimpan', 'options' => ['A' => 'Benar'], 'correct_answer' => 'A', 'points' => 2, 'position' => 1]);
        $trial->answers()->create(['trial_question_id' => $trialQuestion->id, 'answer' => 'A']);
        $this->actingAs($admin)->delete(route('academic.students.bulk-destroy'), ['student_ids' => [$students[0]->id]])->assertSessionHasNoErrors();
        foreach (['exam_attempts', 'exam_answers', 'security_incidents', 'student_exam_rooms', 'daily_checkins', 'trial_attempts', 'trial_responses'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseHas('exam_questions', ['id' => $question->id]);
        $this->assertDatabaseHas('trial_questions', ['id' => $trialQuestion->id]);
        Storage::disk('local')->assertMissing($checkin->selfie_path);
        $this->assertDatabaseHas('students', ['id' => $students[1]->id]);
    }

    public function test_active_core_or_trial_exam_rejects_the_entire_selection(): void
    {
        [$admin, $students] = $this->students();
        [$attempt, $component] = $this->exam($students[1], 'in_progress');
        $route = route('academic.students.bulk-destroy');
        $this->actingAs($admin)->delete($route, ['student_ids' => $students->modelKeys()])->assertSessionHasErrors('student_ids');
        $this->assertDatabaseCount('students', 3);
        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id]);
        $attempt->update(['status' => 'submitted']);
        $trial = $this->trial($students[1], $component, 'in_progress');
        $this->delete($route, ['student_ids' => $students->modelKeys()])->assertSessionHasErrors('student_ids');
        $this->assertDatabaseCount('students', 3);
        $this->assertDatabaseHas('trial_attempts', ['id' => $trial->id]);
    }

    public function test_single_delete_uses_the_same_guard_and_preserves_shared_selfie_files(): void
    {
        [$admin, $students] = $this->students();
        [$attempt, , $checkin] = $this->exam($students[0], 'in_progress');
        $this->actingAs($admin)->delete(route('academic.students.destroy', $students[0]))->assertSessionHasErrors('student_ids');
        $attempt->update(['status' => 'submitted']);
        $other = $checkin->replicate();
        $other->student_id = $students[1]->id;
        $other->save();
        $this->delete(route('academic.students.destroy', $students[0]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertExists($checkin->selfie_path);
        $this->assertDatabaseHas('daily_checkins', ['id' => $other->id]);
    }

    public function test_duplicate_stale_empty_and_oversized_selections_do_not_delete_anything(): void
    {
        [$admin, $students] = $this->students();
        $this->actingAs($admin);
        foreach ([[], [$students[0]->id, $students[0]->id], [$students[0]->id, 999999], array_fill(0, 501, $students[0]->id)] as $ids) {
            $this->deleteJson(route('academic.students.bulk-destroy'), ['student_ids' => $ids])->assertUnprocessable();
            $this->assertDatabaseCount('students', 3);
        }
        $students[0]->update(['user_id' => $admin->id]);
        $this->delete(route('academic.students.bulk-destroy'), ['student_ids' => $students->modelKeys()])->assertSessionHasErrors('student_ids');
        $this->assertDatabaseCount('students', 3);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_only_super_admin_can_delete_selected_students(): void
    {
        [, $students] = $this->students();
        foreach ([UserRole::Teacher, UserRole::Committee, UserRole::Principal, UserRole::Student] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->delete(route('academic.students.bulk-destroy'), ['student_ids' => $students->modelKeys()])->assertForbidden();
        }
        $this->assertDatabaseCount('students', 3);
    }

    public function test_selection_works_without_a_room_period_and_display_numbers_follow_pages_and_filters(): void
    {
        [$admin, $students] = $this->students(12);
        $response = $this->actingAs($admin)->get(route('academic.index', ['student_page' => 2]))->assertOk()
            ->assertSee('id="student-bulk-delete"', false)->assertDontSee('id="student-room-bulk"', false);
        $this->assertSame(['11', '12'], $this->rowNumbers($response->getContent()));
        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//input[@data-room-student][@disabled]')->length);
        $filtered = $this->get(route('academic.index', ['student_search' => $students[11]->full_name]))->assertOk();
        $this->assertSame(['1'], $this->rowNumbers($filtered->getContent()));
    }

    public function test_unambiguous_legacy_trial_is_deleted_but_ambiguous_identity_is_preserved(): void
    {
        [$admin, $students] = $this->students();
        [, $component] = $this->exam($students[0]);
        $legacy = $this->trial($students[0], $component);
        $legacy->update(['student_id' => null]);
        $ambiguous = $this->trial($students[1], $component);
        $ambiguous->update(['student_id' => null, 'participant_identifier' => $students[0]->student_number, 'participant_name' => 'Nama siswa lain']);
        $this->actingAs($admin)->delete(route('academic.students.bulk-destroy'), ['student_ids' => [$students[0]->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('trial_attempts', ['id' => $legacy->id]);
        $this->assertDatabaseHas('trial_attempts', ['id' => $ambiguous->id]);
    }

    public function test_database_failure_rolls_back_trials_students_accounts_and_file_cleanup(): void
    {
        [$admin, $students] = $this->students();
        [, $component, $checkin] = $this->exam($students[0]);
        $trial = $this->trial($students[0], $component);
        Schema::create('student_delete_blockers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
        });
        DB::table('student_delete_blockers')->insert(['student_id' => $students[0]->id]);
        $this->actingAs($admin)->delete(route('academic.students.bulk-destroy'), ['student_ids' => $students->modelKeys()])->assertSessionHasErrors('student_ids');
        $this->assertDatabaseCount('students', 3);
        $this->assertDatabaseHas('trial_attempts', ['id' => $trial->id]);
        foreach ($students as $student) {
            $this->assertDatabaseHas('users', ['id' => $student->user_id]);
        }
        Storage::disk('local')->assertExists($checkin->selfie_path);
        Schema::drop('student_delete_blockers');
    }

    public function test_delete_all_ignores_list_filters_resets_sqlite_ids_and_rejects_stale_actions(): void
    {
        [$admin, $students] = $this->students();
        $class = $students[0]->schoolClass;
        $otherClass = SchoolClass::create([
            'academic_year_id' => $class->academic_year_id, 'name' => 'XI-1', 'grade_level' => 11,
        ]);
        $students[2]->update(['school_class_id' => $otherClass->id]);

        $this->actingAs($admin)
            ->get(route('academic.index', ['student_class' => $class->id]))
            ->assertOk()
            ->assertSee('Hapus semua siswa (3)')
            ->assertViewHas('students', fn ($filtered) => $filtered->total() === 2);

        $this->delete(route('academic.students.destroy-all', ['student_class' => $class->id]), [
            'confirmation' => 'HAPUS SEMUA',
            'expected_count' => 3,
            'expected_last_id' => $students[2]->id,
            'roster_revision' => 0,
        ])->assertSessionHas('status', '3 siswa beserta akun dan riwayat terkait berhasil dihapus. ID siswa baru akan dimulai dari 1.');

        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('school_classes', ['id' => $otherClass->id]);

        $new = Student::create([
            'school_class_id' => $class->id, 'student_number' => 'SISWA-BARU', 'full_name' => 'Siswa Baru',
        ]);
        $this->assertSame(1, $new->id);
        $this->delete(route('academic.students.destroy', $new), ['roster_revision' => 0])->assertSessionHasErrors('student_ids');
        $this->delete(route('academic.students.bulk-destroy'), ['student_ids' => [$new->id], 'roster_revision' => 0])->assertSessionHasErrors('student_ids');
        $this->patch(route('academic.students.toggle', $new), ['roster_revision' => 0])->assertSessionHasErrors('student_ids');
        $this->assertDatabaseHas('students', ['id' => $new->id, 'is_active' => true]);
        $this->delete(route('academic.students.destroy', $new), ['roster_revision' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('students', 0);
    }

    public function test_delete_all_requires_confirmation_current_roster_and_super_admin(): void
    {
        [$admin, $students] = $this->students();
        $route = route('academic.students.destroy-all');
        $payload = [
            'confirmation' => 'HAPUS SEMUA',
            'expected_count' => 3,
            'expected_last_id' => $students[2]->id,
            'roster_revision' => 0,
        ];

        $this->actingAs($admin)->delete($route, [...$payload, 'confirmation' => 'HAPUS'])->assertSessionHasErrors('confirmation');
        $this->delete($route, [...$payload, 'expected_count' => 2])->assertSessionHasErrors('student_ids');
        $this->delete($route, [...$payload, 'expected_last_id' => $students[1]->id])->assertSessionHasErrors('student_ids');
        $teacher = User::factory()->create(['role' => UserRole::Teacher]);
        $this->actingAs($teacher)->delete($route, $payload)->assertForbidden();
        $this->assertDatabaseCount('students', 3);
    }

    public function test_delete_all_spans_batches_and_rolls_back_if_a_later_student_is_taking_an_exam(): void
    {
        [$admin, $students] = $this->students();
        $classId = $students[0]->school_class_id;
        foreach (array_chunk(range(4, 501), 100) as $batch) {
            Student::query()->insert(array_map(fn (int $i) => [
                'school_class_id' => $classId, 'student_number' => 'BATCH-'.$i, 'full_name' => 'Siswa '.$i,
            ], $batch));
        }
        $last = Student::where('student_number', 'BATCH-501')->firstOrFail();
        [$attempt] = $this->exam($last, 'in_progress');
        $route = route('academic.students.destroy-all');
        $payload = [
            'confirmation' => 'HAPUS SEMUA',
            'expected_count' => 501,
            'expected_last_id' => $last->id,
            'roster_revision' => 0,
        ];

        $this->actingAs($admin)->delete($route, $payload)->assertSessionHasErrors('student_ids');
        $this->assertDatabaseCount('students', 501);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseHas('student_roster_revision', ['id' => 1, 'revision' => 0]);

        $attempt->update(['status' => 'submitted']);
        $this->delete($route, $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('exam_attempts', 0);
        $this->assertDatabaseCount('users', 1);
    }

    private function students(int $count = 3): array
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $year = AcademicYear::create(['name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'X-1', 'grade_level' => 10]);
        foreach (range(1, $count) as $i) {
            $user = User::factory()->create(['role' => UserRole::Student]);
            Student::create(['user_id' => $user->id, 'school_class_id' => $class->id, 'student_number' => 'NIS-'.str_pad($i, 3, '0', STR_PAD_LEFT), 'nisn' => 'NISN-'.$i, 'full_name' => 'Siswa '.str_pad($i, 3, '0', STR_PAD_LEFT), 'is_active' => true]);
        }

        return [$admin, Student::orderBy('id')->get()];
    }

    private function exam(Student $student, string $status = 'submitted'): array
    {
        $period = AssessmentPeriod::create(['academic_year_id' => $student->schoolClass->academic_year_id, 'code' => 'ASAS', 'name' => 'ASAS Ganjil', 'type' => 'aas', 'semester' => 'odd', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addDay()->toDateString(), 'status' => 'published']);
        $subject = Subject::create(['name' => 'Informatika', 'code' => 'INF', 'is_active' => true]);
        $component = AssessmentSubject::create(['assessment_period_id' => $period->id, 'subject_id' => $subject->id, 'school_class_id' => $student->school_class_id]);
        $campus = Campus::create(['name' => 'Kampus', 'latitude' => 0, 'longitude' => 0, 'radius_meters' => 100, 'max_accuracy_meters' => 50]);
        $session = ExamSession::create(['assessment_subject_id' => $component->id, 'campus_id' => $campus->id, 'kind' => 'regular', 'status' => 'active', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'duration_minutes' => 60]);
        $assignment = ExamAssignment::create(['assessment_subject_id' => $component->id, 'student_id' => $student->id, 'exam_session_id' => $session->id, 'status' => 'scheduled', 'assigned_at' => now()]);
        Storage::disk('local')->put('selfies/student.jpg', 'image');
        $checkin = DailyCheckin::create(['student_id' => $student->id, 'campus_id' => $campus->id, 'attendance_date' => now(), 'method' => 'face', 'status' => 'verified', 'selfie_path' => 'selfies/student.jpg', 'latitude' => 0, 'longitude' => 0, 'accuracy_meters' => 1, 'distance_meters' => 0]);
        $attempt = ExamAttempt::create(['exam_assignment_id' => $assignment->id, 'daily_checkin_id' => $checkin->id, 'status' => $status, 'started_at' => now()->subMinute(), 'device_session_hash' => str_repeat('a', 64)]);

        return [$attempt, $component, $checkin];
    }

    private function trial(Student $student, AssessmentSubject $component, string $status = 'submitted'): TrialAttempt
    {
        $bank = TrialQuestionBank::create(['subject_id' => $component->subject_id, 'grade_level' => 10, 'name' => 'Uji Coba '.$student->id]);
        $trial = TrialExam::create(['assessment_subject_id' => $component->id, 'trial_question_bank_id' => $bank->id, 'title' => $bank->name, 'slug' => 'uji-'.$student->id, 'access_code' => 'CODE-'.$student->id, 'duration_minutes' => 60, 'is_active' => true]);

        return TrialAttempt::create(['trial_exam_id' => $trial->id, 'student_id' => $student->id, 'participant_name' => $student->full_name, 'participant_identifier' => $student->nisn, 'session_token_hash' => hash('sha256', 'student-'.$student->id), 'status' => $status, 'started_at' => now()]);
    }

    private function rowNumbers(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        return array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query('//td[@data-student-row-number]')));
    }
}
