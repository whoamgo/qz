<?php

namespace App\Http\Controllers\Website;

use App\Models\BankQuestion;
use App\Models\Category;

/**
 * Exam-prep landing hubs (SSC / UPSC / Banking / Railway / Defence).
 *
 * Each hub CURATES real, already-published quizzes from the subject categories
 * the exam tests (config/exam_hubs.php) — it never creates new taxonomy or thin
 * pages. A hub that can't surface at least `min_quizzes` real quizzes is served
 * noindex so it can never dilute the index, and it is left out of the sitemap.
 */
class ExamHubController extends BaseWebsiteController {

    /** GET /exam-prep — directory of the exam hubs. */
    public function index() {
        $hubs = collect(config('exam_hubs.hubs', []))
            ->map(fn($h, $slug) => (object) [
                'slug'        => $slug,
                'name'        => $h['name'],
                'full_name'   => $h['full_name'],
                'description' => $h['description'],
            ])->values();

        $seo = $this->seo([
            'title'       => 'Exam Prep — SSC, UPSC, Banking, Railway & Defence Quizzes',
            'description' => 'Free curated quiz hubs for SSC, UPSC, Banking, Railway and Defence exams — practise the exact subjects each exam tests, with instant scoring and explanations.',
            'canonical'   => route('website.exam.hub.index'),
            'image'       => route('og.home'),
            'schema'      => [
                $this->breadcrumbSchema(['Home' => route('home'), 'Exam Prep' => route('website.exam.hub.index')]),
                $this->itemListSchema('Exam Prep Hubs',
                    $hubs->map(fn($h) => ['name' => $h->full_name, 'url' => route('website.exam.hub.show', $h->slug)])),
            ],
        ]);

        return view('website.exams.hub-index', compact('seo', 'hubs'));
    }

    /** GET /exam-prep/{exam} — one curated hub. */
    public function show($exam) {
        $cfg = config("exam_hubs.hubs.$exam");
        abort_unless($cfg, 404);

        // Map the configured subject slugs to real, existing parent categories.
        $catIds = Category::whereNull('parent_id')->where('status', 1)
            ->whereIn('slug', $cfg['categories'])
            ->pluck('id');

        $quizzes = $this->publishedQuizzes()
            ->whereIn('category_id', $catIds)
            ->withCount('attempts')
            ->orderByDesc('attempts_count')
            ->orderByDesc('id')
            ->paginate(18);

        $quizTotal     = $quizzes->total();
        $questionTotal = BankQuestion::where('status', 1)->whereIn('category_id', $catIds)->count();

        // Thin-page guard — noindex a hub that can't show enough real quizzes.
        $indexable = $quizTotal >= (int) config('exam_hubs.min_quizzes', 6);
        $robots    = $indexable ? 'index, follow' : 'noindex, follow';

        $faqs = [
            ['question' => "Are the {$cfg['name']} quizzes free?",
             'answer'   => 'Yes. Every quiz marked Free can be attempted without payment, with instant scoring and a written explanation after each question.'],
            ['question' => "Which subjects does this {$cfg['name']} hub cover?",
             'answer'   => "It curates published quizzes across the subjects {$cfg['full_name']} tests, drawn from QuizMitra's existing question bank."],
            ['question' => 'Do I earn XP here?',
             'answer'   => 'Yes. Completing any quiz awards XP toward your level, badges and leaderboard rank.'],
        ];

        $listItems = $quizzes->getCollection()->take(20)
            ->map(fn($q) => ['name' => $q->title, 'url' => route('website.quiz.show', $q->slug)]);

        $seo = $this->seo([
            'title'       => $cfg['title'],
            'description' => $cfg['description'],
            'canonical'   => route('website.exam.hub.show', $exam),
            'image'       => route('og.exam', $exam),
            'robots'      => $robots,
            'schema'      => array_values(array_filter([
                $indexable ? $this->itemListSchema($cfg['name'] . ' Exam Quizzes', $listItems) : null,
                $this->faqSchema($faqs),
                $this->breadcrumbSchema([
                    'Home'        => route('home'),
                    'Exam Prep'   => route('website.exam.hub.index'),
                    $cfg['name']  => route('website.exam.hub.show', $exam),
                ]),
            ])),
        ]);

        return view('website.exams.hub', [
            'seo'           => $seo,
            'exam'          => $cfg,
            'slug'          => $exam,
            'quizzes'       => $quizzes,
            'questionTotal' => $questionTotal,
            'quizTotal'     => $quizTotal,
            'faqs'          => $faqs,
            'indexable'     => $indexable,
        ]);
    }
}
