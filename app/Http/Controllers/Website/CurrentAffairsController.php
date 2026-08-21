<?php

namespace App\Http\Controllers\Website;

use App\Models\BankQuestion;
use App\Models\Category;
use App\Services\SeoService;

/**
 * Current Affairs is the "current-affairs" parent category and its
 * sub-categories (National Affairs, International Affairs, Government Schemes,
 * Appointments, Awards, Sports, Science & Technology, Economy, Defence,
 * Important Days ...). Content is quiz-based; there is no separate article
 * store in this installation.
 */
class CurrentAffairsController extends BaseWebsiteController {
    public function __construct(private SeoService $seoService) {}

    /** Sub-category slugs treated as the day/week/month digests. */
    const PERIOD_SLUGS = [
        'today'   => ['today-current-affairs-quiz', 'daily-current-affairs'],
        'weekly'  => ['weekly-current-affairs'],
        'monthly' => ['monthly-current-affairs'],
    ];

    public function index() {
        $category = $this->requireCategory();

        $topics = Category::where('parent_id', $category->id)
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        $quizCounts = $this->publishedQuizzes()
            ->whereIn('sub_category_id', $topics->pluck('id'))
            ->selectRaw('sub_category_id, COUNT(*) as total')
            ->groupBy('sub_category_id')
            ->pluck('total', 'sub_category_id');

        $questionCounts = BankQuestion::where('status', 1)
            ->whereIn('sub_category_id', $topics->pluck('id'))
            ->selectRaw('sub_category_id, COUNT(*) as total')
            ->groupBy('sub_category_id')
            ->pluck('total', 'sub_category_id');

        $latest = $this->publishedQuizzes()
            ->where('category_id', $category->id)
            ->latest('id')
            ->paginate(12);

        $faqs = [
            ['question' => 'How often is Current Affairs content updated?', 'answer' => 'New quizzes are published to the daily, weekly and monthly sections as they are added by the editorial team.'],
            ['question' => 'Which topics are covered?', 'answer' => 'National and international affairs, government schemes, appointments, awards, sports, science and technology, economy, defence and important days.'],
            ['question' => 'Are Current Affairs quizzes useful for competitive exams?', 'answer' => 'Yes. Current affairs carries significant weight in SSC, Banking, Railway, UPSC, Defence and State PSC examinations.'],
        ];

        // The Current Affairs hub is the "current-affairs" category, so its
        // admin-managed SEO (Phase 1) applies here, with curated fallbacks.
        $meta = $this->seoService->categoryMeta($category, [
            'canonical'     => route('website.current.affairs.index'),
            'quizCount'     => $latest->total(),
            'questionTotal' => (int) $questionCounts->sum(),
            'parent'        => null,
            'isSub'         => false,
            'image'         => $category->image ? getImage(getFilePath('category') . '/' . $category->image, getFileSize('category')) : null,
        ]);
        if (blank($category->meta_title)) {
            $meta['title'] = $meta['og_title'] = $meta['twitter_title'] = 'Current Affairs Quiz — Daily, Weekly and Monthly GK Updates';
        }
        if (blank($category->meta_description)) {
            $meta['description'] = $meta['og_description'] = $meta['twitter_description'] = 'Practice daily, weekly and monthly current affairs quizzes covering national and international news, government schemes, appointments, awards, sports and economy.';
        }
        $seoContent = $this->seoService->categoryContent($category, ['isSub' => false]);
        if (blank($category->seo_h1)) {
            $seoContent['h1'] = 'Current Affairs';
        }

        $seo = $this->seo(array_merge($meta, [
            'schema' => array_merge($meta['schema'], [
                $this->faqSchema($faqs),
                $this->breadcrumbSchema([
                    'Home'            => route('home'),
                    'Current Affairs' => route('website.current.affairs.index'),
                ]),
            ]),
        ]));

        return view('website.current-affairs.index', compact('seo', 'seoContent', 'category', 'topics', 'quizCounts', 'questionCounts', 'latest', 'faqs'));
    }

    public function today() {
        // Dynamic dated SEO (auto-updates every day) — builds a fresh, unique
        // title/description without editing anything (spec §39).
        $date = now()->format('d M Y');
        return $this->period(
            'today',
            "Daily Current Affairs Quiz – {$date} | Today's GK Questions",
            "Attempt the current affairs quiz for {$date} with the latest national and international news questions, updated daily with detailed explanations.",
            route('website.current.affairs.today'),
            "Today's Current Affairs",
            'website.current-affairs.today'
        );
    }

    public function weekly() {
        return $this->period(
            'weekly',
            'Weekly Current Affairs Quiz — This Week in News',
            'Revise the week\'s most important current affairs with a consolidated weekly quiz covering national, international, economy and sports news.',
            route('website.current.affairs.weekly'),
            'Weekly Current Affairs',
            'website.current-affairs.weekly'
        );
    }

    public function monthly() {
        return $this->period(
            'monthly',
            'Monthly Current Affairs Quiz — Full Month Revision',
            'Cover an entire month of current affairs in one revision quiz, ideal for SSC, Banking, Railway, UPSC and State PSC preparation.',
            route('website.current.affairs.monthly'),
            'Monthly Current Affairs',
            'website.current-affairs.monthly'
        );
    }

    private function period(string $key, string $title, string $description, string $canonical, string $label, string $view) {
        $category = $this->requireCategory();

        $subs = Category::where('parent_id', $category->id)
            ->where('status', 1)
            ->whereIn('slug', self::PERIOD_SLUGS[$key])
            ->get();

        $quizzes = $subs->isEmpty()
            ? $this->publishedQuizzes()->whereRaw('1 = 0')->paginate(12)
            : $this->publishedQuizzes()->whereIn('sub_category_id', $subs->pluck('id'))->latest('id')->paginate(12);

        $otherTopics = Category::where('parent_id', $category->id)
            ->where('status', 1)
            ->whereNotIn('slug', self::PERIOD_SLUGS[$key])
            ->orderBy('name')
            ->limit(10)
            ->get();

        $seo = $this->seo([
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'schema'      => [$this->breadcrumbSchema([
                'Home'            => route('home'),
                'Current Affairs' => route('website.current.affairs.index'),
                $label            => $canonical,
            ])],
        ]);

        return view($view, compact('seo', 'category', 'quizzes', 'otherTopics', 'label'));
    }

    private function requireCategory(): Category {
        $category = $this->categoryBySlug(self::CURRENT_AFFAIRS_SLUG);
        abort_if(!$category, 404, 'Current Affairs category is not configured.');
        return $category;
    }
}
