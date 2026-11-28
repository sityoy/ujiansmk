<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_assignments', fn (Blueprint $table) => $table->unsignedSmallInteger('security_reset_count')->default(0));
        Schema::table('trial_attempts', fn (Blueprint $table) => $table->unsignedSmallInteger('security_reset_count')->default(0));

        // Count old resets too, including incidents archived by a whole-attempt reset.
        DB::table('exam_assignments')->select('id')->chunkById(200, function ($assignments): void {
            $ids = $assignments->pluck('id');
            $counts = DB::table('security_incidents')->join('exam_attempts', 'exam_attempts.id', '=', 'security_incidents.exam_attempt_id')
                ->whereIn('exam_attempts.exam_assignment_id', $ids)->whereIn('category', ['supervisor_reset', 'supervisor_resume'])
                ->groupBy('exam_attempts.exam_assignment_id')->selectRaw('exam_attempts.exam_assignment_id as owner_id, count(*) as total')->pluck('total', 'owner_id')->all();
            foreach (DB::table('exam_attempt_resets')->whereIn('exam_assignment_id', $ids)->get() as $reset) {
                $snapshot = json_decode($reset->snapshot, true) ?? [];
                $archived = collect($snapshot['security_incidents'] ?? [])->whereIn('category', ['supervisor_reset', 'supervisor_resume'])->count();
                $counts[$reset->exam_assignment_id] = ($counts[$reset->exam_assignment_id] ?? 0) + 1 + $archived;
            }
            foreach ($counts as $id => $total) {
                DB::table('exam_assignments')->where('id', $id)->update(['security_reset_count' => min(65535, $total)]);
            }
        });
        DB::table('trial_attempts')->select('id')->chunkById(200, function ($attempts): void {
            $counts = DB::table('trial_security_incidents')->whereIn('trial_attempt_id', $attempts->pluck('id'))
                ->where('category', 'supervisor_reset')->groupBy('trial_attempt_id')->selectRaw('trial_attempt_id, count(*) as total')->pluck('total', 'trial_attempt_id');
            foreach ($counts as $id => $total) {
                DB::table('trial_attempts')->where('id', $id)->update(['security_reset_count' => min(65535, $total)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_assignments', fn (Blueprint $table) => $table->dropColumn('security_reset_count'));
        Schema::table('trial_attempts', fn (Blueprint $table) => $table->dropColumn('security_reset_count'));
    }
};
