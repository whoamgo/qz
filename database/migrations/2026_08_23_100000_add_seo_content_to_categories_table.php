<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing category SEO fields (meta_title/meta_description/
 * meta_keywords already exist) into a full, admin-managed SEO content set used
 * by both categories and sub-categories (same table, distinguished by parent_id).
 *
 * Every column is nullable/defaulted so existing rows keep working untouched —
 * the frontend falls back to generated values when a field is empty. Guarded
 * with hasColumn so the migration is safe to re-run.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('categories', function (Blueprint $table) {
            $add = function (string $col, callable $def) use ($table) {
                if (!Schema::hasColumn('categories', $col)) {
                    $def($table);
                }
            };

            $add('seo_h1',             fn($t) => $t->string('seo_h1', 255)->nullable()->after('meta_keywords'));
            $add('seo_intro',          fn($t) => $t->text('seo_intro')->nullable()->after('seo_h1'));
            $add('seo_content',        fn($t) => $t->longText('seo_content')->nullable()->after('seo_intro'));
            $add('seo_bottom_content', fn($t) => $t->longText('seo_bottom_content')->nullable()->after('seo_content'));
            $add('canonical_url',      fn($t) => $t->string('canonical_url', 512)->nullable()->after('seo_bottom_content'));
            $add('og_title',           fn($t) => $t->string('og_title', 255)->nullable()->after('canonical_url'));
            $add('og_description',     fn($t) => $t->string('og_description', 320)->nullable()->after('og_title'));
            $add('og_image',           fn($t) => $t->string('og_image', 512)->nullable()->after('og_description'));
            $add('twitter_title',      fn($t) => $t->string('twitter_title', 255)->nullable()->after('og_image'));
            $add('twitter_description',fn($t) => $t->string('twitter_description', 320)->nullable()->after('twitter_title'));
            $add('robots_index',       fn($t) => $t->boolean('robots_index')->default(true)->after('twitter_description'));
            $add('robots_follow',      fn($t) => $t->boolean('robots_follow')->default(true)->after('robots_index'));
            $add('schema_json',        fn($t) => $t->longText('schema_json')->nullable()->after('robots_follow'));
            $add('seo_score',          fn($t) => $t->unsignedTinyInteger('seo_score')->nullable()->after('schema_json'));
            $add('seo_updated_at',     fn($t) => $t->timestamp('seo_updated_at')->nullable()->after('seo_score'));
        });
    }

    public function down(): void {
        Schema::table('categories', function (Blueprint $table) {
            foreach ([
                'seo_h1', 'seo_intro', 'seo_content', 'seo_bottom_content', 'canonical_url',
                'og_title', 'og_description', 'og_image', 'twitter_title', 'twitter_description',
                'robots_index', 'robots_follow', 'schema_json', 'seo_score', 'seo_updated_at',
            ] as $col) {
                if (Schema::hasColumn('categories', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
