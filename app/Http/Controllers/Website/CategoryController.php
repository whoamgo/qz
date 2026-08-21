<?php

namespace App\Http\Controllers\Website;

use App\Models\BankQuestion;
use App\Models\Category;
use App\Services\SeoService;

class CategoryController extends BaseWebsiteController {
    public function __construct(private SeoService $seoService) {}

    public function index() {
        $categories = Category::whereNull('parent_id')
            ->where('status', 1)
            ->withCount([
                'children as sub_count' => fn($q) => $q->where('status', 1),
            ])
            ->orderBy('name')
            ->get();

        // One grouped query rather than a count per category.
        $quizCounts = $this->publishedQuizzes()
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $questionCounts = BankQuestion::where('status', 1)
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $seo = $this->seo([
            'title'       => 'All Quiz Categories — Browse by Subject and Exam',
            'description' => 'Explore every quiz category, from General Knowledge and Current Affairs to SSC, Railway, Banking, UPSC and Defence exam preparation.',
            'canonical'   => route('website.categories'),
            'schema'      => [$this->breadcrumbSchema([
                'Home'       => route('home'),
                'Categories' => route('website.categories'),
            ])],
        ]);

        return view('website.categories.index', compact('seo', 'categories', 'quizCounts', 'questionCounts'));
    }

    public function show($slug) {
        $category = Category::where('slug', $slug)
            ->whereNull('parent_id')
            ->where('status', 1)
            ->firstOrFail();

        $subCategories = Category::where('parent_id', $category->id)
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        $subQuizCounts = $this->publishedQuizzes()
            ->whereIn('sub_category_id', $subCategories->pluck('id'))
            ->selectRaw('sub_category_id, COUNT(*) as total')
            ->groupBy('sub_category_id')
            ->pluck('total', 'sub_category_id');

        $popularQuizzes = $this->publishedQuizzes()
            ->where('category_id', $category->id)
            ->withCount('attempts')
            ->orderByDesc('attempts_count')
            ->limit(6)
            ->get();

        $latestQuizzes = $this->publishedQuizzes()
            ->where('category_id', $category->id)
            ->latest('id')
            ->paginate(12);

        $questionTotal = BankQuestion::where('category_id', $category->id)->where('status', 1)->count();

        $faqs = [
            ['question' => "What does the {$category->name} category cover?", 'answer' => $subCategories->count()
                ? "It covers {$subCategories->count()} topics with {$questionTotal} practice questions across every published {$category->name} quiz."
                : "It brings together {$questionTotal} practice questions across every published {$category->name} quiz."],
            ['question' => 'Are these quizzes free?', 'answer' => 'Quizzes marked Free can be attempted without payment. Any paid or subscription quiz is labelled on its card.'],
            ['question' => 'Do I earn XP in this category?', 'answer' => 'Yes. Completing any quiz awards XP, which counts towards your level, badges and leaderboard rank.'],
        ];

        // Admin-managed SEO (fields on the category) with generated fallbacks;
        // thin-page noindex protection is preserved inside SeoService::robots().
        $meta = $this->seoService->categoryMeta($category, [
            'canonical'     => route('website.category.show', $category->slug),
            'quizCount'     => $latestQuizzes->total(),
            'questionTotal' => $questionTotal,
            'parent'        => null,
            'isSub'         => false,
            'image'         => $category->image ? getImage(getFilePath('category') . '/' . $category->image, getFileSize('category')) : null,
        ]);
        $seoContent = $this->seoService->categoryContent($category, ['isSub' => false]);

        $seo = $this->seo(array_merge($meta, [
            'schema' => array_merge($meta['schema'], [
                $this->faqSchema($faqs),
                $this->breadcrumbSchema([
                    'Home'          => route('home'),
                    'Categories'    => route('website.categories'),
                    $category->name => route('website.category.show', $category->slug),
                ]),
            ]),
        ]));

        return view('website.categories.show', compact(
            'seo', 'seoContent', 'category', 'subCategories', 'subQuizCounts',
            'popularQuizzes', 'latestQuizzes', 'questionTotal', 'faqs'
        ));
    }

    public function subCategory($parentSlug, $childSlug) {
        $category = Category::where('slug', $parentSlug)->whereNull('parent_id')->where('status', 1)->firstOrFail();
        $sub      = Category::where('slug', $childSlug)->where('parent_id', $category->id)->where('status', 1)->firstOrFail();

        $quizzes = $this->publishedQuizzes()
            ->where('sub_category_id', $sub->id)
            ->latest('id')
            ->paginate(12);

        $questionTotal = BankQuestion::where('sub_category_id', $sub->id)->where('status', 1)->count();

        $siblings = Category::where('parent_id', $category->id)
            ->where('status', 1)
            ->where('id', '!=', $sub->id)
            ->orderBy('name')
            ->limit(12)
            ->get();

        $meta = $this->seoService->categoryMeta($sub, [
            'canonical'     => route('website.subcategory.show', [$category->slug, $sub->slug]),
            'quizCount'     => $quizzes->total(),
            'questionTotal' => $questionTotal,
            'parent'        => $category,
            'isSub'         => true,
            'image'         => $sub->image ? getImage(getFilePath('category') . '/' . $sub->image, getFileSize('category')) : null,
        ]);
        $seoContent = $this->seoService->categoryContent($sub, ['isSub' => true]);

        $seo = $this->seo(array_merge($meta, [
            'schema' => array_merge($meta['schema'], [
                $this->breadcrumbSchema([
                    'Home'          => route('home'),
                    'Categories'    => route('website.categories'),
                    $category->name => route('website.category.show', $category->slug),
                    $sub->name      => route('website.subcategory.show', [$category->slug, $sub->slug]),
                ]),
            ]),
        ]));

        return view('website.categories.subcategory', compact('seo', 'seoContent', 'category', 'sub', 'quizzes', 'questionTotal', 'siblings'));
    }
}
