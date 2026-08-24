<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 P0 — adds keyword / search-intent / priority fields to the SEO
 * entities that Phase 1 already extended (categories + quizzes). These drive
 * the SEO Opportunity Dashboard, cannibalisation detection, and the URL/keyword
 * inventory (§3, §23, §24). Every column is nullable so the frontend behaves
 * identically until an admin fills them in.
 */
return new class extends Migration {
    public function up(): void {
        foreach (['categories', 'quizzes'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $add = function (string $col, callable $def) use ($t, $table) {
                    if (!Schema::hasColumn($table, $col)) {
                        $def($t);
                    }
                };
                $add('primary_keyword',    fn($t) => $t->string('primary_keyword', 191)->nullable()->after('meta_keywords'));
                $add('secondary_keywords', fn($t) => $t->text('secondary_keywords')->nullable()->after('primary_keyword'));
                // Values kept as strings so admins can add classifications later
                // without a schema change; the picker lists a fixed set.
                $add('search_intent',      fn($t) => $t->string('search_intent', 40)->nullable()->after('secondary_keywords'));
                // P0 / P1 / P2 / P3 or null.
                $add('seo_priority',       fn($t) => $t->string('seo_priority', 4)->nullable()->after('search_intent'));

                // Indexes to make dashboard/opportunity queries cheap.
                if (Schema::hasColumn($table, 'primary_keyword')) {
                    $t->index('primary_keyword', $table . '_primary_keyword_idx');
                }
                if (Schema::hasColumn($table, 'seo_priority')) {
                    $t->index('seo_priority', $table . '_seo_priority_idx');
                }
            });
        }

        // Google Search Console metrics import store. Kept independent of the
        // taxonomy so it can be populated later (CSV import or API) without
        // changing anything else. Only URL is required — everything is nullable
        // so partial imports still succeed.
        if (!Schema::hasTable('gsc_query_metrics')) {
            Schema::create('gsc_query_metrics', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('page_url', 512);      // absolute URL from GSC
                $t->string('query', 255)->nullable();
                $t->unsignedInteger('impressions')->default(0);
                $t->unsignedInteger('clicks')->default(0);
                $t->decimal('ctr', 6, 4)->nullable();          // 0.0000–1.0000
                $t->decimal('position', 6, 2)->nullable();     // avg position
                $t->date('date_from')->nullable();
                $t->date('date_to')->nullable();
                $t->timestamp('imported_at')->useCurrent();

                $t->index(['page_url'], 'gsc_page_url_idx');
                $t->index(['position'], 'gsc_position_idx');
                $t->index(['impressions'], 'gsc_impressions_idx');
            });
        }
    }

    public function down(): void {
        foreach (['categories', 'quizzes'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['primary_keyword_idx', 'seo_priority_idx'] as $suffix) {
                    try { $t->dropIndex($table . '_' . $suffix); } catch (\Throwable $e) {}
                }
                foreach (['primary_keyword', 'secondary_keywords', 'search_intent', 'seo_priority'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('gsc_query_metrics');
    }
};
