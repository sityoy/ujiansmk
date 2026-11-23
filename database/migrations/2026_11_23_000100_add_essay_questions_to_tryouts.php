<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_questions', function (Blueprint $table): void {
            $table->string('question_type', 30)->default('multiple_choice');
            $table->json('options')->nullable()->change();
            $table->string('correct_answer', 5)->nullable()->change();
        });
        Schema::table('trial_responses', function (Blueprint $table): void {
            $table->boolean('is_correct')->nullable()->default(null)->change();
            $table->decimal('points_awarded', 8, 2)->nullable()->default(null)->change();
        });
        Schema::table('trial_attempts', function (Blueprint $table): void {
            $table->string('grading_status', 20)->default('automatic');
            $table->unsignedSmallInteger('multiple_choice_questions')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('trial_questions')->where('question_type', 'essay')->exists()) {
            throw new LogicException('Rollback is blocked while trial essay questions exist.');
        }

        Schema::table('trial_attempts', function (Blueprint $table): void {
            $table->dropColumn(['grading_status', 'multiple_choice_questions']);
        });
        Schema::table('trial_questions', function (Blueprint $table): void {
            $table->dropColumn('question_type');
            $table->json('options')->change();
            $table->string('correct_answer', 5)->change();
        });
        DB::table('trial_responses')->whereNull('is_correct')->update(['is_correct' => false]);
        DB::table('trial_responses')->whereNull('points_awarded')->update(['points_awarded' => 0]);
        Schema::table('trial_responses', function (Blueprint $table): void {
            $table->boolean('is_correct')->default(false)->change();
            $table->decimal('points_awarded', 8, 2)->default(0)->change();
        });
    }
};
