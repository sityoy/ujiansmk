<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (Artisan::call('exams:sync-student-roster') !== 0) {
            throw new RuntimeException('Daftar peserta ujian reguler gagal disinkronkan.');
        }
    }

    public function down(): void
    {
        // Placements may already have answers and must be preserved.
    }
};
