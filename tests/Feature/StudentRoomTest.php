<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StudentRoomTest extends TestCase
{
    use RefreshDatabase;

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
                ->patch($route, ['room_name' => $input])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('academic.index', ['student_class' => $classId]));
            $this->assertSame($expected, $student->fresh()->room_name);
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
        $data = ['student_ids' => [$first->id, $second->id], 'bulk_room_name' => 'Lab Bahasa'];
        $this->actingAs($admin)->patch($route, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Lab Bahasa', $first->fresh()->room_name);
        $this->assertSame('Lab Bahasa', $second->fresh()->room_name);
        $this->assertSame('Ruang Lama', $untouched->fresh()->room_name);
        $this->patch($route, [...$data, 'bulk_room_name' => ''])->assertSessionHasNoErrors();
        $this->assertNull($first->fresh()->room_name);
        $this->assertNull($second->fresh()->room_name);
        $this->assertSame('Ruang Lama', $untouched->fresh()->room_name);
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
            $this->patch($route, $data)->assertSessionHasErrors($error);
            $this->assertSame('Ruang Lama', $student->fresh()->room_name);
        }
        $this->patch(route('academic.students.room', $student), [])->assertSessionHasErrors('room_name');
        $this->patch(route('academic.students.room', $student), ['room_name' => str_repeat('R', 81)])
            ->assertSessionHasErrors('room_name');
        $this->assertSame('Ruang Lama', $student->fresh()->room_name);
    }

    public function test_only_academic_managers_can_assign_student_rooms(): void
    {
        $student = $this->makeStudents()->first();
        foreach ([UserRole::Teacher, UserRole::Committee, UserRole::Student] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->patch(route('academic.students.room', $student), ['room_name' => 'Baru'])->assertForbidden();
            $this->patch(route('academic.students.rooms'), ['student_ids' => [$student->id], 'bulk_room_name' => 'Baru'])->assertForbidden();
        }
        $this->assertSame('Ruang Lama', $student->fresh()->room_name);
    }

    public function test_student_list_can_find_room_assignments_and_exposes_bulk_selection_controls(): void
    {
        [$first, $second] = $this->makeStudents();
        $first->update(['room_name' => 'Lab Bahasa']);
        $second->update(['room_name' => null]);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->get(route('academic.index', ['student_search' => 'Lab Bahasa']))
            ->assertOk()
            ->assertViewHas('students', fn ($students) => $students->count() === 1 && $students->first()->id === $first->id)
            ->assertSee('data-room-select-all', false)
            ->assertSee('form="student-room-bulk"', false)
            ->assertSee('Lab Bahasa')
            ->assertSee('Simpan ruangan siswa terpilih');
    }

    private function makeStudents(): Collection
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $classes = collect(['X-1', 'X-2'])->map(fn ($name) => SchoolClass::create([
            'academic_year_id' => $year->id, 'name' => $name, 'grade_level' => 10,
        ]));

        return collect(range(1, 3))->map(fn ($number) => Student::create([
            'user_id' => User::factory()->create(['role' => UserRole::Student])->id,
            'school_class_id' => $classes[($number - 1) % 2]->id,
            'student_number' => 'S-00'.$number, 'full_name' => 'Siswa '.$number,
            'room_name' => 'Ruang Lama', 'is_active' => true,
        ]));
    }
}
