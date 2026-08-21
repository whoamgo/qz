<?php

namespace App\Http\Controllers\Website;

use App\Models\BankQuestion;
use App\Models\Category;
use App\Services\SeoService;

/**
 * "Exams" on the public site are the exam-oriented parent categories
 * (SSC, Railway, Banking, UPSC, Defence, State PSC, Teaching, School).
 * This is separate from the legacy Exam Manager module.
 */
class ExamController extends BaseWebsiteController {
    public function __construct(private SeoService $seoService) {}
    public function index() {
        $exams = $this->examCategoryQuery()
            ->withCount(['children as sub_count' => fn($q) => $q->where('status', 1)])
            ->orderBy('name')
            ->get();

        $quizCounts = $this->publishedQuizzes()
            ->whereIn('category_id', $exams->pluck('id'))
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $questionCounts = BankQuestion::where('status', 1)
            ->whereIn('category_id', $exams->pluck('id'))
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $seo = $this->seo([
            'title'       => 'Competitive Exam Preparation — SSC, Railway, Banking, UPSC & Defence',
            'description' => 'Prepare for SSC, Railway, Banking, UPSC, Defence, State PSC and Teaching exams with topic-wise quizzes, mock tests and previous year questions.',
            'canonical'   => route('exams'),
            'schema'      => [$this->breadcrumbSchema([
                'Home'  => route('home'),
                'Exams' => route('exams'),
            ])],
        ]);

        return view('website.exams.index', compact('seo', 'exams', 'quizCounts', 'questionCounts'));
    }

    public function show($slug) {
        // Any live top-level category is a valid exam destination; the old
        // hard-coded list 404'd after categories were reorganised.
        abort_unless($this->isExamCategory($slug), 404);

        $exam = Category::where('slug', $slug)->whereNull('parent_id')->where('status', 1)->firstOrFail();

        $subjects = Category::where('parent_id', $exam->id)
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        $popularQuizzes = $this->publishedQuizzes()
            ->where('category_id', $exam->id)
            ->withCount('attempts')
            ->orderByDesc('attempts_count')
            ->limit(6)
            ->get();

        $latestQuizzes = $this->publishedQuizzes()
            ->where('category_id', $exam->id)
            ->latest('id')
            ->limit(6)
            ->get();

        // Mock tests and PYQs live in their own parent categories; surface the
        // sub-topics whose names match this exam.
        $mockTests = $this->relatedIn(self::MOCK_TEST_SLUG, $exam->name);
        $pyqs      = $this->relatedIn(self::PYQ_SLUG, $exam->name);

        $currentAffairs = $this->publishedQuizzes()
            ->whereHas('category', fn($q) => $q->where('slug', self::CURRENT_AFFAIRS_SLUG))
            ->latest('id')
            ->limit(4)
            ->get();

        $questionTotal = BankQuestion::where('category_id', $exam->id)->where('status', 1)->count();

        $faqs = [
            ['question' => "How do I prepare for {$exam->name} here?", 'answer' => "Work through the {$exam->name} quizzes below, then attempt mock tests and previous year questions to check your readiness."],
            ['question' => "How many {$exam->name} questions are available?", 'answer' => "There are {$questionTotal} practice questions in this section."],
            ['question' => 'Are mock tests timed?', 'answer' => 'Each quiz shows its own time limit on the quiz card and detail page. Timed quizzes submit automatically when the clock runs out.'],
        ];

        // Exam pages ARE top-level categories, so admin-managed category SEO
        // (Phase 1) applies here too. A curated exam-specific title/description
        // is used as the fallback when the admin has left those blank.
        $quizCount = $this->publishedQuizzes()->where('category_id', $exam->id)->count();
        $meta = $this->seoService->categoryMeta($exam, [
            'canonical'     => route('website.exam.show', $exam->slug),
            'quizCount'     => $quizCount,
            'questionTotal' => $questionTotal,
            'parent'        => null,
            'isSub'         => false,
            'image'         => $exam->image ? getImage(getFilePath('category') . '/' . $exam->image, getFileSize('category')) : null,
        ]);
        if (blank($exam->meta_title)) {
            $meta['title'] = $meta['og_title'] = $meta['twitter_title'] = $exam->name . ' Preparation — Quizzes, Mock Tests & Previous Year Questions';
        }
        if (blank($exam->meta_description)) {
            $meta['description'] = $meta['og_description'] = $meta['twitter_description'] = "Free {$exam->name} preparation with {$questionTotal} practice questions, plus mock tests and previous year papers.";
        }
        $seoContent = $this->seoService->categoryContent($exam, ['isSub' => false]);
        // Preserve the exam-specific H1 wording as the fallback (admin seo_h1 wins).
        if (blank($exam->seo_h1)) {
            $seoContent['h1'] = $exam->name . ' Preparation';
        }

        $seo = $this->seo(array_merge($meta, [
            'schema' => array_merge($meta['schema'], [
                $this->faqSchema($faqs),
                $this->breadcrumbSchema([
                    'Home'      => route('home'),
                    'Exams'     => route('exams'),
                    $exam->name => route('website.exam.show', $exam->slug),
                ]),
            ]),
        ]));

        return view('website.exams.show', compact(
            'seo', 'seoContent', 'exam', 'subjects', 'popularQuizzes', 'latestQuizzes',
            'mockTests', 'pyqs', 'currentAffairs', 'questionTotal', 'faqs'
        ));
    }

    public function mockTests() {
        return $this->hubPage(
            self::MOCK_TEST_SLUG,
            'Mock Tests — Full-Length Practice Tests for Every Exam',
            'Attempt full-length mock tests for SSC, Railway, Banking, UPSC, Defence, State PSC, Teaching and School exams with instant scoring.',
            route('website.mock.tests'),
            'Mock Tests',
            'website.mock-tests.index'
        );
    }

    public function pyq() {
        return $this->hubPage(
            self::PYQ_SLUG,
            'Previous Year Questions — Solved Papers by Exam',
            'Practice previous year questions from SSC, Railway, Banking, UPSC, Defence, State PSC, Teaching and School examinations with explanations.',
            route('website.pyq'),
            'Previous Year Questions',
            'website.pyq.index'
        );
    }

    /** Shared renderer for the Mock Test and PYQ hub pages. */
    private function hubPage(string $slug, string $title, string $description, string $canonical, string $label, string $view) {
        $category = $this->categoryBySlug($slug);

        $groups  = collect();
        $quizzes = $this->publishedQuizzes()->whereRaw('1 = 0')->paginate(12);

        if ($category) {
            $groups = Category::where('parent_id', $category->id)
                ->where('status', 1)
                ->orderBy('name')
                ->get();

            $quizzes = $this->publishedQuizzes()
                ->where('category_id', $category->id)
                ->latest('id')
                ->paginate(12);
        }

        $seo = $this->seo([
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'schema'      => [$this->breadcrumbSchema([
                'Home' => route('home'),
                $label => $canonical,
            ])],
        ]);

        return view($view, compact('seo', 'category', 'groups', 'quizzes', 'label'));
    }

    /** Sub-categories of a hub whose name matches the given exam. */
    private function relatedIn(string $hubSlug, string $examName) {
        $hub = $this->categoryBySlug($hubSlug);
        if (!$hub) {
            return collect();
        }

        $keyword = trim(str_replace(['Exams', 'Quiz'], '', $examName));

        return Category::where('parent_id', $hub->id)
            ->where('status', 1)
            ->where('name', 'like', "%{$keyword}%")
            ->orderBy('name')
            ->limit(6)
            ->get();
    }
}
