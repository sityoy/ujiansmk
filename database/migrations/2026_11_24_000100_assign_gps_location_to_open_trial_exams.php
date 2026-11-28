<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $locations = DB::table('trial_locations')->orderBy('id')->limit(2)->pluck('id');
        if ($locations->count() !== 1) {
            return;
        }

        DB::table('trial_exams')->whereNull('trial_location_id')
            ->where('is_active', true)
            ->where(fn ($exams) => $exams->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->update(['trial_location_id' => $locations->first()]);
    }

    public function down(): void
    {
        // Keep the location because it may now be referenced by verified attendance.
    }
};
