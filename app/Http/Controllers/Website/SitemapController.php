<?php

namespace App\Http\Controllers\Website;

use App\Models\Category;
use App\Models\Frontend;
use App\Models\Quiz;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public discovery endpoints: the XML sitemap and robots.txt.
 *
 * Both are served through Laravel (rather than static files in public/) so the
 * sitemap stays in sync with the database and both use the configured app URL —
 * http://localhost/qz in development, https://quizmitra.com in production —
 * without any per-environment editing.
 *
 * These routes are registered in routes/website.php, which loads before
 * web.php's catch-all `/{slug}` route, so the exact paths win.
 */
class SitemapController extends BaseWebsiteController {

    /** GET /sitemap.xml — every indexable public URL, cached. */
    public function index() {
        $xml = Cache::remember('website.sitemap.xml', config('seo.sitemap_cache_ttl', 21600), function () {
            return $this->buildSitemap();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** GET /robots.txt — crawl rules plus a pointer to the sitemap. */
    public function robots() {
        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            '# Private, personalised or non-indexable areas',
            'Disallow: /admin',
            'Disallow: /user/login',
            'Disallow: /user/register',
            'Disallow: /password',
            'Disallow: /profile',
            'Disallow: /quiz/attempt/',
            'Disallow: /quiz/result/',
            'Disallow: /quiz/review/',
            'Disallow: /search',
            '',
            'Sitemap: ' . url('sitemap.xml'),
        ];

        return response(implode("\n", $lines) . "\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** Assembles the full sitemap XML string from live data. */
    protected function buildSitemap(): string {
        $urls = [];

        // ---- Static / landing pages -------------------------------------
        $add = function (string $loc, string $freq, string $priority, $lastmod = null) use (&$urls) {
            $urls[] = compact('loc', 'freq', 'priority', 'lastmod');
        };

        $add(route('home'), 'daily', '1.0');
        $add(route('website.quizzes'), 'daily', '0.9');
        $add(route('website.categories'), 'weekly', '0.7');
        $add(route('exams'), 'weekly', '0.7');
        $add(route('website.mock.tests'), 'weekly', '0.8');
        $add(route('website.pyq'), 'weekly', '0.7');
        $add(route('website.current.affairs.index'), 'daily', '0.8');
        $add(route('website.current.affairs.today'), 'daily', '0.8');
        $add(route('website.current.affairs.weekly'), 'weekly', '0.6');
        $add(route('website.current.affairs.monthly'), 'monthly', '0.6');
        $add(route('website.leaderboard'), 'daily', '0.6');
        $add(route('blog'), 'weekly', '0.6');
        $add(route('website.about'), 'monthly', '0.3');
        $add(route('website.privacy'), 'yearly', '0.2');
        $add(route('website.terms'), 'yearly', '0.2');
        $add(route('website.disclaimer'), 'yearly', '0.2');
        $add(route('contact'), 'yearly', '0.3');

        // ---- Categories & sub-categories --------------------------------
        // Only taxonomy pages that actually hold published quizzes are listed;
        // empty categories are thin pages and are kept out of the index. The
        // id sets are fetched once (two lean DISTINCT queries) so filtering the
        // whole tree stays O(1) with no N+1.
        $quizCatIds = array_flip(
            Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')
                ->whereNotNull('category_id')->distinct()->pluck('category_id')->all()
        );
        $quizSubIds = array_flip(
            Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')
                ->whereNotNull('sub_category_id')->distinct()->pluck('sub_category_id')->all()
        );

        $parents = Category::whereNull('parent_id')
            ->where('status', 1)
            ->with(['children' => fn($q) => $q->where('status', 1)])
            ->get(['id', 'slug', 'updated_at']);

        foreach ($parents as $parent) {
            if (isset($quizCatIds[$parent->id])) {
                $add(route('website.category.show', $parent->slug), 'weekly', '0.7', $parent->updated_at);
            }

            foreach ($parent->children as $child) {
                if (isset($quizSubIds[$child->id])) {
                    $add(
                        route('website.subcategory.show', [$parent->slug, $child->slug]),
                        'weekly', '0.6', $child->updated_at
                    );
                }
            }
        }

        // ---- Published quizzes (with at least one question) -------------
        Quiz::where('status', Quiz::STATUS_PUBLISHED)
            ->has('questions')
            ->select('slug', 'updated_at')
            ->orderBy('id')
            ->chunk(500, function ($quizzes) use ($add) {
                foreach ($quizzes as $quiz) {
                    $add(route('website.quiz.show', $quiz->slug), 'weekly', '0.8', $quiz->updated_at);
                }
            });

        // ---- Blog posts (stored as Frontend "blog.element" rows) --------
        Frontend::where('data_keys', 'blog.element')
            ->select('slug', 'updated_at')
            ->get()
            ->each(function ($post) use ($add) {
                if ($post->slug) {
                    $add(route('blog.details', $post->slug), 'monthly', '0.6', $post->updated_at);
                }
            });

        return $this->renderXml($urls);
    }

    /** Serialises the collected URLs into a urlset document. */
    protected function renderXml(array $urls): string {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $u) {
            $out .= '  <url>' . "\n";
            $out .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>' . "\n";
            if (!empty($u['lastmod'])) {
                $out .= '    <lastmod>' . $u['lastmod']->toAtomString() . '</lastmod>' . "\n";
            }
            $out .= '    <changefreq>' . $u['freq'] . '</changefreq>' . "\n";
            $out .= '    <priority>' . $u['priority'] . '</priority>' . "\n";
            $out .= '  </url>' . "\n";
        }

        $out .= '</urlset>' . "\n";

        return $out;
    }
}
