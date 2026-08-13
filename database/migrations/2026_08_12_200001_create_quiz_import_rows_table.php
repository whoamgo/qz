<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging table. One row = one CSV line = one question, carrying the quiz
 * fields alongside it. Rows sharing a quiz_key belong to the same quiz.
 */
return new class extends Migration {
    public function up() {
        Schema::create('quiz_import_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id');
            $table->unsignedInteger('row_number');

            // Groups rows into a quiz: normalised slug, or the title if blank.
            $table->string('quiz_key')->nullable()->index();

            // ---- quiz-level fields, as written in the file ----
            $table->string('quiz_title')->nullable();
            $table->string('quiz_slug')->nullable();
            $table->text('quiz_description')->nullable();
            $table->string('category_raw', 100)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('quiz_type', 20)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('quiz_difficulty', 20)->nullable();
            $table->unsignedInteger('time_limit')->nullable();
            $table->unsignedInteger('pass_percentage')->nullable();
            $table->decimal('marks_per_correct', 8, 2)->nullable();
            $table->decimal('negative_marking', 8, 2)->nullable();
            $table->string('quiz_status', 20)->nullable();

            // ---- question-level fields ----
            $table->text('question')->nullable();
            $table->string('question_type', 20)->nullable();
            $table->text('option_a')->nullable();
            $table->text('option_b')->nullable();
            $table->text('option_c')->nullable();
            $table->text('option_d')->nullable();
            $table->string('correct_answer', 10)->nullable();
            $table->text('explanation')->nullable();
            $table->string('question_difficulty', 20)->nullable();

            $table->enum('validation_status', [
                'pending', 'valid', 'invalid', 'duplicate', 'imported', 'failed', 'removed',
            ])->default('pending');
            $table->json('validation_errors')->nullable();

            $table->boolean('duplicate_flag')->default(false);
            $table->string('duplicate_reason', 191)->nullable();
            $table->unsignedBigInteger('duplicate_quiz_id')->nullable();
            $table->unsignedBigInteger('duplicate_question_id')->nullable();

            // Filled once promoted.
            $table->unsignedBigInteger('quiz_id')->nullable();
            $table->unsignedBigInteger('bank_question_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('import_id')->references('id')->on('quiz_imports')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('quiz_id')->references('id')->on('quizzes')->nullOnDelete();
            $table->foreign('bank_question_id')->references('id')->on('bank_questions')->nullOnDelete();
            $table->foreign('duplicate_quiz_id')->references('id')->on('quizzes')->nullOnDelete();
            $table->foreign('duplicate_question_id')->references('id')->on('bank_questions')->nullOnDelete();

            $table->index(['import_id', 'validation_status']);
            $table->index(['import_id', 'row_number']);
        });
    }

    public function down() {
        Schema::dropIfExists('quiz_import_rows');
    }
};
