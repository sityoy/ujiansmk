<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_exam_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('room_name', 80);
            $table->timestamps();
            $table->unique(['assessment_period_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_rooms');
    }
};
