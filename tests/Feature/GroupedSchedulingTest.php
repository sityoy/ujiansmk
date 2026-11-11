<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\AttemptStatus;
use App\Enums\PeriodStatus;
use App\Enums\Semester;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Campus;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupedSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 0));
    }

    public function test_schedule_covers_one_grade_across_teachers_and_keeps_class_rosters(): void
    {
        [$components, $campus] = $this->makeComponents();
        $data = $this->scheduleData($components[0], $campus);
        $this->post(route('scheduling.sessions.store'), $data)->assertSessionHasNoErrors();
        $sessions = ExamSession::query()->orderBy('id')->get();
        $this->assertCount(3, $sessions);
        $this->assertSame(1, $sessions->pluck('schedule_group_id')->unique()->count());
        $this->assertNotNull($sessions->first()->schedule_group_id);
        $this->assertDatabaseCount('exam_assignments', 3);
        $this->assertFalse($components[3]->examSessions()->exists());
        $this->assertFalse($components[4]->examSessions()->exists());

        foreach ($sessions as $session) {
            $assignment = $session->assignments()->with('student')->firstOrFail();
            $this->assertSame($session->assessmentSubject->school_class_id, $assignment->student->school_class_id);
            $this->get(route('operations.sessions.show', $session))
                ->assertOk()->assertSee($assignment->student->full_name);
        }

        $this->post(route('scheduling.assign-class', [$components[0], $sessions[0]]))->assertSessionHasNoErrors();
        $this->post(route('scheduling.sessions.store'), $data)->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('exam_sessions', 3);
        $this->assertDatabaseCount('exam_assignments', 3);

        $this->patch(route('scheduling.sessions.update', $sessions[0]), [
            ...$data, 'starts_at' => '2026-11-11 10:00:00',
            'rooms' => [$components[0]->id => 'Lab A', $components[1]->id => 'Lab B', $components[2]->id => 'Lab C'],
        ])->assertSessionHasNoErrors();
        foreach ($sessions as $session) {
            $this->assertSame('10:00', $session->fresh()->starts_at->format('H:i'));
            $this->assertSame('11:15', $session->fresh()->ends_at->format('H:i'));
        }
        $this->assertSame(['Lab A', 'Lab B', 'Lab C'], ExamSession::query()->orderBy('id')->pluck('room_name')->all());
        $this->patch(route('scheduling.sessions.update', $sessions[0]), [
            ...$data, 'starts_at' => '2026-11-11 12:00:00',
            'rooms' => [$components[0]->id => 'Lab A', $components[1]->id => 'Lab A', $components[2]->id => 'Lab C'],
        ])->assertSessionHasErrors('rooms');
        $this->assertSame('10:00', $sessions[0]->fresh()->starts_at->format('H:i'));

        $this->get(route('scheduling.index'))->assertOk()
            ->assertViewHas('components', fn ($groups): bool => $groups->total() === 3)
            ->assertSee('Lihat peserta X-1')->assertSee('Lihat peserta X-2')->assertSee('Lihat peserta X-3');
        $this->get(route('scheduling.index', ['session_class_id' => $components[0]->school_class_id]))
            ->assertOk()->assertViewHas('components', fn ($groups): bool => $groups->total() === 1 && $groups->first()->scheduledClasses->count() === 3);
        $this->get(route('operations.index'))->assertOk()
            ->assertViewHas('sessions', fn ($groups): bool => $groups->total() === 1);

        $this->post(route('scheduling.sessions.store'), $this->scheduleData($components[3], $campus))
            ->assertSessionHasNoErrors();
        $this->post(route('scheduling.sessions.store'), $this->scheduleData($components[4], $campus))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('exam_sessions', 5);
        $this->assertSame(3, ExamSession::query()->distinct()->count('schedule_group_id'));
    }

    public function test_conflict_in_last_class_rolls_back_sessions_and_participants_in_all_classes(): void
    {
        [$components, $campus] = $this->makeComponents();
        $other = Subject::create(['name' => 'Mapel lain', 'code' => 'OTHER', 'is_active' => true]);
        $conflicting = AssessmentSubject::create([
            'assessment_period_id' => $components[2]->assessment_period_id,
            'subject_id' => $other->id, 'school_class_id' => $components[2]->school_class_id,
        ]);
        ExamSession::create([
            ...$this->scheduleData($conflicting, $campus),
            'ends_at' => '2026-11-11 09:15:00',
        ]);

        $this->post(route('scheduling.sessions.store'), $this->scheduleData($components[0], $campus))
            ->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('exam_sessions', 1);
        $this->assertDatabaseCount('exam_assignments', 0);
    }

    public function test_one_started_class_locks_the_whole_schedule_and_closure_is_shared(): void
    {
        [$components, $campus] = $this->makeComponents();
        $data = $this->scheduleData($components[0], $campus);
        $this->post(route('scheduling.sessions.store'), $data)->assertSessionHasNoErrors();
        $sessions = ExamSession::query()->orderBy('id')->get();
        $assignment = $sessions[2]->assignments()->firstOrFail();
        $attempt = ExamAttempt::create([
            'exam_assignment_id' => $assignment->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(), 'device_session_hash' => str_repeat('a', 64),
        ]);
        $this->patch(route('scheduling.sessions.update', $sessions[0]), [
            ...$data, 'starts_at' => '2026-11-11 10:00:00',
        ])->assertSessionHasErrors('session');
        $this->assertSame(['08:00'], ExamSession::query()->get()->pluck('starts_at')->map(fn ($time) => $time->format('H:i'))->unique()->all());

        $this->patch(route('operations.sessions.status', $sessions[0]), ['status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame(3, ExamSession::query()->where('status', SessionStatus::Active)->count());
        $this->travelTo(now()->setTime(8, 30));
        $this->patch(route('operations.sessions.status', $sessions[1]), ['status' => 'closed'])
            ->assertSessionHasNoErrors();
        $this->assertSame(3, ExamSession::query()->where('status', SessionStatus::Closed)->count());
        $this->assertSame(AttemptStatus::Submitted, $attempt->fresh()->status);
        $this->assertSame(2, ExamAssignment::query()->where('status', 'absent')->count());
        $this->patch(route('operations.sessions.status', $sessions[0]), ['status' => 'active'])
            ->assertSessionHasErrors('status');
        $this->assertSame(3, ExamSession::query()->where('status', SessionStatus::Closed)->count());
        $this->post(route('scheduling.assign-class', [$components[0], $sessions[0]]))
            ->assertSessionHasErrors('session');
    }

    public function test_makeup_sources_and_teacher_access_stay_with_the_correct_class(): void
    {
        [$components, $campus] = $this->makeComponents();
        $this->post(route('scheduling.sessions.store'), $this->scheduleData($components[0], $campus))
            ->assertSessionHasNoErrors();
        $regular = ExamSession::query()->orderBy('id')->get();
        $this->post(route('scheduling.sessions.store'), [
            ...$this->scheduleData($components[0], $campus),
            'kind' => 'makeup', 'starts_at' => '2026-11-12 13:00:00', 'source_session_id' => $regular[0]->id,
        ])->assertSessionHasNoErrors();
        $makeup = ExamSession::query()->where('kind', 'makeup')->orderBy('id')->get();
        $this->assertCount(3, $makeup);
        foreach ($makeup as $session) {
            $this->assertSame($session->assessment_subject_id, $session->sourceSession->assessment_subject_id);
            $this->assertSame(0, $session->assignments()->count());
        }
        $assignment = $regular[2]->assignments()->firstOrFail();
        $this->post(route('scheduling.makeup.move'), [
            'assignment_id' => $assignment->id, 'makeup_session_id' => $makeup[2]->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($makeup[2]->id, $assignment->fresh()->exam_session_id);
        $this->assertDatabaseCount('exam_assignments', 3);

        $this->actingAs($components[0]->teacher)->get(route('operations.sessions.show', $regular[0]))
            ->assertOk()->assertDontSee('href="'.route('operations.sessions.show', $regular[2]).'"', false);
        $this->get(route('operations.sessions.show', $regular[2]))->assertForbidden();
        $this->patch(route('operations.sessions.status', $regular[0]), ['status' => 'active'])->assertForbidden();
    }

    public function test_matching_legacy_schedules_are_grouped_without_changing_session_or_roster_ids(): void
    {
        [$components, $campus] = $this->makeComponents();
        foreach ($components->take(3) as $component) {
            $this->post(route('scheduling.sessions.store'), [
                ...$this->scheduleData($component, $campus), 'scope' => 'class',
            ])->assertSessionHasNoErrors();
        }
        $first = ExamSession::query()->firstOrFail();
        $this->post(route('scheduling.assign-class', [$components[0], $first]))->assertSessionHasNoErrors();
        $sessionIds = ExamSession::query()->pluck('id')->all();
        $assignment = ExamAssignment::query()->firstOrFail();
        $migration = require database_path('migrations/2026_11_11_000100_group_exam_schedules_by_grade.php');
        $migration->down();
        $migration->up();

        $this->assertSame($sessionIds, ExamSession::query()->pluck('id')->all());
        $this->assertSame($first->id, $assignment->fresh()->exam_session_id);
        $this->assertSame(1, ExamSession::query()->distinct()->count('schedule_group_id'));
        $this->assertNotNull($first->fresh()->schedule_group_id);
    }

    private function scheduleData(AssessmentSubject $component, Campus $campus): array
    {
        return [
            'assessment_subject_id' => $component->id, 'scope' => 'grade',
            'campus_id' => $campus->id, 'kind' => 'regular', 'status' => 'published',
            'starts_at' => '2026-11-11 08:00:00', 'duration_minutes' => 75,
        ];
    }

    private function makeComponents(): array
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        $teachers = User::factory()->count(2)->create(['role' => UserRole::Teacher]);
        $year = AcademicYear::create([
            'name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $period = AssessmentPeriod::create([
            'academic_year_id' => $year->id, 'code' => 'AAS-GROUP', 'name' => 'AAS Ganjil',
            'type' => AssessmentType::AAS, 'semester' => Semester::Odd, 'status' => PeriodStatus::Published,
            'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30',
        ]);
        $subject = Subject::create(['code' => 'ADM', 'name' => 'Administrasi Pajak', 'is_active' => true]);
        $campus = Campus::create([
            'name' => 'Sekolah', 'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meters' => 100, 'max_accuracy_meters' => 50, 'is_active' => true,
        ]);
        $components = new Collection;
        foreach ([10, 10, 10, 11, 12] as $index => $grade) {
            $class = SchoolClass::create([
                'academic_year_id' => $year->id, 'name' => ['X-1', 'X-2', 'X-3', 'XI-1', 'XII-1'][$index],
                'grade_level' => $grade,
            ]);
            $components->push(AssessmentSubject::create([
                'assessment_period_id' => $period->id, 'subject_id' => $subject->id, 'school_class_id' => $class->id,
                'teacher_user_id' => $teachers[$index === 2 ? 1 : 0]->id,
            ]));
            Student::create([
                'school_class_id' => $class->id, 'student_number' => 'S-'.$index,
                'full_name' => 'Peserta '.$class->name, 'is_active' => true,
            ]);
        }
        Student::create([
            'school_class_id' => $components[0]->school_class_id, 'student_number' => 'INACTIVE',
            'full_name' => 'Siswa nonaktif', 'is_active' => false,
        ]);

        return [$components, $campus];
    }
}
