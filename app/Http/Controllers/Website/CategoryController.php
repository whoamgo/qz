<?php

namespace App\Http\Controllers\Website;

use App\Models\BankQuestion;
use App\Models\Category;

class CategoryController extends BaseWebsiteController {
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
            ['question' => "What does the {$category->name} category cover?", 'answer' => "It brings together {$questionTotal} practice questions across every published {$category->name} quiz."],
            ['question' => 'Are these quizzes free?', 'answer' => 'Quizzes marked Free can be attempted without payment. Any paid or subscription quiz is labelled on its card.'],
            ['question' => 'Do I earn XP in this category?', 'answer' => 'Yes. Completing any quiz awards XP, which counts towards your level, badges and leaderboard rank.'],
        ];

        $seo = $this->seo([
            'title'       => $category->name . ' Quizzes — Practice Questions and Mock Tests',
            'description' => "Practice {$category->name} quizzes with {$questionTotal} questions. Free online practice with instant results, explanations and XP rewards.",
            'canonical'   => route('website.category.show', $category->slug),
            'schema'      => [
                $this->faqSchema($faqs),
                $this->breadcrumbSchema([
                    'Home'          => route('home'),
                    'Categories'    => route('website.categories'),
                    $category->name => route('website.category.show', $category->slug),
                ]),
            ],
        ]);

        return view('website.categories.show', compact(
            'seo', 'category', 'subCategories', 'subQuizCounts',
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

        $seo = $this->seo([
            'title'       => $sub->name . ' Quiz — ' . $category->name . ' Practice Questions',
            'description' => "Practice {$sub->name} questions from the {$category->name} category. {$questionTotal} questions available with explanations, instant scoring and XP rewards.",
            // Kept reachable for any existing link, but excluded from the index:
            // the public structure is Category -> Quiz, so the parent category
            // is the canonical destination for this content.
            'canonical'   => route('website.category.show', $category->slug),
            'robots'      => 'noindex, follow',
            'schema'      => [$this->breadcrumbSchema([
                'Home'          => route('home'),
                'Categories'    => route('website.categories'),
                $category->name => route('website.category.show', $category->slug),
                $sub->name      => route('website.subcategory.show', [$category->slug, $sub->slug]),
            ])],
        ]);

        return view('website.categories.subcategory', compact('seo', 'category', 'sub', 'quizzes', 'questionTotal', 'siblings'));
    }
}
