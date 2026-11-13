<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_attempts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('violation_count')->default(0)->after('total_questions');
            $table->dateTime('security_locked_at')->nullable()->after('violation_count');
            $table->unsignedInteger('security_lock_version')->default(0)->after('security_locked_at');
            $table->dateTime('last_seen_at')->nullable()->after('security_lock_version');
            $table->string('ip_address', 45)->nullable()->after('last_seen_at');
            $table->text('user_agent')->nullable()->after('ip_address');
        });

        Schema::create('trial_security_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trial_attempt_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id')->nullable()->unique();
            $table->string('category', 50);
            $table->json('details')->nullable();
            $table->unsignedTinyInteger('severity')->default(1);
            $table->dateTime('occurred_at')->index();
            $table->timestamps();

            $table->index(['trial_attempt_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_security_incidents');

        Schema::table('trial_attempts', function (Blueprint $table): void {
            $table->dropColumn([
                'violation_count', 'security_locked_at', 'security_lock_version',
                'last_seen_at', 'ip_address', 'user_agent',
            ]);
        });
    }
};
