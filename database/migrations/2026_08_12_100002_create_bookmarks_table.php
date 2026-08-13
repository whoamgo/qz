<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic-lite bookmarks: a row targets either a quiz or a bank question,
 * never both. Kept as two nullable FKs rather than a morph so the database
 * still enforces referential integrity.
 */
return new class extends Migration {
    public function up() {
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('quiz_id')->nullable();
            $table->unsignedBigInteger('bank_question_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('quiz_id')->references('id')->on('quizzes')->cascadeOnDelete();
            $table->foreign('bank_question_id')->references('id')->on('bank_questions')->cascadeOnDelete();

            $table->unique(['user_id', 'quiz_id']);
            $table->unique(['user_id', 'bank_question_id']);
            $table->index('user_id');
        });
    }

    public function down() {
        Schema::dropIfExists('bookmarks');
    }
};
