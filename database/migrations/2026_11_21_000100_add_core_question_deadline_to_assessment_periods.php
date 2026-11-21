<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_periods', function (Blueprint $table): void {
            $table->dateTime('core_question_deadline_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_periods', function (Blueprint $table): void {
            $table->dropColumn('core_question_deadline_at');
        });
    }
};
