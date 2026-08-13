<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone quiz attempts. The legacy attend_exams table is bound to
 * exam_id and belongs to the Exam module, so quizzes need their own record.
 * Purely additive - no existing table is touched.
 */
return new class extends Migration {
    public function up() {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('quiz_id');

            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');

            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);

            $table->decimal('score', 10, 2)->default(0);
            $table->decimal('total_marks', 10, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->boolean('passed')->default(false);

            $table->unsignedInteger('time_taken')->default(0)->comment('seconds');
            $table->unsignedInteger('xp_awarded')->default(0);
            // Snapshot of the XP breakdown returned by QuizXpCalculator.
            $table->json('xp_breakdown')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('quiz_id')->references('id')->on('quizzes')->cascadeOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['quiz_id', 'status']);
            $table->index(['user_id', 'quiz_id']);
            $table->index('submitted_at');
        });
    }

    public function down() {
        Schema::dropIfExists('quiz_attempts');
    }
};
