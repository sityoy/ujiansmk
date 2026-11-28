<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_attempts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_minutes')->nullable();
        });

        DB::table('trial_attempts')->update([
            'duration_minutes' => DB::raw('(select trial_exams.duration_minutes from trial_exams where trial_exams.id = trial_attempts.trial_exam_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('trial_attempts', function (Blueprint $table): void {
            $table->dropColumn('duration_minutes');
        });
    }
};
