<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many questions a single attempt should serve, drawn at random from the
 * quiz's full bank. 0 (the default) means "serve every attached question",
 * which preserves the existing behaviour for every current quiz.
 *
 * total_questions stays as the count of questions ATTACHED to the quiz;
 * question_limit only controls how many of them any one attempt sees.
 */
return new class extends Migration {
    public function up() {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedInteger('question_limit')->default(0)->after('total_questions');
        });
    }

    public function down() {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('question_limit');
        });
    }
};
