<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_question_banks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('grade_level')->index();
            $table->string('name', 150);
            $table->timestamps();
            $table->unique(['subject_id', 'grade_level', 'name'], 'trial_bank_subject_grade_name_unique');
        });

        Schema::create('trial_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trial_question_bank_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->string('question_image_path')->nullable();
            $table->json('options');
            $table->string('correct_answer', 5);
            $table->decimal('points', 8, 2)->default(1);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['trial_question_bank_id', 'position'], 'trial_bank_question_position_unique');
        });

        Schema::table('trial_exams', function (Blueprint $table): void {
            $table->foreignId('trial_question_bank_id')->nullable()->after('assessment_subject_id')
                ->constrained()->restrictOnDelete();
        });

        Schema::create('trial_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trial_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trial_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->decimal('points_awarded', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['trial_attempt_id', 'trial_question_id'], 'trial_attempt_question_unique');
        });

        $this->migrateExistingTrials();
        $this->normalizeUnlockedCoreBanks();
    }

    private function migrateExistingTrials(): void
    {
        $trials = DB::table('trial_exams')->orderBy('id')->get();

        foreach ($trials as $trial) {
            $component = DB::table('assessment_subjects')
                ->join('school_classes', 'school_classes.id', '=', 'assessment_subjects.school_class_id')
                ->where('assessment_subjects.id', $trial->assessment_subject_id)
                ->select('assessment_subjects.*', 'school_classes.grade_level')
                ->first();

            if (! $component) {
                continue;
            }

            $bankId = DB::table('trial_question_banks')->insertGetId([
                'subject_id' => $component->subject_id,
                'teacher_user_id' => $component->teacher_user_id ?? null,
                'grade_level' => $component->grade_level,
                'name' => mb_substr('Migrasi - '.$trial->title, 0, 150),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('trial_exams')->where('id', $trial->id)->update([
                'trial_question_bank_id' => $bankId,
                'updated_at' => now(),
            ]);

            $questionMap = [];
            $questions = DB::table('exam_questions')
                ->where('assessment_subject_id', $component->id)
                ->where('question_type', 'multiple_choice')
                ->orderBy('position')->get();

            foreach ($questions as $index => $question) {
                $trialQuestionId = DB::table('trial_questions')->insertGetId([
                    'trial_question_bank_id' => $bankId,
                    'question_text' => $question->question_text,
                    'question_image_path' => $question->question_image_path,
                    'options' => $question->options,
                    'correct_answer' => $question->correct_answer,
                    'points' => $question->points,
                    'position' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $questionMap[$question->id] = $trialQuestionId;
            }

            $legacyAnswers = DB::table('trial_answers')
                ->join('trial_attempts', 'trial_attempts.id', '=', 'trial_answers.trial_attempt_id')
                ->where('trial_attempts.trial_exam_id', $trial->id)
                ->select('trial_answers.*')->get();

            foreach ($legacyAnswers as $answer) {
                if (! isset($questionMap[$answer->exam_question_id])) {
                    continue;
                }
                DB::table('trial_responses')->insertOrIgnore([
                    'trial_attempt_id' => $answer->trial_attempt_id,
                    'trial_question_id' => $questionMap[$answer->exam_question_id],
                    'answer' => $answer->answer,
                    'is_correct' => $answer->is_correct,
                    'points_awarded' => $answer->points_awarded,
                    'created_at' => $answer->created_at,
                    'updated_at' => $answer->updated_at,
                ]);
            }
        }
    }

    private function normalizeUnlockedCoreBanks(): void
    {
        $components = DB::table('assessment_subjects')
            ->join('school_classes', 'school_classes.id', '=', 'assessment_subjects.school_class_id')
            ->select('assessment_subjects.id', 'assessment_subjects.assessment_period_id',
                'assessment_subjects.subject_id', 'school_classes.grade_level')
            ->orderBy('assessment_subjects.id')->get();

        $groups = $components->groupBy(fn ($component): string =>
            $component->assessment_period_id.'|'.$component->subject_id.'|'.$component->grade_level
        );

        foreach ($groups as $group) {
            $componentIds = $group->pluck('id')->all();
            if (count($componentIds) < 2) {
                continue;
            }

            $hasAttempts = DB::table('exam_attempts')
                ->join('exam_assignments', 'exam_assignments.id', '=', 'exam_attempts.exam_assignment_id')
                ->whereIn('exam_assignments.assessment_subject_id', $componentIds)
                ->exists();
            if ($hasAttempts) {
                continue;
            }

            $counts = DB::table('exam_questions')->whereIn('assessment_subject_id', $componentIds)
                ->select('assessment_subject_id', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('assessment_subject_id')->pluck('aggregate', 'assessment_subject_id');
            $canonicalId = collect($componentIds)->sortByDesc(fn ($id) => (int) ($counts[$id] ?? 0))->first();
            $canonicalQuestions = DB::table('exam_questions')
                ->where('assessment_subject_id', $canonicalId)->orderBy('position')->get();

            foreach ($componentIds as $componentId) {
                if ($componentId === $canonicalId) {
                    continue;
                }
                DB::table('exam_questions')->where('assessment_subject_id', $componentId)->delete();
                foreach ($canonicalQuestions as $index => $question) {
                    DB::table('exam_questions')->insert([
                        'assessment_subject_id' => $componentId,
                        'question_text' => $question->question_text,
                        'question_image_path' => $question->question_image_path,
                        'question_type' => $question->question_type,
                        'options' => $question->options,
                        'correct_answer' => $question->correct_answer,
                        'points' => $question->points,
                        'position' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_responses');
        Schema::table('trial_exams', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trial_question_bank_id');
        });
        Schema::dropIfExists('trial_questions');
        Schema::dropIfExists('trial_question_banks');
    }
};
