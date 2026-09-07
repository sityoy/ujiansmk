<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->string('room_name', 100)->nullable();
        });

        Schema::create('exam_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_subject_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->json('options');
            $table->string('correct_answer', 5);
            $table->decimal('points', 6, 2)->default(1);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['assessment_subject_id', 'position'], 'exam_question_position_unique');
        });

        Schema::create('exam_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_question_id')->constrained()->cascadeOnDelete();
            $table->string('answer', 5)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'exam_question_id'], 'attempt_question_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_questions');

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropColumn('room_name');
        });
    }
};
