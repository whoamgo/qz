<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('question_import_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id');
            $table->unsignedInteger('row_number');

            // Raw values exactly as they appeared in the file, kept so the original
            // upload is always recoverable even when lookup/resolution fails.
            $table->string('category_name')->nullable();
            $table->string('sub_category_name')->nullable();

            // Resolved foreign keys, null when the name could not be matched.
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();

            $table->text('question')->nullable();
            $table->string('question_type', 20)->nullable();
            $table->text('option_a')->nullable();
            $table->text('option_b')->nullable();
            $table->text('option_c')->nullable();
            $table->text('option_d')->nullable();
            $table->string('correct_answer', 10)->nullable();
            $table->text('explanation')->nullable();
            $table->string('difficulty', 20)->nullable();

            $table->enum('validation_status', [
                'pending',
                'valid',
                'invalid',
                'duplicate',
                'imported',
                'failed',
                'removed',
            ])->default('pending');
            $table->json('validation_errors')->nullable();
            $table->boolean('duplicate_flag')->default(false);
            $table->unsignedBigInteger('duplicate_question_id')->nullable();

            // Set once the row has been promoted into bank_questions.
            $table->unsignedBigInteger('bank_question_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('import_id')->references('id')->on('question_imports')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('sub_category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('duplicate_question_id')->references('id')->on('bank_questions')->nullOnDelete();
            $table->foreign('bank_question_id')->references('id')->on('bank_questions')->nullOnDelete();

            $table->index(['import_id', 'validation_status']);
            $table->index(['import_id', 'row_number']);
        });
    }

    public function down() {
        Schema::dropIfExists('question_import_rows');
    }
};
