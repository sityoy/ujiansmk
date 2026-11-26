<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_contexts', function (Blueprint $table): void {
            $table->id();
            $table->text('statement')->nullable();
            $table->string('text_format', 5)->default('plain');
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        foreach (['exam_questions', 'trial_questions'] as $questions) {
            Schema::table($questions, function (Blueprint $table): void {
                $table->foreignId('question_context_id')->nullable()->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['exam_questions', 'trial_questions'] as $questions) {
            Schema::table($questions, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('question_context_id');
            });
        }

        Schema::dropIfExists('question_contexts');
    }
};
