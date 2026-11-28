<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_question_banks', function (Blueprint $table): void {
            $table->dateTime('question_deadline_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trial_question_banks', function (Blueprint $table): void {
            $table->dropColumn('question_deadline_at');
        });
    }
};
