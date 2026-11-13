<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_question_banks', function (Blueprint $table): void {
            $table->string('source_pack_key', 80)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('trial_question_banks', function (Blueprint $table): void {
            $table->dropUnique(['source_pack_key']);
            $table->dropColumn('source_pack_key');
        });
    }
};
