<?php

namespace App\Http\Controllers\Website;

use App\Models\Bookmark;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use App\Services\QuizSampleService;
use Illuminate\Http\Request;

class QuizController extends BaseWebsiteController {
    public function __construct(
        private QuizAttemptService $attempts,
        private QuizSampleService $samples,
    ) {}

    // --------------------------------------------------------------- listing

    public function index(Request $request) {
        $query = $this->publishedQuizzes()->withCount('attempts');

        if ($search = trim((string) $request->q)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->category) {
            $category = $this->categoryBySlug($request->category);
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        if ($request->sub_category) {
            $sub = $this->categoryBySlug($request->sub_category);
            if ($sub) {
                $query->where('sub_category_id', $sub->id);
            }
        }

        if (in_array($request->difficulty, ['easy', 'medium', 'hard'], true)) {
            $query->where('difficulty', $request->difficulty);
        }

        if (in_array($request->type, ['free', 'paid', 'subscription'], true)) {
            $query->where('quiz_type', $request->type);
        }

        match ($request->sort) {
            'oldest'     => $query->oldest('id'),
            'popular'    => $query->orderByDesc('attempts_count'),
            'questions'  => $query->orderByDesc('total_questions'),
            'title'      => $query->orderBy('title'),
            default      => $query->latest('id'),
        };

        $quizzes    = $query->paginate(12)->withQueryString();
        $categories = $this->navCategories();

        $seo = $this->seo([
            'title'       => 'All Quizzes — Practice by Category, Exam and Difficulty',
            'description' => 'Browse and filter every published quiz by category, difficulty and type. Start practising instantly and earn XP for each quiz you complete.',
            'canonical'   => route('website.quizzes'),
            'schema'      => [$this->breadcrumbSchema([
                'Home'    => route('home'),
                'Quizzes' => route('website.quizzes'),
            ])],
        ]);

        return view('website.quizzes.index', compact('seo', 'quizzes', 'categories'));
    }

    // ---------------------------------------------------------------- detail

    public function show($slug) {
        $quiz = $this->publishedQuizzes()->where('slug', $slug)->firstOrFail();
        $quiz->loadCount('questions');

        $related = $this->publishedQuizzes()
            ->where('id', '!=', $quiz->id)
            ->where(function ($q) use ($quiz) {
                $q->where('sub_category_id', $quiz->sub_category_id)
                  ->orWhere('category_id', $quiz->category_id);
            })
            ->limit(4)
            ->get();

        $user = auth()->user();

        $openAttempt = $user
            ? QuizAttempt::where('user_id', $user->id)->where('quiz_id', $quiz->id)->inProgress()->latest('id')->first()
            : null;

        $lastAttempt = $user
            ? QuizAttempt::where('user_id', $user->id)->where('quiz_id', $quiz->id)->completed()->latest('id')->first()
            : null;

        $bookmarked = $user
            ? Bookmark::where('user_id', $user->id)->where('quiz_id', $quiz->id)->exists()
            : false;

        $faqs = $this->quizFaqs($quiz);

        // A small, stable set of real questions (with answers + explanations)
        // rendered server-side so Google can crawl the quiz content without
        // login. This is display-only — it never starts a scored attempt.
        $sampleQuestions = $this->samples->forQuiz($quiz);

        $seo = $this->seo([
            'title'       => $quiz->title . ' — ' . ($quiz->category?->name ?? 'Quiz') . ' Practice Test',
            'description' => $quiz->description
                ?: "Attempt the {$quiz->title} quiz with " . $quiz->effectiveQuestionCount($quiz->questions_count) . " questions in {$quiz->time_limit} minutes. Difficulty: " . ucfirst($quiz->difficulty) . '.',
            'canonical'   => route('website.quiz.show', $quiz->slug),
            'type'        => 'article',
            // Branded, auto-generated OG card (quiz title + category) for social shares.
            'image'       => route('og.quiz', $quiz->slug),
            'schema'      => array_values(array_filter([
                // Quiz schema carries the same sample questions we render, so the
                // structured data matches the visible page exactly.
                $this->quizSchema($quiz, $sampleQuestions),
                $this->faqSchema($faqs),
                $this->breadcrumbSchema(array_filter([
                    'Home'    => route('home'),
                    'Quizzes' => route('website.quizzes'),
                    $quiz->category?->name => $quiz->category ? route('website.category.show', $quiz->category->slug) : null,
                    $quiz->title => route('website.quiz.show', $quiz->slug),
                ])),
            ])),
        ]);

        return view('website.quizzes.show', compact('seo', 'quiz', 'related', 'openAttempt', 'lastAttempt', 'bookmarked', 'faqs', 'sampleQuestions'));
    }

    // -------------------------------------------------------------- attempt

    public function start(Request $request, $slug) {
        $quiz = $this->publishedQuizzes()->where('slug', $slug)->firstOrFail();

        if ($quiz->questions()->count() === 0) {
            return back()->with('error', 'This quiz has no questions yet.');
        }

        $attempt = $this->attempts->startOrResume(auth()->user(), $quiz);

        return redirect()->route('website.quiz.attempt', $attempt->id);
    }

    public function attempt($attemptId) {
        $attempt = $this->ownedAttempt($attemptId);

        if ($attempt->isCompleted()) {
            return redirect()->route('website.quiz.result', $attempt->id);
        }

        $quiz      = $attempt->quiz;
        $questions = $this->attempts->questionsForAttempt($attempt);
        $remaining = $attempt->remainingSeconds();
        // Effective clock: a room may have set a per-attempt limit.
        $timeLimit = (int) ($attempt->time_limit ?? $quiz->time_limit);

        // A timed attempt whose clock already expired is submitted immediately
        // rather than letting the user keep answering.
        if ($remaining !== null && $remaining <= 0) {
            $this->attempts->submit($attempt);
            return redirect()->route('website.quiz.result', $attempt->id)
                ->with('info', 'Time expired, your attempt was submitted automatically.');
        }

        $seo = $this->seo([
            'title'  => 'Attempting: ' . $quiz->title,
            'robots' => 'noindex, nofollow',
        ]);

        return view('website.quizzes.attempt', compact('seo', 'attempt', 'quiz', 'questions', 'remaining', 'timeLimit'));
    }

    /** AJAX: persists one selection. Never returns whether it was correct. */
    public function saveAnswer(Request $request, $attemptId) {
        $request->validate([
            'question_id' => 'required|integer',
            'option_id'   => 'nullable|integer',
            'marked'      => 'nullable|boolean',
        ]);

        $attempt = $this->ownedAttempt($attemptId);

        if ($attempt->isCompleted()) {
            return response()->json(['success' => false, 'message' => 'This attempt is already submitted.'], 422);
        }

        $ok = $this->attempts->saveAnswer(
            $attempt,
            (int) $request->question_id,
            $request->option_id !== null ? (int) $request->option_id : null,
            $request->boolean('marked')
        );

        return response()->json([
            'success'   => $ok,
            'answered'  => $attempt->answeredCount(),
            'remaining' => $attempt->remainingSeconds(),
        ], $ok ? 200 : 422);
    }

    public function markReview(Request $request, $attemptId) {
        $request->validate(['question_id' => 'required|integer', 'marked' => 'required|boolean']);

        $attempt = $this->ownedAttempt($attemptId);
        $this->attempts->toggleReview($attempt, (int) $request->question_id, $request->boolean('marked'));

        return response()->json(['success' => true]);
    }

    public function submit(Request $request, $attemptId) {
        $attempt = $this->ownedAttempt($attemptId);

        if ($attempt->isCompleted()) {
            return redirect()->route('website.quiz.result', $attempt->id);
        }

        // Client-reported elapsed time is clamped to the server-side window so
        // it cannot be used to fake a fast finish.
        $elapsed = $attempt->started_at ? now()->diffInSeconds($attempt->started_at) : 0;
        $claimed = (int) $request->input('time_taken', $elapsed);
        $timeTaken = max(0, min($claimed, $elapsed));

        $this->attempts->submit($attempt, $timeTaken);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => route('website.quiz.result', $attempt->id)]);
        }

        return redirect()->route('website.quiz.result', $attempt->id);
    }

    // --------------------------------------------------------------- result

    public function result($attemptId) {
        $attempt = $this->ownedAttempt($attemptId);

        if (!$attempt->isCompleted()) {
            return redirect()->route('website.quiz.attempt', $attempt->id);
        }

        $quiz = $attempt->quiz;
        $user = auth()->user();
        $xp   = $user->xpProfile;

        $nextLevel = $xp
            ? \App\Models\Level::where('required_xp', '>', $xp->total_xp)->orderBy('required_xp')->first()
            : null;

        $recentBadges = $user->badges()->orderByDesc('user_badges.created_at')->limit(4)->get();

        $related = $this->publishedQuizzes()
            ->where('id', '!=', $quiz->id)
            ->where('category_id', $quiz->category_id)
            ->limit(4)
            ->get();

        $seo = $this->seo(['title' => 'Result: ' . $quiz->title, 'robots' => 'noindex, nofollow']);

        // If this attempt was played inside a multiplayer room, expose the room
        // so the result page can show its leaderboard in a modal.
        $roomId = \App\Models\QuizRoomParticipant::where('quiz_attempt_id', $attempt->id)
            ->where('user_id', $user->id)
            ->value('room_id');
        $roomLeaderboardUrl = $roomId ? route('website.rooms.results', $roomId) : null;

        return view('website.quizzes.result', compact('seo', 'attempt', 'quiz', 'xp', 'nextLevel', 'recentBadges', 'related', 'roomId', 'roomLeaderboardUrl'));
    }

    /** Answer review, gated on the quiz's own display settings. */
    public function review($attemptId) {
        $attempt = $this->ownedAttempt($attemptId);

        if (!$attempt->isCompleted()) {
            return redirect()->route('website.quiz.attempt', $attempt->id);
        }

        $quiz = $attempt->quiz;

        if (!$quiz->show_correct_answers) {
            return redirect()->route('website.quiz.result', $attempt->id)
                ->with('error', 'Answer review is disabled for this quiz.');
        }

        $answers = $attempt->answers()
            ->with(['question.options', 'question.correctOption', 'selectedOption'])
            ->get();

        $seo = $this->seo(['title' => 'Answer Review: ' . $quiz->title, 'robots' => 'noindex, nofollow']);

        return view('website.quizzes.review', compact('seo', 'attempt', 'quiz', 'answers'));
    }

    // -------------------------------------------------------------- internals

    /** Loads an attempt, 404ing if it does not belong to the current user. */
    private function ownedAttempt($attemptId): QuizAttempt {
        return QuizAttempt::with('quiz')
            ->where('id', $attemptId)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    private function quizFaqs(Quiz $quiz): array {
        $bank  = $quiz->questions_count ?? $quiz->questions()->count();
        $count = $quiz->effectiveQuestionCount($bank);
        $time  = $quiz->time_limit ? "{$quiz->time_limit} minutes" : 'no time limit';

        return [
            ['question' => "How many questions are in the {$quiz->title}?", 'answer' => $quiz->isLimited()
                ? "Each attempt serves {$count} questions, drawn at random from a bank of {$bank}, with {$time}."
                : "This quiz has {$count} questions and {$time}."],
            ['question' => 'What is the passing score?', 'answer' => "You need at least {$quiz->pass_percentage}% to pass this quiz."],
            ['question' => 'Is there negative marking?', 'answer' => $quiz->negative_marking > 0
                ? "Yes, {$quiz->negative_marking} mark(s) are deducted for each wrong answer."
                : 'No, there is no negative marking on this quiz.'],
            ['question' => 'Can I retake this quiz?', 'answer' => 'Yes. You can attempt this quiz again at any time, though first-attempt XP bonuses apply only once.'],
        ];
    }

    /**
     * Quiz structured data (schema.org/Quiz). When sample questions are
     * available, each is emitted as a schema.org/Question in `hasPart` with its
     * correct answer (acceptedAnswer) and distractors (suggestedAnswer) — the
     * SAME questions that are visibly rendered on the page, so the markup never
     * describes content a user cannot see.
     *
     * @param  \Illuminate\Support\Collection|iterable|null  $sampleQuestions
     */
    private function quizSchema(Quiz $quiz, $sampleQuestions = null): array {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Quiz',
            'name'        => $quiz->title,
            'description' => strip_tags((string) $quiz->description),
            'url'         => route('website.quiz.show', $quiz->slug),
            'educationalLevel' => ucfirst($quiz->difficulty),
            'numberOfQuestions' => $quiz->questions_count ?? $quiz->total_questions,
            'about'       => ['@type' => 'Thing', 'name' => $quiz->category?->name ?? 'General Knowledge'],
        ];

        $questions = collect($sampleQuestions ?? [])
            ->map(fn($q) => $this->questionSchema($q))
            ->filter()
            ->values()
            ->all();

        if ($questions) {
            $schema['hasPart'] = $questions;
        }

        return $schema;
    }

    /** One schema.org/Question, or null if it has no identifiable answer. */
    private function questionSchema($question): ?array {
        $options = $question->options ?? collect();
        if ($options->isEmpty()) {
            return null;
        }

        $correct = $options->first(fn($o) => $o->is_correct)
            ?? $options->firstWhere('id', $question->correct_option_id);

        if (!$correct) {
            return null;
        }

        $wrong = $options
            ->reject(fn($o) => $o->id === $correct->id)
            ->map(fn($o) => ['@type' => 'Answer', 'text' => (string) $o->option_text])
            ->values()
            ->all();

        return array_filter([
            '@type'           => 'Question',
            'eduQuestionType' => 'Multiple choice',
            'text'            => (string) $question->question_text,
            'acceptedAnswer'  => ['@type' => 'Answer', 'text' => (string) $correct->option_text],
            'suggestedAnswer' => $wrong ?: null,
        ]);
    }
}
