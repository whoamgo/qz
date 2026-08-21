<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Quiz;
use Illuminate\Support\Facades\Cache;

/**
 * Shared helpers for the public site: SEO payload assembly and the cached
 * category lookups the header and footer need on every request.
 */
abstract class BaseWebsiteController extends Controller {
    /**
     * Preferred slugs for the Exams section, in display order. This is a
     * PREFERENCE, not a whitelist: slugs that no longer exist are skipped, and
     * if none of them match (categories were restructured) every top-level
     * category is offered instead. That keeps /exams working through an admin
     * reorganisation rather than 404ing on a stale hard-coded list.
     */
    const PREFERRED_EXAM_SLUGS = [
        'competitive-exams', 'ssc-exams', 'railway-exams', 'banking-exams',
        'upsc-exams', 'defence-exams', 'state-psc-exams', 'teaching-exams',
        'school-quiz', 'mock-tests',
    ];

    /** Resolves the Exams section against categories that actually exist. */
    protected function examCategoryQuery() {
        $preferred = Category::whereNull('parent_id')
            ->where('status', 1)
            ->whereIn('slug', self::PREFERRED_EXAM_SLUGS);

        if ((clone $preferred)->exists()) {
            return $preferred;
        }

        return Category::whereNull('parent_id')->where('status', 1);
    }

    /** True when a slug is a valid destination for /exams/{slug}. */
    protected function isExamCategory(string $slug): bool {
        return Category::whereNull('parent_id')->where('status', 1)->where('slug', $slug)->exists();
    }

    const CURRENT_AFFAIRS_SLUG = 'current-affairs';
    const MOCK_TEST_SLUG       = 'mock-tests';
    const PYQ_SLUG             = 'previous-year-questions';

    /**
     * Builds the SEO block consumed by layouts/app.blade.php. Every indexable
     * page passes its own values so no two pages share a title/description.
     */
    protected function seo(array $data): array {
        $siteName = gs('site_name') ?: config('app.name');

        $title = $data['title'] ?? $siteName;
        $description = \Illuminate\Support\Str::limit(strip_tags($data['description'] ?? ''), 158, '');

        return [
            'title'       => $title,
            'description' => $description,
            'keywords'    => $data['keywords'] ?? null,
            'canonical'   => $data['canonical'] ?? url()->current(),
            'image'       => $data['image'] ?? null,
            'robots'      => $data['robots'] ?? 'index, follow',
            'type'        => $data['type'] ?? 'website',
            // Optional per-page social overrides. Default to the page title/description
            // so existing pages are unaffected; SEO-managed pages can override them.
            'og_title'            => $data['og_title'] ?? $title,
            'og_description'      => \Illuminate\Support\Str::limit(strip_tags($data['og_description'] ?? $description), 200, ''),
            'twitter_title'       => $data['twitter_title'] ?? ($data['og_title'] ?? $title),
            'twitter_description' => \Illuminate\Support\Str::limit(strip_tags($data['twitter_description'] ?? ($data['og_description'] ?? $description)), 200, ''),
            'schema'      => $data['schema'] ?? [],
            'breadcrumbs' => $data['breadcrumbs'] ?? [],
        ];
    }

    /** Parent categories with their active sub-categories, cached for an hour. */
    protected function navCategories() {
        return Cache::remember('website.nav.categories', 3600, function () {
            return Category::whereNull('parent_id')
                ->where('status', 1)
                ->with(['children' => fn($q) => $q->where('status', 1)->orderBy('name')])
                ->orderBy('name')
                ->get();
        });
    }

    protected function categoryBySlug(string $slug): ?Category {
        return Category::where('slug', $slug)->where('status', 1)->first();
    }

    /**
     * Base query for anything publicly listable: published quizzes that
     * actually have at least one question attached.
     */
    protected function publishedQuizzes() {
        return Quiz::where('status', Quiz::STATUS_PUBLISHED)
            ->has('questions')
            ->with(['category:id,name,slug', 'subCategory:id,name,slug']);
    }

    /** BreadcrumbList schema from a [label => url] trail. */
    protected function breadcrumbSchema(array $trail): array {
        $items = [];
        $position = 1;

        foreach ($trail as $label => $url) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $label,
                'item'     => $url,
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    protected function faqSchema(array $faqs): array {
        $items = [];
        foreach ($faqs as $faq) {
            $items[] = [
                '@type'          => 'Question',
                'name'           => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
            ];
        }

        return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items];
    }
}
