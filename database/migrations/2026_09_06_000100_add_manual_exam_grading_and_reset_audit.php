<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_subjects', function (Blueprint $table): void {
            $table->foreignId('teacher_user_id')->nullable()->after('school_class_id')
                ->constrained('users')->nullOnDelete();
        });
        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->string('question_type', 30)->default('multiple_choice')->after('question_text');
            $table->json('options')->nullable()->change();
            $table->string('correct_answer', 5)->nullable()->change();
        });
        Schema::table('exam_answers', function (Blueprint $table): void {
            $table->text('answer')->nullable()->change();
            $table->decimal('points_awarded', 6, 2)->nullable()->after('is_correct');
            $table->foreignId('graded_by_user_id')->nullable()->after('points_awarded')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable()->after('graded_by_user_id');
        });
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->string('grading_status', 20)->default('automatic')->after('score')->index();
            $table->dateTime('graded_at')->nullable()->after('grading_status');
        });
        Schema::create('exam_attempt_resets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('original_attempt_id');
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('snapshot');
            $table->timestamps();

            $table->index(['exam_assignment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_resets');
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->dropIndex(['grading_status']);
            $table->dropColumn(['grading_status', 'graded_at']);
        });
        Schema::table('exam_answers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('graded_by_user_id');
            $table->dropColumn(['points_awarded', 'graded_at']);
            $table->string('answer', 5)->nullable()->change();
        });
        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->dropColumn('question_type');
            $table->json('options')->nullable(false)->change();
            $table->string('correct_answer', 5)->nullable(false)->change();
        });
        Schema::table('assessment_subjects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('teacher_user_id');
        });
    }
};
