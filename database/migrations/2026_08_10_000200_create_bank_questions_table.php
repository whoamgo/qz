<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('bank_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->enum('question_type', ['mcq_single', 'mcq_multi', 'true_false'])->default('mcq_single');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->text('question_text');
            $table->text('explanation')->nullable();
            $table->string('hint')->nullable();
            $table->decimal('default_marks', 8, 2)->default(1);
            $table->unsignedBigInteger('correct_option_id')->nullable();
            $table->boolean('status')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('sub_category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['category_id', 'sub_category_id']);
            $table->index(['difficulty', 'question_type']);
        });
    }

    public function down() {
        Schema::dropIfExists('bank_questions');
    }
};
