<?php

namespace App\Http\Controllers\Website;

use App\Models\Category;
use Illuminate\Http\Request;

class SearchController extends BaseWebsiteController {
    public function index(Request $request) {
        $term = trim((string) $request->q);

        $quizzes    = collect();
        $categories = collect();

        if (mb_strlen($term) >= 2) {
            $quizzes = $this->publishedQuizzes()
                ->where(function ($q) use ($term) {
                    $q->where('title', 'like', "%{$term}%")
                      ->orWhere('description', 'like', "%{$term}%");
                })
                ->paginate(12)
                ->withQueryString();

            $categories = Category::where('status', 1)
                ->where('name', 'like', "%{$term}%")
                ->with('parent:id,slug,name')
                ->limit(12)
                ->get();
        }

        $seo = $this->seo([
            'title'       => $term !== '' ? "Search results for \"{$term}\"" : 'Search Quizzes',
            'description' => 'Search across every published quiz, category and exam topic on the platform.',
            'canonical'   => route('website.search'),
            // Search result pages should not be indexed.
            'robots'      => 'noindex, follow',
        ]);

        return view('website.search.index', compact('seo', 'term', 'quizzes', 'categories'));
    }

    /** AJAX typeahead for the header search box. */
    public function suggest(Request $request) {
        $term = trim((string) $request->q);

        if (mb_strlen($term) < 2) {
            return response()->json(['quizzes' => [], 'categories' => []]);
        }

        $quizzes = $this->publishedQuizzes()
            ->where('title', 'like', "%{$term}%")
            ->limit(6)
            ->get(['id', 'title', 'slug', 'category_id', 'difficulty', 'total_questions'])
            ->map(fn($q) => [
                'title'     => $q->title,
                'url'       => route('website.quiz.show', $q->slug),
                'category'  => $q->category?->name,
                'questions' => $q->total_questions,
            ]);

        $categories = Category::where('status', 1)
            ->where('name', 'like', "%{$term}%")
            ->with('parent:id,slug')
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'name' => $c->name,
                'url'  => $c->parent_id && $c->parent
                    ? route('website.subcategory.show', [$c->parent->slug, $c->slug])
                    : route('website.category.show', $c->slug),
            ]);

        return response()->json(['quizzes' => $quizzes, 'categories' => $categories]);
    }
}
