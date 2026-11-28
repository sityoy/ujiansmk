<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->uuid('schedule_group_id')->nullable()->index();
            $table->unique(['schedule_group_id', 'assessment_subject_id'], 'schedule_group_component_unique');
        });

        // Keep existing session, participant, and attempt IDs when combining matching regular schedules.
        $sessions = DB::table('exam_sessions')
            ->join('assessment_subjects', 'assessment_subjects.id', '=', 'exam_sessions.assessment_subject_id')
            ->join('school_classes', 'school_classes.id', '=', 'assessment_subjects.school_class_id')
            ->where('exam_sessions.kind', 'regular')
            ->select('exam_sessions.*', 'assessment_subjects.assessment_period_id',
                'assessment_subjects.subject_id', 'school_classes.grade_level')
            ->orderBy('exam_sessions.id')->get();
        $groups = $sessions->groupBy(fn ($session): string => json_encode([
            $session->assessment_period_id, $session->subject_id, $session->grade_level,
            $session->campus_id, $session->status, $session->starts_at, $session->ends_at, $session->duration_minutes,
        ]));

        foreach ($groups as $group) {
            if ($group->count() > 1 && $group->pluck('assessment_subject_id')->unique()->count() === $group->count()) {
                DB::table('exam_sessions')->whereIn('id', $group->pluck('id'))
                    ->update(['schedule_group_id' => (string) Str::uuid()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropUnique('schedule_group_component_unique');
            $table->dropIndex(['schedule_group_id']);
            $table->dropColumn('schedule_group_id');
        });
    }
};
