<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Quiz;
use App\Services\SeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Populates fallback SEO values (title, description, H1) for categories and
 * sub-categories that are missing them. NEVER overwrites a field an admin has
 * already filled in — only blank fields are touched. Richer fields (intro,
 * main content) are deliberately left blank for the admin to author, since the
 * frontend already renders a contextual auto-intro when they are empty.
 */
class GenerateSeoDefaults extends Command {
    protected $signature = 'seo:generate-defaults {--dry-run : Show what would change without saving}';
    protected $description = 'Fill missing category/sub-category SEO fields with generated fallbacks (non-destructive)';

    public function handle(SeoService $seo): int {
        $dry = (bool) $this->option('dry-run');
        $updated = 0;

        Category::with('parent')->chunkById(200, function ($cats) use ($seo, $dry, &$updated) {
            foreach ($cats as $cat) {
                $qCount = $this->quizCount($cat);
                // Uses the shared SeoService helper — same logic the admin bulk
                // generator uses. Only blank fields are touched.
                if ($seo->fillCategoryDefaults($cat, $qCount, $this->questionTotal($cat))) {
                    $updated++;
                    if (!$dry) {
                        $cat->save();
                    }
                }
            }
        });

        $this->info(($dry ? '[dry-run] ' : '') . "Populated SEO defaults for {$updated} categorie(s) (missing fields only).");
        return self::SUCCESS;
    }

    private function quizCount(Category $c): int {
        $col = $c->parent_id ? 'sub_category_id' : 'category_id';
        return Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')->where($col, $c->id)->count();
    }

    private function questionTotal(Category $c): int {
        $col = $c->parent_id ? 'sub_category_id' : 'category_id';
        return \App\Models\BankQuestion::where('status', 1)->where($col, $c->id)->count();
    }
}
