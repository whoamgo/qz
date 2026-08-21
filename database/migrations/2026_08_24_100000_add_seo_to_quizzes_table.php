<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an admin-managed SEO field set to quizzes, mirroring the category SEO
 * columns. Every field is nullable/defaulted and empty by default, so quiz
 * pages keep using the generated fallbacks (SeoService) until an admin
 * overrides them. `description` already exists and is reused as a fallback.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('quizzes', function (Blueprint $table) {
            $add = function (string $col, callable $def) use ($table) {
                if (!Schema::hasColumn('quizzes', $col)) {
                    $def($table);
                }
            };

            $add('meta_title',         fn($t) => $t->string('meta_title', 255)->nullable()->after('description'));
            $add('meta_description',   fn($t) => $t->string('meta_description', 320)->nullable()->after('meta_title'));
            $add('meta_keywords',      fn($t) => $t->string('meta_keywords', 255)->nullable()->after('meta_description'));
            $add('seo_h1',             fn($t) => $t->string('seo_h1', 255)->nullable()->after('meta_keywords'));
            $add('seo_intro',          fn($t) => $t->text('seo_intro')->nullable()->after('seo_h1'));
            $add('seo_content',        fn($t) => $t->longText('seo_content')->nullable()->after('seo_intro'));
            $add('canonical_url',      fn($t) => $t->string('canonical_url', 512)->nullable()->after('seo_content'));
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
        Schema::table('quizzes', function (Blueprint $table) {
            foreach ([
                'meta_title', 'meta_description', 'meta_keywords', 'seo_h1', 'seo_intro', 'seo_content',
                'canonical_url', 'og_title', 'og_description', 'og_image', 'twitter_title', 'twitter_description',
                'robots_index', 'robots_follow', 'schema_json', 'seo_score', 'seo_updated_at',
            ] as $col) {
                if (Schema::hasColumn('quizzes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
