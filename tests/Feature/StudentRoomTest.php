<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\CreatesRoomPeriods;
use Tests\TestCase;

class StudentRoomTest extends TestCase
{
    use CreatesRoomPeriods;
    use RefreshDatabase;

    private AssessmentPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_room_can_be_assigned_changed_and_cleared_without_changing_credentials_or_class(): void
    {
        $student = $this->makeStudents()->first();
        $before = $student->user->getAttributes();
        $classId = $student->school_class_id;
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        $route = route('academic.students.room', $student);
        foreach (['  Ruang 01  ' => 'Ruang 01', 'Lab Komputer' => 'Lab Komputer', '  ' => null] as $input => $expected) {
            $this->from(route('academic.index', ['student_class' => $classId]))
                ->patch($route, ['room_name' => $input, 'assessment_period_id' => $this->period->id])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('academic.index', ['student_class' => $classId]));
            $this->assertSame($expected, $this->room($student));
        }
        $this->assertSame($before, $student->user->fresh()->getAttributes());
        $this->assertSame($classId, $student->fresh()->school_class_id);
    }

    public function test_bulk_assignment_only_updates_selected_students_and_allows_clearing(): void
    {
        [$first, $second, $untouched] = $this->makeStudents();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $hashes = User::query()->pluck('password', 'id')->all();
        $route = route('academic.students.rooms');
        $data = ['student_ids' => [$first->id, $second->id], 'bulk_room_name' => 'Lab Bahasa', 'assessment_period_id' => $this->period->id];
        $this->actingAs($admin)->patch($route, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Lab Bahasa', $this->room($first));
        $this->assertSame('Lab Bahasa', $this->room($second));
        $this->assertSame('Ruang Lama', $this->room($untouched));
        $this->patch($route, [...$data, 'bulk_room_name' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->room($first));
        $this->assertNull($this->room($second));
        $this->assertSame('Ruang Lama', $this->room($untouched));
        $this->assertSame($hashes, User::query()->pluck('password', 'id')->all());
    }

    public function test_invalid_selection_or_room_is_rejected_before_any_assignment_changes(): void
    {
        $student = $this->makeStudents()->first();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        $route = route('academic.students.rooms');
        foreach ([
            [[], 'student_ids'],
            [['student_ids' => [$student->id, 999999], 'bulk_room_name' => 'Baru'], 'student_ids.1'],
            [['student_ids' => [$student->id, $student->id], 'bulk_room_name' => 'Baru'], 'student_ids.0'],
            [['student_ids' => [$student->id], 'bulk_room_name' => str_repeat('R', 81)], 'bulk_room_name'],
            [['student_ids' => [$student->id]], 'bulk_room_name'],
        ] as [$data, $error]) {
            $this->patch($route, [...$data, 'assessment_period_id' => $this->period->id])->assertSessionHasErrors($error);
            $this->assertSame('Ruang Lama', $this->room($student));
        }
        $this->patch(route('academic.students.room', $student), [])->assertSessionHasErrors('room_name');
        $this->patch(route('academic.students.room', $student), ['room_name' => str_repeat('R', 81), 'assessment_period_id' => $this->period->id])
            ->assertSessionHasErrors('room_name');
        $this->assertSame('Ruang Lama', $this->room($student));
    }

    public function test_only_academic_managers_can_assign_student_rooms(): void
    {
        $student = $this->makeStudents()->first();
        foreach ([UserRole::Teacher, UserRole::Committee, UserRole::Student] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->patch(route('academic.students.room', $student), ['room_name' => 'Baru'])->assertForbidden();
            $this->patch(route('academic.students.rooms'), ['student_ids' => [$student->id], 'bulk_room_name' => 'Baru'])->assertForbidden();
        }
        $this->assertSame('Ruang Lama', $this->room($student));
    }

    public function test_student_list_can_find_room_assignments_and_exposes_bulk_selection_controls(): void
    {
        [$first, $second] = $this->makeStudents();
        $first->examRooms()->where('assessment_period_id', $this->period->id)->update(['room_name' => 'Lab Bahasa']);
        $second->examRooms()->delete();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->get(route('academic.index', ['student_search' => 'Lab Bahasa', 'room_period_id' => $this->period->id]))
            ->assertOk()
            ->assertViewHas('students', fn ($students) => $students->count() === 1 && $students->first()->id === $first->id)
            ->assertSee('data-room-select-all', false)
            ->assertSee('form="student-room-bulk"', false)
            ->assertSee('Lab Bahasa')
            ->assertSee('Simpan ruangan siswa terpilih');
    }

    public function test_assignment_and_printing_are_isolated_between_periods_and_never_use_the_old_global_room(): void
    {
        $student = $this->makeStudents()->first();
        $student->forceFill(['room_name' => 'Ruangan Global Lama'])->save();
        $other = $this->makeRoomPeriod($this->period->academic_year_id, 'AAS');
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->patch(route('academic.students.room', $student), [
            'assessment_period_id' => $other->id, 'room_name' => 'Lab AAS',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Ruang Lama', $this->room($student));
        $this->assertDatabaseHas('student_exam_rooms', ['student_id' => $student->id, 'assessment_period_id' => $other->id, 'room_name' => 'Lab AAS']);
        $this->post(route('academic.students.participant-cards'), ['school_class_id' => 'all', 'assessment_period_id' => $other->id])
            ->assertOk()->assertSee('AAS Ganjil')->assertSee('Lab AAS')->assertDontSee('Ruang Lama')->assertDontSee('Ruangan Global Lama');
        $this->post(route('academic.students.participant-cards'), ['school_class_id' => 'all', 'assessment_period_id' => $this->period->id])
            ->assertOk()->assertSee('ATS Ganjil')->assertSee('Ruang Lama')->assertDontSee('Lab AAS');
        $this->patch(route('academic.students.room', $student), ['assessment_period_id' => $other->id, 'room_name' => ''])
            ->assertSessionHasNoErrors();
        $this->post(route('academic.students.participant-cards'), ['school_class_id' => 'all', 'assessment_period_id' => $other->id])
            ->assertOk()->assertSee('Belum diatur')->assertDontSee('Lab AAS')->assertDontSee('Ruang Lama')->assertDontSee('Ruangan Global Lama');
        $this->assertSame('Ruang Lama', $this->room($student));
    }

    public function test_room_updates_require_a_period_and_reject_students_from_another_academic_year_atomically(): void
    {
        $student = $this->makeStudents()->first();
        $oldYear = AcademicYear::create(['name' => '2025/2026', 'starts_on' => '2025-07-01', 'ends_on' => '2026-06-30', 'is_active' => false]);
        $oldClass = SchoolClass::create(['academic_year_id' => $oldYear->id, 'name' => 'X-1', 'grade_level' => 10]);
        $other = Student::create(['user_id' => User::factory()->create(['role' => UserRole::Student])->id, 'school_class_id' => $oldClass->id, 'student_number' => 'OLD', 'full_name' => 'Siswa Lama']);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->patch(route('academic.students.room', $student), ['room_name' => 'Baru'])
            ->assertSessionHasErrors('assessment_period_id');
        $this->patch(route('academic.students.rooms'), ['student_ids' => [$student->id, $other->id], 'assessment_period_id' => $this->period->id, 'bulk_room_name' => 'Baru'])
            ->assertSessionHasErrors('assessment_period_id');
        $this->patch(route('academic.students.room', $other), ['assessment_period_id' => $this->period->id, 'room_name' => 'Baru'])
            ->assertSessionHasErrors('assessment_period_id');
        $this->assertSame('Ruang Lama', $this->room($student));
        $this->assertNull($this->room($other));
        $this->get(route('academic.index'))->assertOk()->assertDontSee('id="student-room-bulk"', false);
        $this->get(route('academic.index', ['room_period_id' => 999999]))->assertSessionHasErrors('room_period_id');
    }

    public function test_deleting_a_period_only_removes_its_room_assignments(): void
    {
        $student = $this->makeStudents()->first();
        $other = $this->makeRoomPeriod($this->period->academic_year_id, 'AAS');
        $student->examRooms()->create(['assessment_period_id' => $other->id, 'room_name' => 'Lab AAS']);
        $this->period->delete();
        $this->assertDatabaseMissing('student_exam_rooms', ['assessment_period_id' => $this->period->id]);
        $this->assertDatabaseHas('student_exam_rooms', ['assessment_period_id' => $other->id, 'student_id' => $student->id, 'room_name' => 'Lab AAS']);
    }

    private function makeStudents(): Collection
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $this->period = $this->makeRoomPeriod($year->id);
        $classes = collect(['X-1', 'X-2'])->map(fn ($name) => SchoolClass::create([
            'academic_year_id' => $year->id, 'name' => $name, 'grade_level' => 10,
        ]));

        return collect(range(1, 3))->map(fn ($number) => Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::Student])->id,
            'school_class_id' => $classes[($number - 1) % 2]->id,
            'student_number' => 'S-00'.$number, 'full_name' => 'Siswa '.$number,
            'is_active' => true,
        ]))->each(fn ($student) => $student->examRooms()->create(['assessment_period_id' => $this->period->id, 'room_name' => 'Ruang Lama']));
    }

    private function room(Student $student): ?string
    {
        return $student->examRooms()->where('assessment_period_id', $this->period->id)->value('room_name');
    }
}
