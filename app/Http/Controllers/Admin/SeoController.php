<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\Frontend;
use App\Models\Quiz;
use App\Models\QuizBankQuestion;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SEO Manager: an advisory, database-driven overview of SEO coverage across
 * categories, sub-categories, quizzes and blog posts, plus a bulk editor and a
 * "generate missing defaults" action. Everything here is advisory — it never
 * blocks or overwrites admin-entered SEO.
 */
class SeoController extends Controller {

    /* ============================================================ dashboard */

    public function dashboard() {
        $pageTitle = 'SEO Manager';

        // Which categories/sub-categories actually hold published quizzes.
        $quizByCat = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')
            ->whereNotNull('category_id')->groupBy('category_id')
            ->selectRaw('category_id, COUNT(*) c')->pluck('c', 'category_id');
        $quizBySub = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')
            ->whereNotNull('sub_category_id')->groupBy('sub_category_id')
            ->selectRaw('sub_category_id, COUNT(*) c')->pluck('c', 'sub_category_id');
        $taxonomyWithQuizzes = array_unique(array_merge($quizByCat->keys()->all(), $quizBySub->keys()->all()));

        $parentCount = Category::whereNull('parent_id')->count();
        $subCount    = Category::whereNotNull('parent_id')->count();
        $quizCount   = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')->count();
        $blogCount   = Frontend::where('data_keys', 'blog.element')->count();

        // Indexable pages (approx): active categories that are index-allowed and
        // either hold quizzes or carry SEO content, + indexable quizzes + blogs.
        $indexableCats = Category::where('status', 1)->where('robots_index', 1)
            ->where(function ($q) use ($taxonomyWithQuizzes) {
                $q->whereIn('id', $taxonomyWithQuizzes)
                  ->orWhere(fn($w) => $w->whereNotNull('seo_content')->where('seo_content', '!=', ''));
            })->count();
        $indexableQuizzes = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')->where('robots_index', 1)->count();
        $blogNoindex = Frontend::where('data_keys', 'blog.element')->where('seo_content', 'like', '%"meta_robots":"noindex%')->count();

        // "Relying on auto-generated" (blank custom field). Not broken — the
        // frontend fills these — but candidates for manual optimisation.
        $catAutoTitle = Category::whereNull('meta_title')->orWhere('meta_title', '')->count();
        $quizAutoTitle = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')
            ->where(fn($q) => $q->whereNull('meta_title')->orWhere('meta_title', ''))->count();
        $catAutoDesc = Category::whereNull('meta_description')->orWhere('meta_description', '')->count();
        $quizAutoDesc = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')
            ->where(fn($q) => $q->whereNull('meta_description')->orWhere('meta_description', ''))->count();

        // Noindex (admin-disabled indexing).
        $noindex = Category::where('robots_index', 0)->count()
            + Quiz::where('robots_index', 0)->count() + $blogNoindex;

        // Duplicate stored titles / descriptions (a genuine SEO problem).
        $dupTitles = $this->duplicates('meta_title');
        $dupDescs  = $this->duplicates('meta_description');

        $cards = [
            'indexable'    => $indexableCats + $indexableQuizzes + max(0, $blogCount - $blogNoindex),
            'parents'      => $parentCount,
            'subs'         => $subCount,
            'quizzes'      => $quizCount,
            'blogs'        => $blogCount,
            'auto_title'   => $catAutoTitle + $quizAutoTitle,
            'auto_desc'    => $catAutoDesc + $quizAutoDesc,
            'dup_titles'   => $dupTitles->count(),
            'dup_descs'    => $dupDescs->count(),
            'noindex'      => $noindex,
        ];

        return view('admin.seo.dashboard', compact('pageTitle', 'cards', 'dupTitles', 'dupDescs'));
    }

    /** Duplicate non-empty stored values of a column across categories + quizzes. */
    private function duplicates(string $column) {
        $cats = Category::whereNotNull($column)->where($column, '!=', '')
            ->groupBy($column)->havingRaw('COUNT(*) > 1')
            ->selectRaw("$column as val, COUNT(*) c")->pluck('c', 'val');
        $quizzes = Quiz::whereNotNull($column)->where($column, '!=', '')
            ->groupBy($column)->havingRaw('COUNT(*) > 1')
            ->selectRaw("$column as val, COUNT(*) c")->pluck('c', 'val');

        // Merge counts across both entity types.
        $merged = collect();
        foreach ([$cats, $quizzes] as $set) {
            foreach ($set as $val => $c) {
                $merged[$val] = ($merged[$val] ?? 0) + $c;
            }
        }
        return $merged->filter(fn($c) => $c > 1);
    }

    /* ====================================================== opportunities */

    /**
     * SEO Opportunity Dashboard (§26-28): actionable, data-driven lists derived
     * entirely from the existing taxonomy + quiz inventory. No page is created;
     * this only surfaces where real content exists but SEO work is missing.
     */
    public function opportunities() {
        $pageTitle = 'SEO Opportunities';

        // Published-with-questions quiz counts + question counts, grouped once.
        $quizByCat = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')->whereNotNull('category_id')
            ->groupBy('category_id')->selectRaw('category_id, COUNT(*) c')->pluck('c', 'category_id');
        $quizBySub = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')->whereNotNull('sub_category_id')
            ->groupBy('sub_category_id')->selectRaw('sub_category_id, COUNT(*) c')->pluck('c', 'sub_category_id');
        $qByCat = BankQuestion::where('status', 1)->whereNotNull('category_id')->groupBy('category_id')->selectRaw('category_id, COUNT(*) c')->pluck('c', 'category_id');
        $qBySub = BankQuestion::where('status', 1)->whereNotNull('sub_category_id')->groupBy('sub_category_id')->selectRaw('sub_category_id, COUNT(*) c')->pluck('c', 'sub_category_id');

        $cats = Category::get([
            'id', 'parent_id', 'name', 'slug', 'status', 'seo_content',
            'meta_title', 'primary_keyword', 'robots_index',
        ]);
        $slugById   = $cats->pluck('slug', 'id');
        $statusById = $cats->pluck('status', 'id');

        $missingContent = collect();
        $missingKeyword = collect();
        $thin           = collect();
        $orphans        = collect();

        foreach ($cats as $c) {
            $isSub     = (bool) $c->parent_id;
            $quizzes   = (int) ($isSub ? ($quizBySub[$c->id] ?? 0) : ($quizByCat[$c->id] ?? 0));
            $questions = (int) ($isSub ? ($qBySub[$c->id] ?? 0) : ($qByCat[$c->id] ?? 0));

            $url = $isSub && isset($slugById[$c->parent_id])
                ? route('website.subcategory.show', [$slugById[$c->parent_id], $c->slug])
                : route('website.category.show', $c->slug);

            $row = (object) [
                'id' => $c->id, 'name' => $c->name, 'is_sub' => $isSub, 'url' => $url,
                'quizzes' => $quizzes, 'questions' => $questions, 'edit' => route('admin.category.seo', $c->id),
            ];

            $hasQuizzes = $quizzes > 0;
            $hasContent = filled($c->seo_content);

            // Missing content: real inventory but no rich SEO content — biggest win.
            if ($hasQuizzes && !$hasContent) {
                $missingContent->push($row);
            }
            // Missing keyword mapping: real inventory but no primary keyword set.
            if ($hasQuizzes && blank($c->primary_keyword)) {
                $missingKeyword->push($row);
            }
            // Thin: active in nav but no quizzes and no content (currently noindexed).
            if ($c->status == 1 && !$hasQuizzes && !$hasContent) {
                $thin->push($row);
            }
            // Orphan: active sub-category with quizzes but its parent is inactive,
            // so it is unreachable from the normal category navigation.
            if ($isSub && $c->status == 1 && $hasQuizzes && ($statusById[$c->parent_id] ?? 0) != 1) {
                $orphans->push($row);
            }
        }

        $byQuiz = fn($col) => $col->sortByDesc('quizzes')->values();
        $missingContent = $byQuiz($missingContent);
        $missingKeyword = $byQuiz($missingKeyword);

        // Keyword cannibalisation: same primary_keyword on more than one entity.
        $kwCats = Category::whereNotNull('primary_keyword')->where('primary_keyword', '!=', '')
            ->groupBy('primary_keyword')->havingRaw('COUNT(*) > 1')->selectRaw('primary_keyword k, COUNT(*) c')->pluck('c', 'k');
        $kwQuiz = Quiz::whereNotNull('primary_keyword')->where('primary_keyword', '!=', '')
            ->groupBy('primary_keyword')->havingRaw('COUNT(*) > 1')->selectRaw('primary_keyword k, COUNT(*) c')->pluck('c', 'k');
        $kwCannibal = collect();
        foreach ([$kwCats, $kwQuiz] as $set) {
            foreach ($set as $k => $n) { $kwCannibal[$k] = ($kwCannibal[$k] ?? 0) + $n; }
        }
        $kwCannibal = $kwCannibal->filter(fn($n) => $n > 1);

        // Exam/category dual-URL pairs (§6): every top-level active category is
        // reachable at BOTH /category/{slug} and /exams/{slug}. Recommend one
        // canonical (admin can set canonical_url to consolidate).
        $dualUrls = Category::whereNull('parent_id')->where('status', 1)->orderBy('name')
            ->get(['id', 'name', 'slug', 'canonical_url'])
            ->map(fn($c) => (object) [
                'name' => $c->name,
                'category_url' => route('website.category.show', $c->slug),
                'exam_url'     => route('website.exam.show', $c->slug),
                'has_canonical' => filled($c->canonical_url),
                'edit' => route('admin.category.seo', $c->id),
            ]);

        $summary = [
            'missing_content' => $missingContent->count(),
            'missing_keyword' => $missingKeyword->count(),
            'thin'            => $thin->count(),
            'orphans'         => $orphans->count(),
            'dup_keywords'    => $kwCannibal->count(),
            'dual_urls'       => $dualUrls->count(),
        ];

        return view('admin.seo.opportunities', [
            'pageTitle'      => $pageTitle,
            'summary'        => $summary,
            'missingContent' => $missingContent->take(50),
            'missingKeyword' => $missingKeyword->take(50),
            'thin'           => $thin->take(50),
            'orphans'        => $orphans,
            'kwCannibal'     => $kwCannibal,
            'dualUrls'       => $dualUrls,
        ]);
    }

    /* ============================================================= bulk view */

    public function bulk(Request $request) {
        $pageTitle = 'Bulk SEO Editor';
        $type   = in_array($request->get('type'), ['category', 'subcategory', 'quiz', 'blog'], true) ? $request->get('type') : 'category';
        $search = trim((string) $request->get('search'));
        $missing = $request->boolean('missing');

        $rows = match ($type) {
            'quiz'        => $this->quizRows($search, $missing),
            'blog'        => $this->blogRows($search, $missing),
            'subcategory' => $this->categoryRows($search, $missing, true),
            default       => $this->categoryRows($search, $missing, false),
        };

        return view('admin.seo.bulk', compact('pageTitle', 'type', 'search', 'missing', 'rows'));
    }

    private function categoryRows(string $search, bool $missing, bool $sub) {
        return Category::query()
            ->when($sub, fn($q) => $q->whereNotNull('parent_id'), fn($q) => $q->whereNull('parent_id'))
            ->when($search !== '', fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($missing, fn($q) => $q->where(fn($w) => $w->whereNull('meta_title')->orWhere('meta_title', '')
                ->orWhereNull('meta_description')->orWhere('meta_description', '')))
            ->with('parent')
            ->orderBy('name')
            ->paginate(getPaginate())->withQueryString();
    }

    private function quizRows(string $search, bool $missing) {
        return Quiz::query()
            ->when($search !== '', fn($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($missing, fn($q) => $q->where(fn($w) => $w->whereNull('meta_title')->orWhere('meta_title', '')
                ->orWhereNull('meta_description')->orWhere('meta_description', '')))
            ->latest('id')
            ->paginate(getPaginate())->withQueryString();
    }

    private function blogRows(string $search, bool $missing) {
        return Frontend::where('data_keys', 'blog.element')
            ->when($search !== '', fn($q) => $q->where('data_values', 'like', "%{$search}%"))
            ->when($missing, fn($q) => $q->where(fn($w) => $w->whereNull('seo_content')->orWhere('seo_content', 'not like', '%"description"%')))
            ->latest('id')
            ->paginate(getPaginate())->withQueryString();
    }

    /* =========================================================== bulk generate */

    public function generate(Request $request, SeoService $seo) {
        $request->validate(['type' => 'required|in:category,quiz']);
        $updated = 0;

        if ($request->type === 'category') {
            $quizByCat = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')->whereNotNull('category_id')
                ->groupBy('category_id')->selectRaw('category_id, COUNT(*) c')->pluck('c', 'category_id');
            $quizBySub = Quiz::where('status', Quiz::STATUS_PUBLISHED)->has('questions')->whereNotNull('sub_category_id')
                ->groupBy('sub_category_id')->selectRaw('sub_category_id, COUNT(*) c')->pluck('c', 'sub_category_id');
            $qByCat = BankQuestion::where('status', 1)->whereNotNull('category_id')->groupBy('category_id')->selectRaw('category_id, COUNT(*) c')->pluck('c', 'category_id');
            $qBySub = BankQuestion::where('status', 1)->whereNotNull('sub_category_id')->groupBy('sub_category_id')->selectRaw('sub_category_id, COUNT(*) c')->pluck('c', 'sub_category_id');

            Category::with('parent')->chunkById(200, function ($cats) use ($seo, &$updated, $quizByCat, $quizBySub, $qByCat, $qBySub) {
                foreach ($cats as $c) {
                    $quizCount = (int) ($c->parent_id ? ($quizBySub[$c->id] ?? 0) : ($quizByCat[$c->id] ?? 0));
                    $qTotal    = (int) ($c->parent_id ? ($qBySub[$c->id] ?? 0) : ($qByCat[$c->id] ?? 0));
                    if ($seo->fillCategoryDefaults($c, $quizCount, $qTotal)) {
                        $c->save();
                        $updated++;
                    }
                }
            });
        } else {
            $counts = QuizBankQuestion::groupBy('quiz_id')->selectRaw('quiz_id, COUNT(*) c')->pluck('c', 'quiz_id');
            Quiz::chunkById(200, function ($quizzes) use ($seo, &$updated, $counts) {
                foreach ($quizzes as $q) {
                    if ($seo->fillQuizDefaults($q, (int) ($counts[$q->id] ?? 0))) {
                        $q->save();
                        $updated++;
                    }
                }
            });
        }

        $notify[] = ['success', "Generated missing SEO defaults for {$updated} {$request->type}(s). Existing values were not changed."];
        return back()->withNotify($notify);
    }
}
