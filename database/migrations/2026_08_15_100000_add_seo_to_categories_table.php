<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-category SEO fields, editable from the admin panel. Left nullable so the
 * frontend falls back to its auto-generated title/description when they are
 * empty — nothing breaks for the categories that have not been filled in yet.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'meta_title')) {
                $table->string('meta_title', 255)->nullable()->after('icon');
            }
            if (!Schema::hasColumn('categories', 'meta_description')) {
                $table->string('meta_description', 320)->nullable()->after('meta_title');
            }
            if (!Schema::hasColumn('categories', 'meta_keywords')) {
                $table->string('meta_keywords', 255)->nullable()->after('meta_description');
            }
        });
    }

    public function down(): void {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'meta_keywords']);
        });
    }
};
