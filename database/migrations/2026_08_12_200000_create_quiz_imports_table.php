<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header record for a quiz-import batch. Mirrors question_imports, but a batch
 * here produces Quizzes (with their questions attached) rather than loose
 * question-bank rows. Purely additive.
 */
return new class extends Migration {
    public function up() {
        Schema::create('quiz_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('file_name');
            $table->string('stored_file');
            $table->string('file_type', 10)->default('csv');

            $table->enum('status', [
                'uploaded', 'processing', 'validation_failed',
                'ready_for_review', 'approved', 'completed', 'failed', 'cancelled',
            ])->default('uploaded');

            // Row counters (one CSV row = one question).
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('valid_records')->default(0);
            $table->unsignedInteger('invalid_records')->default(0);
            $table->unsignedInteger('duplicate_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);

            // Quiz-level counters, derived from the grouped rows.
            $table->unsignedInteger('total_quizzes')->default(0);
            $table->unsignedInteger('valid_quizzes')->default(0);
            $table->unsignedInteger('imported_quizzes')->default(0);
            $table->unsignedInteger('imported_questions')->default(0);

            $table->unsignedBigInteger('file_cursor')->default(0);
            $table->text('error_message')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
            $table->index(['status', 'created_at']);
        });
    }

    public function down() {
        Schema::dropIfExists('quiz_imports');
    }
};
