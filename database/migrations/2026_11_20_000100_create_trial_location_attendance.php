<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters');
            $table->unsignedSmallInteger('max_accuracy_meters');
            $table->timestamps();
        });

        Schema::table('trial_exams', function (Blueprint $table): void {
            $table->foreignId('trial_location_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('trial_checkins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trial_exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('class_name');
            $table->decimal('distance_meters', 8, 2);
            $table->decimal('accuracy_meters', 8, 2);
            $table->dateTime('verified_at');
            $table->timestamps();
            $table->unique(['trial_exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_checkins');

        Schema::table('trial_exams', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trial_location_id');
        });

        Schema::dropIfExists('trial_locations');
    }
};
