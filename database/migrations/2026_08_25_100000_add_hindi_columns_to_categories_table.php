<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Hindi content columns for categories + sub-categories (same table).
 * Every column is NULLABLE and mirrors the type/length of its English
 * counterpart, placed immediately after it. No existing column or data is
 * touched. Fully reversible. Guarded so the migration is safe to re-run.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('categories', function (Blueprint $table) {
            $add = function (string $col, callable $def) use ($table) {
                if (!Schema::hasColumn('categories', $col)) {
                    $def($table);
                }
            };

            $add('name_hi',               fn($t) => $t->string('name_hi', 40)->nullable()->after('name'));
            $add('meta_title_hi',         fn($t) => $t->string('meta_title_hi', 255)->nullable()->after('meta_title'));
            $add('meta_description_hi',   fn($t) => $t->string('meta_description_hi', 320)->nullable()->after('meta_description'));
            $add('meta_keywords_hi',      fn($t) => $t->string('meta_keywords_hi', 255)->nullable()->after('meta_keywords'));
            $add('primary_keyword_hi',    fn($t) => $t->string('primary_keyword_hi', 191)->nullable()->after('primary_keyword'));
            $add('secondary_keywords_hi', fn($t) => $t->text('secondary_keywords_hi')->nullable()->after('secondary_keywords'));
            $add('seo_h1_hi',             fn($t) => $t->string('seo_h1_hi', 255)->nullable()->after('seo_h1'));
            $add('seo_intro_hi',          fn($t) => $t->text('seo_intro_hi')->nullable()->after('seo_intro'));
            $add('seo_content_hi',        fn($t) => $t->longText('seo_content_hi')->nullable()->after('seo_content'));
            $add('seo_bottom_content_hi', fn($t) => $t->longText('seo_bottom_content_hi')->nullable()->after('seo_bottom_content'));
        });
    }

    public function down(): void {
        Schema::table('categories', function (Blueprint $table) {
            foreach ([
                'name_hi', 'meta_title_hi', 'meta_description_hi', 'meta_keywords_hi',
                'primary_keyword_hi', 'secondary_keywords_hi', 'seo_h1_hi', 'seo_intro_hi',
                'seo_content_hi', 'seo_bottom_content_hi',
            ] as $col) {
                if (Schema::hasColumn('categories', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
