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

        $seoContent = $blog->seo_content;

        $seo = $this->seo([
            'title'       => $values->title ?? 'Article',
            'description' => $seoContent->description ?? Str::limit(strip_tags($body), 158, ''),
            'canonical'   => route('blog.details', $blog->slug),
            'type'        => 'article',
            'image'       => !empty($values->image) ? frontendImage('blog', $values->image) : null,
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
