<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The quiz importer predates sub-categories being visible on the website and
 * the per-quiz question limit. Both are additive columns on the staging table;
 * existing imports are unaffected.
 */
return new class extends Migration {
    public function up() {
        Schema::table('quiz_import_rows', function (Blueprint $table) {
            $table->string('sub_category_raw', 100)->nullable()->after('category_id');
            $table->unsignedBigInteger('sub_category_id')->nullable()->after('sub_category_raw');
            $table->unsignedInteger('question_limit')->nullable()->after('quiz_status');

            $table->foreign('sub_category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down() {
        Schema::table('quiz_import_rows', function (Blueprint $table) {
            $table->dropForeign(['sub_category_id']);
            $table->dropColumn(['sub_category_raw', 'sub_category_id', 'question_limit']);
        });
    }
};
