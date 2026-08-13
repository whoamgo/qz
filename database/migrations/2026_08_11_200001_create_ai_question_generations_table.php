<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('ai_question_generations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->unsignedBigInteger('quiz_id')->nullable();

            $table->string('topic')->nullable();
            // Kept as a plain string rather than an enum: the generator offers
            // "expert", which bank_questions.difficulty does not accept.
            $table->string('difficulty', 20)->default('medium');
            $table->string('question_type', 20)->default('mcq');
            $table->string('language', 20)->default('english');
            $table->unsignedInteger('quantity')->default(10);

            $table->string('provider', 30)->nullable();
            $table->string('model', 100)->nullable();
            $table->decimal('temperature', 3, 2)->nullable();

            $table->longText('prompt')->nullable();
            $table->longText('system_prompt')->nullable();
            $table->longText('additional_instructions')->nullable();
            // Full provider payload, retained for debugging and auditing.
            $table->longText('raw_response')->nullable();

            $table->enum('status', [
                'pending',
                'generating',
                'completed',
                'failed',
                'partially_completed',
                'cancelled',
            ])->default('pending');

            $table->text('error_message')->nullable();

            $table->unsignedInteger('requested_count')->default(0);
            $table->unsignedInteger('generated_count')->default(0);
            $table->unsignedInteger('approved_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('published_count')->default(0);

            // Usage is optional - providers that do not report it leave nulls.
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('sub_category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('quiz_id')->references('id')->on('quizzes')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();

            $table->index(['status', 'created_at']);
            $table->index(['category_id', 'sub_category_id']);
        });
    }

    public function down() {
        Schema::dropIfExists('ai_question_generations');
    }
};
