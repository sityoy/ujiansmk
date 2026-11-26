<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['exam_questions', 'trial_questions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('question_image_caption', 500)->nullable();
                $table->json('option_file_captions')->nullable();
            });
        }
        Schema::table('question_contexts', function (Blueprint $table): void {
            $table->string('image_caption', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('question_contexts', fn (Blueprint $table) => $table->dropColumn('image_caption'));
        foreach (['exam_questions', 'trial_questions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['question_image_caption', 'option_file_captions']));
        }
    }
};
