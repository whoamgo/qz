<?php

namespace App\Http\Controllers\Website;

use App\Models\Frontend;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Blog posts live in the `frontends` table under data_keys = 'blog.element',
 * which is the store the admin Frontend Manager already writes to.
 */
class BlogController extends BaseWebsiteController {
    public function index(Request $request) {
        $query = Frontend::where('data_keys', 'blog.element');

        if ($search = trim((string) $request->q)) {
            // data_values is a JSON blob; a LIKE over it is adequate at this
            // volume and avoids a schema change.
            $query->where('data_values', 'like', "%{$search}%");
        }

        $all = $query->latest('id')->get();

        $featured = $request->filled('q') || $request->filled('page') ? null : $all->first();
        $listing  = $featured ? $all->skip(1) : $all;

        $page    = max(1, (int) $request->input('page', 1));
        $perPage = 9;
        $blogs   = new \Illuminate\Pagination\LengthAwarePaginator(
            $listing->forPage($page, $perPage)->values(),
            $listing->count(),
            $perPage,
            $page,
            ['path' => route('blog'), 'query' => $request->query()]
        );

        $seo = $this->seo([
            'title'       => 'Blog — Exam Tips, Study Guides and Preparation Strategy',
            'description' => 'Read preparation strategies, study guides and exam tips for competitive examinations, general knowledge and current affairs.',
            'canonical'   => route('blog'),
            'schema'      => [$this->breadcrumbSchema([
                'Home' => route('home'),
                'Blog' => route('blog'),
            ])],
        ]);

        return view('website.blog.index', compact('seo', 'blogs', 'featured'));
    }

    public function show($slug) {
        $blog = Frontend::where('data_keys', 'blog.element')->where('slug', $slug)->first();
        abort_if(!$blog, 404);

        $values = $blog->data_values;

        $related = Frontend::where('data_keys', 'blog.element')
            ->where('id', '!=', $blog->id)
            ->latest('id')
            ->limit(3)
            ->get();

        $prev = Frontend::where('data_keys', 'blog.element')->where('id', '<', $blog->id)->latest('id')->first();
        $next = Frontend::where('data_keys', 'blog.element')->where('id', '>', $blog->id)->oldest('id')->first();

        $body        = (string) ($values->description ?? '');
        $readingTime = max(1, (int) ceil(str_word_count(strip_tags($body)) / 200));

        // Blog SEO is admin-managed via the Frontend Manager (frontends.seo_content:
        // description, social_title, social_description, keywords, image, meta_robots).
        // Consume all of it here with sensible fallbacks.
        $seoContent = $blog->seo_content;
        $title    = $values->title ?? 'Article';
        $metaDesc = $seoContent->description ?? Str::limit(strip_tags($body), 158, '');
        $keywords = null;
        if (!empty($seoContent->keywords)) {
            $keywords = is_array($seoContent->keywords) ? implode(', ', $seoContent->keywords) : $seoContent->keywords;
        }
        $seoImage = !empty($seoContent->image)
            ? getImage('assets/images/frontend/blog/seo/' . $seoContent->image, getFileSize('seo'))
            : (!empty($values->image) ? frontendImage('blog', $values->image) : null);
        $ogTitle = $seoContent->social_title ?: $title;
        $ogDesc  = $seoContent->social_description ?: $metaDesc;

        $seo = $this->seo([
            'title'               => $title,
            'description'         => $metaDesc,
            'keywords'            => $keywords,
            'robots'              => $seoContent->meta_robots ?: 'index, follow',
            'canonical'           => route('blog.details', $blog->slug),
            'type'                => 'article',
            'image'               => $seoImage,
            'og_title'            => $ogTitle,
            'og_description'      => $ogDesc,
            'twitter_title'       => $ogTitle,
            'twitter_description' => $ogDesc,
            'schema'      => [
                [
                    '@context'      => 'https://schema.org',
                    '@type'         => 'Article',
                    'headline'      => $values->title ?? '',
                    'description'   => Str::limit(strip_tags($body), 158, ''),
                    'datePublished' => optional($blog->created_at)->toIso8601String(),
                    'dateModified'  => optional($blog->updated_at)->toIso8601String(),
                    'author'        => ['@type' => 'Organization', 'name' => gs('site_name') ?: config('app.name')],
                    'mainEntityOfPage' => route('blog.details', $blog->slug),
                ],
                $this->breadcrumbSchema([
                    'Home' => route('home'),
                    'Blog' => route('blog'),
                    (string) ($values->title ?? 'Article') => route('blog.details', $blog->slug),
                ]),
            ],
        ]);

        return view('website.blog.show', compact('seo', 'blog', 'values', 'related', 'prev', 'next', 'readingTime'));
    }
}
