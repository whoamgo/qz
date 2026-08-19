<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-curated "Most Popular" flag. When set, the quiz is featured in the
 * Most Popular Quizzes slider on the public home page. Default false keeps
 * every existing quiz out of the slider until an admin opts it in.
 */
return new class extends Migration {
    public function up() {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('is_popular')->default(false)->after('status');
            $table->index('is_popular');
        });
    }

    public function down() {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropIndex(['is_popular']);
            $table->dropColumn('is_popular');
        });
    }
};
