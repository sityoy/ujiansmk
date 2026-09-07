<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table): void {
            // Existing attempts keep their original policy; new attempts explicitly opt in.
            $table->boolean('security_enabled')->default(false);
            $table->timestamp('security_locked_at')->nullable();
            $table->unsignedInteger('security_lock_version')->default(0);
        });
        Schema::table('security_incidents', function (Blueprint $table): void {
            $table->uuid('event_id')->nullable();
            $table->unique(['exam_attempt_id', 'event_id'], 'exam_security_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('security_incidents', function (Blueprint $table): void {
            $table->dropUnique('exam_security_event_unique');
            $table->dropColumn('event_id');
        });
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->dropColumn(['security_enabled', 'security_locked_at', 'security_lock_version']);
        });
    }
};
