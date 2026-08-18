<?php

namespace App\Services;

use App\Models\Quiz;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Selects a small, stable set of a quiz's real questions to render publicly on
 * the quiz detail page — the SEO "sample" that lets Google crawl actual
 * questions, answers and explanations without exposing the full timed attempt.
 *
 * Design goals:
 *  - Deterministic: the same questions are chosen on every crawl (stable URLs
 *    and content), so long as the quiz's question bank is unchanged.
 *  - Prefers questions that carry a written explanation (richer, more useful).
 *  - Bounded work: only the sample (10-15 rows) and their options are loaded —
 *    never the whole (often 100+) bank.
 *  - Read-only: this NEVER creates an attempt, touches XP, or reveals anything
 *    beyond the intentionally-public question content.
 */
class QuizSampleService {
    /**
     * The sample questions for a quiz, each with its options eager-loaded.
     * Cached per quiz (keyed on the quiz's updated_at so an edit busts it).
     *
     * @return Collection<int, \App\Models\BankQuestion>
     */
    public function forQuiz(Quiz $quiz, ?int $limit = null): Collection {
        $limit = max(1, $limit ?? (int) config('seo.sample_questions', 12));
        $stamp = optional($quiz->updated_at)->timestamp ?? 0;

        return Cache::remember(
            "website.quiz.sample.{$quiz->id}.{$limit}.{$stamp}",
            (int) config('seo.sample_cache_ttl', 21600),
            fn() => $this->select($quiz, $limit)
        );
    }

    /**
     * Builds the selection. Questions come back in the quiz's own display order
     * (the pivot question_order the relationship already applies), so the set is
     * deterministic. Explanation-bearing questions are taken first; if a quiz
     * has fewer of those than requested, the remainder is topped up so the page
     * still shows a useful number of samples.
     */
    private function select(Quiz $quiz, int $limit): Collection {
        // Only questions that are active AND have an option flagged correct are
        // eligible. `is_correct` is the field the scoring engine and the answer
        // review screen both use, so the public "Correct answer" badge always
        // matches how the quiz is actually graded. A sample without a correct
        // answer would be useless (and would produce invalid Quiz schema).
        $eligible = fn() => $quiz->questions()
            ->where('bank_questions.status', 1)
            ->whereHas('options', fn($o) => $o->where('is_correct', 1))
            ->with(['options' => fn($q) => $q->orderBy('sort_order')]);

        $preferred = $eligible()
            ->whereNotNull('bank_questions.explanation')
            ->where('bank_questions.explanation', '!=', '')
            ->limit($limit)
            ->get();

        if ($preferred->count() >= $limit) {
            return $preferred->values();
        }

        // Top up with eligible questions that have no explanation.
        $needed = $limit - $preferred->count();
        $rest = $eligible()
            ->whereNotIn('bank_questions.id', $preferred->pluck('id')->all())
            ->limit($needed)
            ->get();

        return $preferred->concat($rest)->values();
    }
}
