<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('ai_generated_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('generation_id');

            $table->text('question');
            // {"A": "...", "B": "...", "C": "...", "D": "..."}
            $table->json('options');
            $table->string('correct_answer', 10);
            $table->text('explanation')->nullable();
            $table->string('difficulty', 20)->default('medium');
            $table->string('question_type', 20)->default('mcq');

            $table->enum('status', [
                'generated',
                'pending_review',
                'approved',
                'rejected',
                'duplicate',
                'published',
            ])->default('pending_review');

            $table->boolean('duplicate_flag')->default(false);
            // Which existing bank question it looks like, and how closely.
            $table->unsignedBigInteger('duplicate_question_id')->nullable();
            $table->unsignedBigInteger('duplicate_generated_id')->nullable();
            $table->unsignedTinyInteger('similarity_score')->nullable();
            // Set when an admin consciously keeps a flagged duplicate.
            $table->boolean('duplicate_overridden')->default(false);

            // Populated once promoted into the question bank.
            $table->unsignedBigInteger('question_id')->nullable();

            $table->text('validation_errors')->nullable();
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('generation_id')->references('id')->on('ai_question_generations')->cascadeOnDelete();
            $table->foreign('question_id')->references('id')->on('bank_questions')->nullOnDelete();
            $table->foreign('duplicate_question_id')->references('id')->on('bank_questions')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();

            $table->index(['generation_id', 'status']);
            $table->index('duplicate_flag');
        });
    }

    public function down() {
        Schema::dropIfExists('ai_generated_questions');
    }
};
