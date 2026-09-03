<?php

namespace App\Services\Social;

use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\Page;
use App\Models\Quiz;
use Illuminate\Support\Str;

/**
 * Turns Quiz Mitra content into the fields a social post needs.
 *
 * This is the bridge behind "Share to Social Media" on a quiz, and behind the
 * content sources an automation can draw from. Everything it returns is a
 * *draft* - the wizard shows it for editing before anything is published.
 */
class QuizMitraContentSource {

    const TYPE_QUIZ     = 'quiz';
    const TYPE_QUESTION = 'question';
    const TYPE_CATEGORY = 'category';
    const TYPE_BLOG     = 'blog';

    const TYPES = [
        self::TYPE_QUIZ     => 'Quiz',
        self::TYPE_QUESTION => 'Question',
        self::TYPE_CATEGORY => 'Category',
        self::TYPE_BLOG     => 'Blog / Page',
    ];

    /**
     * @return array the seed fields for a SocialPost, or [] when not found.
     */
    public function seed(string $type, $id): array {
        return match ($type) {
            self::TYPE_QUIZ     => $this->fromQuiz($id),
            self::TYPE_QUESTION => $this->fromQuestion($id),
            self::TYPE_CATEGORY => $this->fromCategory($id),
            self::TYPE_BLOG     => $this->fromPage($id),
            default             => [],
        };
    }

    public function fromQuiz($id): array {
        $quiz = $id instanceof Quiz ? $id : Quiz::with('category')->find($id);
        if (!$quiz) {
            return [];
        }

        $url      = $this->safeRoute('quiz.show', $quiz->slug);
        $category = $quiz->category?->name;
        $count    = $quiz->total_questions ?: $quiz->question_limit;

        $caption = trim(sprintf(
            "%s\n\n%s",
            $quiz->title,
            $quiz->description
                ? Str::limit(strip_tags($quiz->description), 220)
                : ($count ? "Test yourself with {$count} questions." : 'Take the quiz and test yourself.')
        ));

        return [
            'title'        => $quiz->title,
            'caption'      => $caption,
            'description'  => strip_tags((string) $quiz->description),
            'quiz_url'     => $url,
            'cta'          => 'Play now',
            'hashtags'     => $this->hashtagsFor(array_filter([$category, $quiz->difficulty, 'quiz', 'quizmitra'])),
            'category_id'  => $quiz->category_id,
            'source_type'  => self::TYPE_QUIZ,
            'source_id'    => $quiz->id,
            'content_type' => $quiz->image ? \App\Constants\SocialStatus::CONTENT_IMAGE : \App\Constants\SocialStatus::CONTENT_TEXT,
            'image_path'   => $quiz->image,
        ];
    }

    /**
     * A single question makes strong social content: the question is posted and
     * the answer withheld, which is what drives replies.
     */
    public function fromQuestion($id): array {
        $question = $id instanceof BankQuestion ? $id : BankQuestion::with('options', 'category')->find($id);
        if (!$question) {
            return [];
        }

        $text    = strip_tags((string) ($question->question ?? $question->title ?? ''));
        $options = collect($question->options ?? [])->take(4)->values();

        $lines = [$text];
        foreach ($options as $i => $option) {
            $label   = chr(65 + $i);
            $lines[] = $label . ') ' . strip_tags((string) ($option->option_text ?? $option->title ?? ''));
        }
        $lines[] = '';
        $lines[] = 'Comment your answer below 👇';

        return [
            'title'        => Str::limit($text, 90),
            'caption'      => implode("\n", array_filter($lines, fn ($l) => $l !== null)),
            'description'  => $text,
            'quiz_url'     => $this->safeRoute('quizzes'),
            'cta'          => 'More questions on Quiz Mitra',
            'hashtags'     => $this->hashtagsFor(array_filter([$question->category?->name, 'gk', 'quiz', 'quizmitra'])),
            'category_id'  => $question->category_id ?? null,
            'source_type'  => self::TYPE_QUESTION,
            'source_id'    => $question->id,
            'content_type' => \App\Constants\SocialStatus::CONTENT_TEXT,
        ];
    }

    public function fromCategory($id): array {
        $category = $id instanceof Category ? $id : Category::find($id);
        if (!$category) {
            return [];
        }

        return [
            'title'        => $category->name,
            'caption'      => trim($category->name . "\n\n" . Str::limit(strip_tags((string) ($category->seo_intro ?: $category->description)), 220)),
            'quiz_url'     => $this->safeRoute('category.show', $category->slug),
            'cta'          => 'Explore quizzes',
            'hashtags'     => $this->hashtagsFor([$category->name, 'quizmitra']),
            'category_id'  => $category->id,
            'source_type'  => self::TYPE_CATEGORY,
            'source_id'    => $category->id,
            'content_type' => \App\Constants\SocialStatus::CONTENT_TEXT,
        ];
    }

    public function fromPage($id): array {
        $page = $id instanceof Page ? $id : Page::find($id);
        if (!$page) {
            return [];
        }

        $title = $page->name ?? $page->title ?? 'Quiz Mitra';

        return [
            'title'        => $title,
            'caption'      => $title,
            'website_url'  => $this->safeRoute('blog.details', $page->slug),
            'cta'          => 'Read more',
            'hashtags'     => $this->hashtagsFor(['quizmitra', 'blog']),
            'source_type'  => self::TYPE_BLOG,
            'source_id'    => $page->id,
            'content_type' => \App\Constants\SocialStatus::CONTENT_TEXT,
        ];
    }

    /* --------------------------------------------------- Automation sources */

    /** The quiz an automation should post today, by its configured strategy. */
    public function resolveAutomationSource(string $sourceType, array $config = []): array {
        return match ($sourceType) {
            'daily_quiz'      => $this->latestQuiz($config['category_id'] ?? null, true),
            'random_quiz'     => $this->randomQuiz($config['category_id'] ?? null),
            'current_affairs' => $this->currentAffairsQuiz(),
            'new_blog'        => $this->latestPage(),
            'random_question' => $this->randomQuestion($config['category_id'] ?? null),
            default           => [],
        };
    }

    protected function latestQuiz($categoryId = null, bool $todayOnly = false): array {
        $query = Quiz::where('status', Quiz::STATUS_PUBLISHED)->orderByDesc('created_at');

        if ($categoryId) {
            $query->where(fn ($q) => $q->where('category_id', $categoryId)->orWhere('sub_category_id', $categoryId));
        }
        if ($todayOnly) {
            // Prefer something published today, but fall back to the newest
            // rather than posting nothing at all.
            $today = (clone $query)->whereDate('created_at', now()->toDateString())->first();
            if ($today) {
                return $this->fromQuiz($today);
            }
        }

        $quiz = $query->first();
        return $quiz ? $this->fromQuiz($quiz) : [];
    }

    protected function randomQuiz($categoryId = null): array {
        $query = Quiz::where('status', Quiz::STATUS_PUBLISHED)->inRandomOrder();
        if ($categoryId) {
            $query->where(fn ($q) => $q->where('category_id', $categoryId)->orWhere('sub_category_id', $categoryId));
        }

        $quiz = $query->first();
        return $quiz ? $this->fromQuiz($quiz) : [];
    }

    protected function currentAffairsQuiz(): array {
        $category = Category::where('slug', 'current-affairs')->first();

        $quiz = Quiz::where('status', Quiz::STATUS_PUBLISHED)
            ->when($category, fn ($q) => $q->where(fn ($w) => $w
                ->where('category_id', $category->id)
                ->orWhere('sub_category_id', $category->id)))
            ->orderByDesc('created_at')
            ->first();

        return $quiz ? $this->fromQuiz($quiz) : [];
    }

    protected function latestPage(): array {
        $page = Page::orderByDesc('id')->first();
        return $page ? $this->fromPage($page) : [];
    }

    protected function randomQuestion($categoryId = null): array {
        $query = BankQuestion::with('options')->inRandomOrder();
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $question = $query->first();
        return $question ? $this->fromQuestion($question) : [];
    }

    /* -------------------------------------------------------------- Helpers */

    /** Hashtags from free-text labels, normalised the same way as the library. */
    protected function hashtagsFor(array $labels): string {
        $tags = collect($labels)
            ->filter()
            ->map(fn ($label) => '#' . Str::of($label)->ascii()->replaceMatches('/[^A-Za-z0-9]/', '')->lower())
            ->filter(fn ($tag) => strlen($tag) > 2)
            ->unique()
            ->take(10);

        return $tags->implode(' ');
    }

    /**
     * Routes are resolved defensively: a template that does not define one of
     * these names should degrade to the site root, not fatal a publish.
     */
    protected function safeRoute(string $name, ...$params): string {
        try {
            return route($name, ...$params);
        } catch (\Throwable $e) {
            return rtrim((string) config('app.url'), '/') . '/';
        }
    }
}
