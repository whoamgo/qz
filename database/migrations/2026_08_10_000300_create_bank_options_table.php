<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('bank_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_question_id');
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('bank_question_id')->references('id')->on('bank_questions')->cascadeOnDelete();
            $table->index(['bank_question_id', 'is_correct']);
        });
    }

    public function down() {
        Schema::dropIfExists('bank_options');
    }
};
