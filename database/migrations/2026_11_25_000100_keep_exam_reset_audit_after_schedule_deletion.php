<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->changeAssignmentNullability(true);
    }

    public function down(): void
    {
        if (DB::table('exam_attempt_resets')->whereNull('exam_assignment_id')->exists()) {
            throw new RuntimeException('Riwayat reset jadwal yang sudah dihapus masih tersimpan.');
        }

        $this->changeAssignmentNullability(false);
    }

    private function changeAssignmentNullability(bool $nullable): void
    {
        Schema::table('exam_attempt_resets', function (Blueprint $table): void {
            $table->dropForeign(['exam_assignment_id']);
        });
        Schema::table('exam_attempt_resets', function (Blueprint $table) use ($nullable): void {
            $table->unsignedBigInteger('exam_assignment_id')->nullable($nullable)->change();
        });
        Schema::table('exam_attempt_resets', function (Blueprint $table): void {
            $table->foreign('exam_assignment_id')->references('id')->on('exam_assignments')->cascadeOnDelete();
        });
    }
};
