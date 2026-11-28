<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_questions', function (Blueprint $table): void {
            $table->unsignedBigInteger('retired_after_attempt_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trial_questions', function (Blueprint $table): void {
            $table->dropColumn('retired_after_attempt_id');
        });
    }
};
