<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_periods', function (Blueprint $table): void {
            $table->string('report_paper_size', 8)->default('f4')->after('report_date');
            $table->unsignedTinyInteger('report_margin_top_mm')->default(8)->after('report_paper_size');
            $table->unsignedTinyInteger('report_margin_right_mm')->default(8)->after('report_margin_top_mm');
            $table->unsignedTinyInteger('report_margin_bottom_mm')->default(8)->after('report_margin_right_mm');
            $table->unsignedTinyInteger('report_margin_left_mm')->default(8)->after('report_margin_bottom_mm');
            $table->unsignedTinyInteger('report_scale_percent')->default(90)->after('report_margin_left_mm');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_periods', function (Blueprint $table): void {
            $table->dropColumn([
                'report_paper_size',
                'report_margin_top_mm',
                'report_margin_right_mm',
                'report_margin_bottom_mm',
                'report_margin_left_mm',
                'report_scale_percent',
            ]);
        });
    }
};
