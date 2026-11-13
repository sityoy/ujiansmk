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
                $table->string('text_format', 10)->default('plain');
            });
        }
    }

    public function down(): void
    {
        foreach (['exam_questions', 'trial_questions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('text_format');
            });
        }
    }
};
