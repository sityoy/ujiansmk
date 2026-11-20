<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->json('option_file_paths')->nullable()->after('options');
        });
        Schema::table('trial_questions', function (Blueprint $table): void {
            $table->json('option_file_paths')->nullable()->after('options');
        });
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->decimal('manual_score', 5, 2)->nullable()->after('score');
        });
        Schema::table('trial_attempts', function (Blueprint $table): void {
            $table->foreignId('student_id')->nullable()->after('trial_exam_id')
                ->constrained()->nullOnDelete();
            $table->index(['trial_exam_id', 'student_id']);
        });

        DB::table('exam_questions')
            ->where('question_type', 'multiple_choice')
            ->update(['points' => 2]);
        DB::table('trial_questions')->update(['points' => 2]);

        DB::table('trial_attempts')->orderBy('id')->each(function ($attempt): void {
            $trial = DB::table('trial_exams')
                ->join('trial_question_banks', 'trial_question_banks.id', '=', 'trial_exams.trial_question_bank_id')
                ->where('trial_exams.id', $attempt->trial_exam_id)
                ->select('trial_question_banks.grade_level')
                ->first();
            if (! $trial) {
                return;
            }

            $student = DB::table('students')
                ->join('school_classes', 'school_classes.id', '=', 'students.school_class_id')
                ->where('students.is_active', true)
                ->where('school_classes.grade_level', $trial->grade_level)
                ->where(function ($query) use ($attempt): void {
                    $query->where('students.nisn', $attempt->participant_identifier)
                        ->orWhere('students.student_number', $attempt->participant_identifier);
                })
                ->select('students.id')
                ->first();

            if ($student) {
                DB::table('trial_attempts')->where('id', $attempt->id)->update(['student_id' => $student->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('trial_attempts', function (Blueprint $table): void {
            $table->dropIndex(['trial_exam_id', 'student_id']);
            $table->dropConstrainedForeignId('student_id');
        });
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->dropColumn('manual_score');
        });
        Schema::table('trial_questions', function (Blueprint $table): void {
            $table->dropColumn('option_file_paths');
        });
        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->dropColumn('option_file_paths');
        });
    }
};
