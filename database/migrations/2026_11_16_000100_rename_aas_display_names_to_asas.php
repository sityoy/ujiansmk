<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename display text only. Existing period types, codes, access codes,
        // slugs and foreign keys remain compatible with previously issued cards.
        foreach (['assessment_periods' => 'name', 'trial_exams' => 'title', 'trial_question_banks' => 'name'] as $table => $column) {
            DB::table($table)->select(['id', $column])->orderBy('id')->chunkById(200, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $name = preg_replace('/(?<![\pL\pN])AAS(?![\pL\pN])/iu', 'ASAS', $row->{$column});
                    $name = preg_replace('/Asesmen Akhir Semester/iu', 'Asesmen Sumatif Akhir Semester', $name);
                    if ($name !== $row->{$column}) {
                        DB::table($table)->where('id', $row->id)->update([$column => $name]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Display names may have been edited after deployment; do not overwrite them.
    }
};
