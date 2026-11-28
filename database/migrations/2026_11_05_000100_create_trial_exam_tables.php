<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_exams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_subject_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('access_code', 50)->unique();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('trial_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trial_exam_id')->constrained()->cascadeOnDelete();
            $table->string('participant_name');
            $table->string('participant_identifier', 50)->nullable();
            $table->string('session_token_hash', 64)->unique();
            $table->string('status', 20)->default('in_progress')->index();
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedSmallInteger('correct_answers')->default(0);
            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->timestamps();

            $table->index(['trial_exam_id', 'status']);
        });

        Schema::create('trial_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trial_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->decimal('points_awarded', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['trial_attempt_id', 'exam_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_answers');
        Schema::dropIfExists('trial_attempts');
        Schema::dropIfExists('trial_exams');
    }
};
