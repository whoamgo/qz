<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('bank_question_id');

            // Null means the question was seen but left unanswered (skipped).
            $table->unsignedBigInteger('selected_option_id')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->boolean('marked_for_review')->default(false);
            $table->decimal('marks_awarded', 8, 2)->default(0);
            $table->unsignedInteger('question_order')->default(0);

            $table->timestamps();

            $table->foreign('attempt_id')->references('id')->on('quiz_attempts')->cascadeOnDelete();
            $table->foreign('bank_question_id')->references('id')->on('bank_questions')->cascadeOnDelete();
            $table->foreign('selected_option_id')->references('id')->on('bank_options')->nullOnDelete();

            // One row per question per attempt, so repeated saves update in place.
            $table->unique(['attempt_id', 'bank_question_id']);
            $table->index(['attempt_id', 'question_order']);
        });
    }

    public function down() {
        Schema::dropIfExists('quiz_attempt_answers');
    }
};
