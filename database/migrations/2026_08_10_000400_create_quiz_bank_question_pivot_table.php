<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('quiz_bank_question', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quiz_id');
            $table->unsignedBigInteger('bank_question_id');
            $table->unsignedInteger('question_order')->default(0);
            $table->decimal('marks', 8, 2)->nullable();
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('quizzes')->cascadeOnDelete();
            $table->foreign('bank_question_id')->references('id')->on('bank_questions')->cascadeOnDelete();
            $table->unique(['quiz_id', 'bank_question_id']);
            $table->index(['quiz_id', 'question_order']);
        });
    }

    public function down() {
        Schema::dropIfExists('quiz_bank_question');
    }
};
