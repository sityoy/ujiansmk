<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('midterm_subject_results', function (Blueprint $table): void {
            $table->json('achieved_objectives')->nullable()->after('description');
            $table->json('improvement_objectives')->nullable()->after('achieved_objectives');
        });
    }

    public function down(): void
    {
        Schema::table('midterm_subject_results', function (Blueprint $table): void {
            $table->dropColumn(['achieved_objectives', 'improvement_objectives']);
        });
    }
};
