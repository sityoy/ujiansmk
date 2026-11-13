<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['exam_questions', 'trial_questions'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->text('statement')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['exam_questions', 'trial_questions'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('statement');
            });
        }
    }
};
