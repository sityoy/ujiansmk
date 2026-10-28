<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_profiles', function (Blueprint $table): void {
            $table->string('letterhead_path')->nullable()->after('principal_name');
        });

        Schema::table('school_classes', function (Blueprint $table): void {
            $table->foreignId('homeroom_teacher_user_id')->nullable()->after('major')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('assessment_subjects', function (Blueprint $table): void {
            $table->text('learning_objective')->nullable()->after('school_class_id');
        });

        Schema::create('midterm_subject_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->text('description');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assessment_subject_id', 'student_id'], 'midterm_subject_student_unique');
        });

        Schema::create('extracurriculars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('coach_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
        });

        Schema::create('extracurricular_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extracurricular_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['extracurricular_id', 'student_id'], 'extracurricular_student_unique');
        });

        Schema::create('extracurricular_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extracurricular_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('rating', 24);
            $table->text('description');
            $table->foreignId('graded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'extracurricular_id', 'student_id'],
                'extracurricular_period_student_unique',
            );
        });

        Schema::create('midterm_attendance_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sick_days')->default(0);
            $table->unsignedSmallInteger('excused_days')->default(0);
            $table->unsignedSmallInteger('unexcused_days')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assessment_period_id', 'student_id'], 'midterm_attendance_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('midterm_attendance_summaries');
        Schema::dropIfExists('extracurricular_grades');
        Schema::dropIfExists('extracurricular_participants');
        Schema::dropIfExists('extracurriculars');
        Schema::dropIfExists('midterm_subject_results');

        Schema::table('assessment_subjects', function (Blueprint $table): void {
            $table->dropColumn('learning_objective');
        });

        Schema::table('school_classes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('homeroom_teacher_user_id');
        });

        Schema::table('school_profiles', function (Blueprint $table): void {
            $table->dropColumn('letterhead_path');
        });
    }
};
