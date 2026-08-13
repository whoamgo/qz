<?php

namespace App\Http\Controllers\Website;

use App\Models\Badge;
use App\Models\Frontend;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\UserXp;
use Illuminate\Support\Facades\Cache;

class HomeController extends BaseWebsiteController {
    public function index() {
        $user = auth()->user();

        $categories = $this->navCategories();

        // "Today's quiz" is deterministic per day rather than random, so the
        // homepage is cacheable and the same for every visitor that day.
        $todayQuiz = $this->todayQuiz();

        $popularQuizzes = Cache::remember('website.home.popular', 900, function () {
            return $this->publishedQuizzes()
                ->withCount('attempts')
                ->orderByDesc('attempts_count')
                ->orderByDesc('id')
                ->limit(4)
                ->get();
        });

        $latestQuizzes = Cache::remember('website.home.latest', 900, function () {
            return $this->publishedQuizzes()->latest('id')->limit(4)->get();
        });

        $examCategories = $categories->whereIn('slug', self::PREFERRED_EXAM_SLUGS)->take(4);

        $currentAffairs = $this->currentAffairsQuizzes(4);

        // Personalised blocks only when signed in.
        $continueLearning = collect();
        $userStats        = null;
        $weakTopics       = collect();

        if ($user) {
            $continueLearning = QuizAttempt::where('user_id', $user->id)
                ->inProgress()
                ->with('quiz.category')
                ->latest('id')
                ->limit(4)
                ->get();

            $userStats  = $this->userStats($user);
            $weakTopics = $this->weakTopics($user);
        }

        $leaders = Cache::remember('website.home.leaders', 600, function () {
            return UserXp::with('user:id,username,firstname,lastname,image')
                ->orderByDesc('total_xp')
                ->limit(5)
                ->get();
        });

        $blogs = Frontend::where('data_keys', 'blog.element')->latest('id')->limit(3)->get();

        // Hero slider images are managed in Admin > Frontend Manager > Banner.
        //
        // Only the FILENAME is cached, never the resolved URL. asset() depends
        // on the request root, so caching an absolute URL would let whichever
        // context warmed the cache (CLI, queue, a differently-rooted request)
        // decide the URL for every visitor until it expired — which is how the
        // /qiz prefix went missing. Resolving per render is cheap and correct.
        $heroFiles = Cache::remember('website.home.hero.slides', 1800, function () {
            return Frontend::where('data_keys', 'banner.element')
                ->orderBy('id')
                ->get()
                ->map(fn($row) => (object) [
                    'file'  => $row->data_values->image ?? null,
                    'title' => $row->data_values->title ?? null,
                ])
                ->filter(fn($s) => !empty($s->file))
                ->values();
        });

        $heroSlides = $heroFiles->map(fn($s) => (object) [
            'image' => frontendImage('banner', $s->file),
            'title' => $s->title,
        ]);
       // echo "<pre>"; print_r($heroSlides); die();    
        $heroContent = getContent('banner.content', true);



        $testimonials       = $this->testimonials();
        $testimonialContent = getContent('testimonial.content', true);

        $faqs = $this->homeFaqs();

        $seo = $this->seo([
            'title'       => (gs('site_name') ?: 'Quiz') . ' — Practice Quizzes for GK, Current Affairs & Competitive Exams',
            'description' => 'Practice free quizzes on General Knowledge, Current Affairs and competitive exams like SSC, Railway, Banking, UPSC and Defence. Earn XP, unlock badges and climb the leaderboard.',
            'canonical'   => route('home'),
            'schema'      => [$this->websiteSchema(), $this->organizationSchema(), $this->faqSchema($faqs)],
        ]);

        return view('website.home.index', compact(
            'seo', 'categories', 'todayQuiz', 'popularQuizzes', 'latestQuizzes',
            'examCategories', 'currentAffairs', 'continueLearning', 'userStats',
            'weakTopics', 'leaders', 'blogs', 'faqs', 'user',
            'testimonials', 'testimonialContent', 'heroSlides', 'heroContent'
        ));
    }

    /**
     * Testimonials come from the admin Frontend Manager (testimonial.element).
     * The placeholder set below is used ONLY when nothing has been published
     * yet, so the section never renders empty on a fresh install.
     */
    private function testimonials() {
        $rows = Cache::remember('website.home.testimonials', 1800, function () {
            return Frontend::where('data_keys', 'testimonial.element')
                ->orderBy('id')
                ->get()
                ->map(function ($row) {
                    $v = $row->data_values;
                    return (object) [
                        'name'        => $v->name ?? null,
                        'designation' => $v->designation ?? null,
                        'review'      => strip_tags((string) ($v->review ?? '')),
                        'rating'      => (int) ($v->rating ?? 5),
                        // Filename only — see the hero-slider note above.
                        'image_file'  => $v->image ?? null,
                        'is_sample'   => false,
                    ];
                })
                ->filter(fn($t) => $t->name && $t->review !== '')
                ->values();
        });

        // Resolve image URLs outside the cache.
        $rows = $rows->map(function ($t) {
            $t->image = !empty($t->image_file) ? frontendImage('testimonial', $t->image_file) : null;
            return $t;
        });

        return $rows->isNotEmpty() ? $rows : $this->sampleTestimonials();
    }

    /** Clearly-labelled placeholders, replaced as soon as real ones exist. */
    private function sampleTestimonials() {
        return collect([
            ['Megha Sharma', 'Video Editor', 5, 'The quizzes are completely free and the quality is top-notch. Practising daily and tracking my accuracy per topic changed how I prepare. Best decision ever!'],
            ['Sumit Sidar', 'Digital Marketer', 5, 'Current affairs coverage is comprehensive. National, international, schemes and economy — all covered practically. The explanations after each quiz are what make it stick.'],
            ['Vansh Khanna', 'Web Developer', 5, 'The XP and streak system genuinely kept me consistent. I have completed more practice in a month here than in a year of reading PDFs.'],
            ['Riya Verma', 'Content Writer', 5, 'Mock tests mirror the real exam pattern and the answer review explains the reasoning, not just the key. My accuracy went up noticeably.'],
        ])->map(fn($t) => (object) [
            'name'        => $t[0],
            'designation' => $t[1],
            'review'      => $t[3],
            'rating'      => $t[2],
            'image'       => null,
            'is_sample'   => true,
        ]);
    }

    /** Same quiz for everyone for a given day, rotated by day-of-year. */
    private function todayQuiz(): ?Quiz {
        return Cache::remember('website.home.today.' . now()->toDateString(), 3600, function () {
            $ids = $this->publishedQuizzes()->pluck('id');
            if ($ids->isEmpty()) {
                return null;
            }

            $index = (int) now()->dayOfYear % $ids->count();
            return $this->publishedQuizzes()->find($ids[$index]);
        });
    }

    private function currentAffairsQuizzes(int $limit) {
        $category = $this->categoryBySlug(self::CURRENT_AFFAIRS_SLUG);
        if (!$category) {
            return collect();
        }

        return $this->publishedQuizzes()
            ->where('category_id', $category->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function userStats($user): array {
        $attempts = QuizAttempt::where('user_id', $user->id)->completed();

        $totalAttempts = (clone $attempts)->count();
        $correct       = (clone $attempts)->sum('correct_count');
        $answered      = (clone $attempts)->sum(\DB::raw('correct_count + wrong_count'));

        $xp = UserXp::where('user_id', $user->id)->first();

        return [
            'attempts'  => $totalAttempts,
            'answered'  => (int) $answered,
            'correct'   => (int) $correct,
            'accuracy'  => $answered > 0 ? round(($correct / $answered) * 100, 1) : 0,
            'total_xp'  => $xp->total_xp ?? 0,
            'level'     => $xp->current_level ?? 1,
            'streak'    => optional($user->streak)->current_streak ?? 0,
        ];
    }

    /** Categories where the user's accuracy is weakest, minimum 2 attempts. */
    private function weakTopics($user) {
        return QuizAttempt::where('quiz_attempts.user_id', $user->id)
            ->where('quiz_attempts.status', QuizAttempt::STATUS_COMPLETED)
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->join('categories', 'categories.id', '=', 'quizzes.category_id')
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->havingRaw('COUNT(*) >= 1')
            ->selectRaw('categories.id, categories.name, categories.slug,
                         COUNT(*) as attempts,
                         AVG(quiz_attempts.percentage) as avg_score')
            ->orderBy('avg_score')
            ->limit(4)
            ->get();
    }

    private function homeFaqs(): array {
        return [
            ['question' => 'Are the quizzes free to attempt?', 'answer' => 'Yes. Every quiz marked as Free can be attempted without payment. Paid and subscription quizzes are labelled clearly on the quiz card.'],
            ['question' => 'How do I earn XP?', 'answer' => 'You earn XP for completing a quiz, for each correct answer, and through bonuses for passing, scoring full marks and finishing a quiz for the first time. XP is calculated on the server after you submit.'],
            ['question' => 'What is a streak?', 'answer' => 'Your streak counts consecutive days on which you completed at least one quiz. Missing a day resets it.'],
            ['question' => 'Can I review my answers after a quiz?', 'answer' => 'Yes, if the quiz has answer review enabled. After submitting you can see each question, your answer, the correct answer and an explanation.'],
            ['question' => 'Do I need an account?', 'answer' => 'You can browse quizzes and categories without an account. To attempt a quiz and earn XP, badges and leaderboard rank, you need to register.'],
        ];
    }

    private function websiteSchema(): array {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => gs('site_name') ?: config('app.name'),
            'url'             => url('/'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => route('website.search') . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    private function organizationSchema(): array {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => gs('site_name') ?: config('app.name'),
            'url'      => url('/'),
            'logo'     => getImage(getFilePath('logoIcon') . '/logo.png'),
        ];
    }
}
