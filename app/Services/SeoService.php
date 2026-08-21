<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Quiz;
use Illuminate\Support\Str;

/**
 * Central SEO resolver. Turns an entity's admin-entered SEO fields into the
 * meta payload the public layout consumes, filling any blank field with a
 * sensible generated fallback. Admin-entered values ALWAYS win.
 *
 * Also owns HTML sanitisation for admin-authored SEO content (XSS-safe output)
 * and the advisory SEO score shown in the admin panel.
 */
class SeoService {
    private ?\HTMLPurifier $purifier = null;

    /* =============================================================== category */

    /**
     * Meta payload for a category / sub-category, merged into the controller's
     * seo() call. Falls back to generated values for every empty field.
     *
     * @param array $ctx  ['canonical','quizCount','questionTotal','parent'(Category|null),
     *                      'siteName','image'(url|null),'isSub'(bool)]
     */
    public function categoryMeta(Category $category, array $ctx): array {
        $isSub  = (bool) ($ctx['isSub'] ?? false);
        $parent = $ctx['parent'] ?? null;
        $count  = (int) ($ctx['questionTotal'] ?? 0);
        $quizzes = (int) ($ctx['quizCount'] ?? 0);

        $title = $category->meta_title ?: $this->fallbackTitle($category, $isSub, $parent);
        $desc  = $category->meta_description ?: $this->fallbackDescription($category, $isSub, $parent, $count);

        return [
            'title'               => $title,
            'description'         => $desc,
            'keywords'            => $category->meta_keywords ?: null,
            'canonical'           => $category->canonical_url ?: ($ctx['canonical'] ?? null),
            'robots'              => $this->robots($category, $quizzes),
            'image'               => $category->og_image ?: ($ctx['image'] ?? null),
            'og_title'            => $category->og_title ?: $title,
            'og_description'      => $category->og_description ?: $desc,
            'twitter_title'       => $category->twitter_title ?: ($category->og_title ?: $title),
            'twitter_description' => $category->twitter_description ?: ($category->og_description ?: $desc),
            'schema'              => array_filter([$this->customSchema($category)]),
        ];
    }

    /** On-page content (H1 / intro / main / bottom), purified, with fallbacks. */
    public function categoryContent(Category $category, array $ctx): array {
        $isSub  = (bool) ($ctx['isSub'] ?? false);
        return [
            'h1'     => $category->seo_h1 ?: $this->fallbackH1($category, $isSub),
            'intro'  => $category->seo_intro ?: null,   // view keeps its own auto-intro when null
            'content' => $this->purify($category->seo_content),
            'bottom'  => $this->purify($category->seo_bottom_content),
        ];
    }

    /* ============================================================== fallbacks */

    public function fallbackTitle(Category $category, bool $isSub, ?Category $parent): string {
        if ($isSub && $parent) {
            return "{$category->name} Questions & Quiz – {$parent->name} Practice";
        }
        return "{$category->name} Quiz & Practice Questions";
    }

    public function fallbackDescription(Category $category, bool $isSub, ?Category $parent, int $questionTotal): string {
        $n = $questionTotal > 0 ? number_format($questionTotal) . ' ' : '';
        if ($isSub && $parent) {
            return "Practice {$category->name} questions from {$parent->name} with {$n}free online quizzes, answers and explanations for SSC, Banking, Railway and other competitive exams.";
        }
        return "Practice {$category->name} quizzes with {$n}questions online — free practice with instant results, detailed explanations and XP rewards for competitive-exam preparation.";
    }

    public function fallbackH1(Category $category, bool $isSub): string {
        return $isSub ? "{$category->name} Questions" : "{$category->name} Quizzes";
    }

    /* ============================================ bulk "fill missing" helpers */

    /** Fills only the blank meta title/description/H1 of a category. Returns
     *  true if anything changed. NEVER overwrites admin-entered values. */
    public function fillCategoryDefaults(Category $c, int $quizCount, int $questionTotal): bool {
        $isSub = (bool) $c->parent_id;
        $dirty = false;
        if (blank($c->meta_title)) {
            $c->meta_title = Str::limit($this->fallbackTitle($c, $isSub, $c->parent), 255, '');
            $dirty = true;
        }
        if (blank($c->meta_description)) {
            $c->meta_description = Str::limit($this->fallbackDescription($c, $isSub, $c->parent, $questionTotal), 320, '');
            $dirty = true;
        }
        if (blank($c->seo_h1)) {
            $c->seo_h1 = $this->fallbackH1($c, $isSub);
            $dirty = true;
        }
        if ($dirty) {
            $c->seo_score = $this->score($c, $quizCount);
            $c->seo_updated_at = now();
        }
        return $dirty;
    }

    /** Fills only the blank meta title/description/H1 of a quiz. Returns true if
     *  anything changed. NEVER overwrites admin-entered values. */
    public function fillQuizDefaults(Quiz $q, int $questionCount): bool {
        $dirty = false;
        if (blank($q->meta_title)) {
            $q->meta_title = Str::limit($this->quizFallbackTitle($q), 255, '');
            $dirty = true;
        }
        if (blank($q->meta_description)) {
            $q->meta_description = Str::limit($this->quizFallbackDescription($q, $questionCount), 320, '');
            $dirty = true;
        }
        if (blank($q->seo_h1)) {
            $q->seo_h1 = $q->title;
            $dirty = true;
        }
        if ($dirty) {
            $q->seo_score = $this->score($q, $questionCount);
            $q->seo_updated_at = now();
        }
        return $dirty;
    }

    /** Effective robots string, preserving thin-page protection. */
    public function robots(Category $category, int $quizCount): string {
        $index = ($category->robots_index ?? true)
            && ($quizCount > 0 || filled($category->seo_content));
        $follow = $category->robots_follow ?? true;
        return ($index ? 'index' : 'noindex') . ', ' . ($follow ? 'follow' : 'nofollow');
    }

    /** Decoded admin schema_json if it is valid JSON, else null. Works for any
     *  model exposing a schema_json attribute (Category, Quiz, …). */
    public function customSchema($model): ?array {
        if (blank($model->schema_json)) {
            return null;
        }
        $decoded = json_decode($model->schema_json, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
    }

    /* ================================================================== quiz */

    /** Meta payload for a quiz page — admin overrides, else generated fallback. */
    public function quizMeta(Quiz $quiz, array $ctx): array {
        $count = (int) ($ctx['questionCount'] ?? 0);
        $title = $quiz->meta_title ?: $this->quizFallbackTitle($quiz);
        $desc  = $quiz->meta_description ?: $this->quizFallbackDescription($quiz, $count);
        $index = ($quiz->robots_index ?? true);
        $follow = ($quiz->robots_follow ?? true);

        return [
            'title'               => $title,
            'description'         => $desc,
            'keywords'            => $quiz->meta_keywords ?: null,
            'canonical'           => $quiz->canonical_url ?: ($ctx['canonical'] ?? null),
            'robots'              => ($index ? 'index' : 'noindex') . ', ' . ($follow ? 'follow' : 'nofollow'),
            'type'                => 'article',
            'image'               => $quiz->og_image ?: ($ctx['image'] ?? null),
            'og_title'            => $quiz->og_title ?: $title,
            'og_description'      => $quiz->og_description ?: $desc,
            'twitter_title'       => $quiz->twitter_title ?: ($quiz->og_title ?: $title),
            'twitter_description' => $quiz->twitter_description ?: ($quiz->og_description ?: $desc),
            'schema'              => array_filter([$this->customSchema($quiz)]),
        ];
    }

    /** On-page content (H1 / intro / main), purified, with fallbacks. */
    public function quizContent(Quiz $quiz): array {
        return [
            'h1'      => $quiz->seo_h1 ?: $quiz->title,
            'intro'   => $quiz->seo_intro ?: null,
            'content' => $this->purify($quiz->seo_content),
        ];
    }

    public function quizFallbackTitle(Quiz $quiz): string {
        return $quiz->title . ' — ' . ($quiz->category?->name ?? 'Quiz') . ' Practice Test';
    }

    public function quizFallbackDescription(Quiz $quiz, int $questionCount): string {
        if (filled($quiz->description)) {
            return strip_tags((string) $quiz->description);
        }
        $n = $questionCount > 0 ? $questionCount . ' questions' : 'multiple questions';
        $time = $quiz->time_limit ? "in {$quiz->time_limit} minutes" : 'at your own pace';
        return "Attempt the {$quiz->title} quiz with {$n} {$time}. Difficulty: " . ucfirst((string) $quiz->difficulty)
            . '. Free online practice with instant results and explanations.';
    }

    /* =========================================================== sanitisation */

    /**
     * Sanitises admin-authored HTML for safe frontend output. Allows the tags
     * SEO content needs (headings, lists, tables, links, images, emphasis) and
     * strips scripts / event handlers / unsafe URIs.
     */
    public function purify(?string $html): string {
        if (blank($html)) {
            return '';
        }
        return $this->purifier()->purify($html);
    }

    private function purifier(): \HTMLPurifier {
        if ($this->purifier) {
            return $this->purifier;
        }
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed',
            'h2,h3,h4,p,ul,ol,li,strong,b,em,i,u,a[href|title|rel|target],'
            . 'blockquote,br,hr,span,div,table,thead,tbody,tr,th,td,'
            . 'img[src|alt|title|width|height]'
        );
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('AutoFormat.RemoveEmpty', true);
        // Writable cache dir, or disable serializer cache if not available.
        $cacheDir = storage_path('app/htmlpurifier');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $config->set('Cache.SerializerPath', $cacheDir);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }
        return $this->purifier = new \HTMLPurifier($config);
    }

    /* ============================================================= seo score */

    /**
     * Advisory 0-100 score for the admin panel (not Google's score). Works for
     * any model exposing the standard SEO attributes (Category, Quiz, …).
     */
    public function score($model, int $contentCount = 0): int {
        $checks = [
            filled($model->meta_title),
            $this->between(strlen((string) $model->meta_title), 30, 60),
            filled($model->meta_description),
            $this->between(strlen((string) $model->meta_description), 120, 160),
            filled($model->seo_h1),
            filled($model->seo_intro),
            filled($model->seo_content) && strlen(strip_tags((string) $model->seo_content)) >= 300,
            filled($model->meta_keywords),
            filled($model->og_image),
            $contentCount > 0,
        ];
        $passed = count(array_filter($checks));
        return (int) round($passed / count($checks) * 100);
    }

    private function between(int $n, int $min, int $max): bool {
        return $n >= $min && $n <= $max;
    }
}
