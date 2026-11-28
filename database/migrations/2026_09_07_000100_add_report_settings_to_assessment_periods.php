<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_periods', function (Blueprint $table): void {
            $table->string('report_place', 120)->nullable()->after('ends_on');
            $table->date('report_date')->nullable()->after('report_place');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_periods', function (Blueprint $table): void {
            $table->dropColumn(['report_place', 'report_date']);
        });
    }
};
