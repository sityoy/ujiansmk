<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_roster_revision', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });

        DB::table('student_roster_revision')->insert(['id' => 1, 'revision' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('student_roster_revision');
    }
};
