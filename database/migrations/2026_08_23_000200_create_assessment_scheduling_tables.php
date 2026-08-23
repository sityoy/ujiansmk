<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('type', 20)->index();
            $table->string('semester', 10)->index();
            $table->unsignedTinyInteger('sequence_no')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();

            $table->unique(['academic_year_id', 'code']);
        });

        Schema::create('assessment_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'subject_id', 'school_class_id'],
                'assessment_subject_unique'
            );
        });

        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
            $table->string('kind', 20)->default('regular')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('access_token_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['assessment_subject_id', 'starts_at']);
            $table->index(['campus_id', 'starts_at']);
        });

        Schema::create('exam_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_session_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('scheduled')->index();
            $table->dateTime('assigned_at');
            $table->timestamps();

            $table->unique(
                ['student_id', 'assessment_subject_id'],
                'student_assessment_subject_unique'
            );
            $table->index(['exam_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_assignments');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('assessment_subjects');
        Schema::dropIfExists('assessment_periods');
    }
};
