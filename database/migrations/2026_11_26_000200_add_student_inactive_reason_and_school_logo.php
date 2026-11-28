<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('inactive_reason', 500)->nullable();
        });
        Schema::table('school_profiles', function (Blueprint $table): void {
            $table->string('logo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('inactive_reason');
        });
        Schema::table('school_profiles', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
