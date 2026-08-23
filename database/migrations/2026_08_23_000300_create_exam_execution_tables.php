<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('method', 20);
            $table->string('status', 20)->default('review')->index();
            $table->string('card_reference_hash', 64)->nullable();
            $table->string('selfie_path')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 8, 2);
            $table->decimal('distance_meters', 8, 2);
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'attendance_date'], 'student_daily_checkin_unique');
            $table->index(['campus_id', 'attendance_date', 'status']);
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('daily_checkin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('in_progress')->index();
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedSmallInteger('correct_answers')->default(0);
            $table->unsignedSmallInteger('incorrect_answers')->default(0);
            $table->unsignedSmallInteger('violation_count')->default(0);
            $table->string('device_session_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50)->index();
            $table->json('details')->nullable();
            $table->unsignedTinyInteger('severity')->default(1);
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['exam_attempt_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('daily_checkins');
    }
};
