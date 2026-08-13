<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->enum('quiz_type', ['free', 'paid', 'subscription'])->default('free');
            $table->decimal('price', 12, 2)->default(0);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('time_limit')->default(0)->comment('minutes');
            $table->unsignedTinyInteger('pass_percentage')->default(0);
            $table->decimal('marks_per_correct', 8, 2)->default(1);
            $table->decimal('negative_marking', 8, 2)->default(0);
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('randomize_options')->default(false);
            $table->boolean('show_result')->default(true);
            $table->boolean('show_correct_answers')->default(true);
            $table->boolean('show_explanation')->default(true);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('image')->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('sub_category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['category_id', 'sub_category_id']);
            $table->index(['status', 'quiz_type']);
        });
    }

    public function down() {
        Schema::dropIfExists('quizzes');
    }
};
