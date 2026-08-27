<?php

namespace App\Services;

use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\DailySpinAttempt;
use App\Models\Level;
use App\Models\User;
use App\Models\UserXp;
use App\Models\XpRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Backend brain for "Daily Spin & Win".
 *
 * One spin per user per day. A spin serves a SET of questions from the landed
 * category; the user answers them all and submits once. Every trust-sensitive
 * decision is made here against the authenticated user + server date:
 *   - eligibility / one-spin-per-day  (DB unique + row status)
 *   - which segment & questions are served (answers never leak on spin)
 *   - grading each answer
 *   - the +5-per-correct XP reward (through XpService, idempotent per question)
 */
class DailySpinService
{
    /** XP rule key seeded by migration; reward/on-off managed in XP Rules admin. */
    const XP_RULE_KEY = 'daily_spin';

    /** Questions served per spin. */
    const QUESTIONS_PER_SPIN = 10;

    /**
     * Wheel segments. Purely visual on the client; the backend decides the landed
     * one. Each maps to a real top-level category by slug so the questions match
     * the label. `slug === null` = Bonus (any topic).
     */
    const SEGMENTS = [
        ['key' => 'gk',            'label' => 'General Knowledge', 'emoji' => '🎯', 'slug' => 'general-knowledge'],
        ['key' => 'current',       'label' => 'Current Affairs',   'emoji' => '📰', 'slug' => 'current-affairs'],
        ['key' => 'sports',        'label' => 'Sports',            'emoji' => '🏏', 'slug' => 'sports-quiz'],
        ['key' => 'technology',    'label' => 'Technology',        'emoji' => '💻', 'slug' => 'computer-technology'],
        ['key' => 'world',         'label' => 'World',             'emoji' => '🌍', 'slug' => 'world-quiz'],
        ['key' => 'reasoning',     'label' => 'Reasoning',         'emoji' => '🧠', 'slug' => 'reasoning'],
        ['key' => 'entertainment', 'label' => 'Entertainment',     'emoji' => '🎬', 'slug' => 'entertainment-quiz'],
        ['key' => 'bonus',         'label' => 'Bonus',             'emoji' => '🎁', 'slug' => null],
    ];

    /** Segment metadata for the view (index + label + emoji). */
    public static function segments(): array
    {
        $out = [];
        foreach (self::SEGMENTS as $i => $s) {
            $out[] = ['index' => $i, 'key' => $s['key'], 'label' => $s['label'], 'emoji' => $s['emoji']];
        }
        return $out;
    }

    /** Feature on/off — reuses the existing XP rule's active flag. */
    public function isEnabled(): bool
    {
        return XpRule::where('key', self::XP_RULE_KEY)->where('is_active', true)->exists();
    }

    /** Per-correct reward, from the existing (admin-editable) XP rule. */
    public function rewardXp(): int
    {
        return (int) (XpRule::where('key', self::XP_RULE_KEY)->value('xp_value') ?? 5);
    }

    /** Homepage state on load; never creates a row. */
    public function getStatus(User $user): array
    {
        $attempt = $this->todaysAttempt($user);

        if ($attempt && $attempt->isAnswered()) {
            return [
                'state'         => 'completed',
                'correct_count' => (int) $attempt->correct_count,
                'total'         => (int) ($attempt->total_questions ?: self::QUESTIONS_PER_SPIN),
                'xp_awarded'    => (int) $attempt->xp_awarded,
                'next_spin_at'  => now()->addDay()->startOfDay()->toIso8601String(),
            ] + $this->levelBlock($user);
        }

        return ['state' => 'available'] + $this->levelBlock($user);
    }

    /**
     * Resolve today's spin: create it (with its 10 questions) on the first spin of
     * the day; a repeat the same day resumes the SAME set. Race-safe.
     */
    public function spin(User $user): array
    {
        if (!$this->isEnabled()) {
            return ['state' => 'disabled'];
        }

        return DB::transaction(function () use ($user) {
            $today = today()->toDateString();

            $attempt = DailySpinAttempt::where('user_id', $user->id)
                ->where('spin_date', $today)
                ->lockForUpdate()
                ->first();

            if ($attempt && $attempt->isAnswered()) {
                return [
                    'state'         => 'already_completed',
                    'correct_count' => (int) $attempt->correct_count,
                    'total'         => (int) ($attempt->total_questions ?: self::QUESTIONS_PER_SPIN),
                    'xp_awarded'    => (int) $attempt->xp_awarded,
                    'next_spin_at'  => now()->addDay()->startOfDay()->toIso8601String(),
                ] + $this->levelBlock($user);
            }

            // Resume an in-progress spin with its original question set.
            if ($attempt) {
                $questions = $this->loadQuestions($attempt->question_ids ?? []);

                // Repick if the stored set is missing/short or holds unanswerable
                // (orphaned) questions — never strand the user on an empty set.
                if ($questions->count() < self::QUESTIONS_PER_SPIN) {
                    [$segIndex, $seg, $questions] = $this->pickSegmentAndQuestions($user);
                    $attempt->update([
                        'segment_key'   => $seg['key'],
                        'segment_index' => $segIndex,
                        'category_id'   => $questions->first()->category_id ?? null,
                        'question_ids'  => $questions->pluck('id')->all(),
                    ]);
                }

                $idx = $attempt->segment_index ?? 0;
                return $this->spinPayload($idx, self::SEGMENTS[$idx], $questions, $attempt);
            }

            // First spin today.
            [$segIndex, $seg, $questions] = $this->pickSegmentAndQuestions($user);

            $attempt = DailySpinAttempt::create([
                'user_id'         => $user->id,
                'spin_date'       => $today,
                'segment_key'     => $seg['key'],
                'segment_index'   => $segIndex,
                'category_id'     => $questions->first()->category_id ?? null,
                'question_ids'    => $questions->pluck('id')->all(),
                'total_questions' => $questions->count(),
                'status'          => DailySpinAttempt::STATUS_SPUN,
            ]);

            return $this->spinPayload($segIndex, $seg, $questions, $attempt);
        });
    }

    /**
     * Grade the whole set and award +5 per correct. Idempotent: a second submit
     * replays the stored result and never re-awards.
     *
     * @param array $answers  map of [question_id => option_id]
     */
    public function submit(User $user, int $spinId, array $answers): array
    {
        return DB::transaction(function () use ($user, $spinId, $answers) {
            $attempt = DailySpinAttempt::where('id', $spinId)
                ->where('user_id', $user->id)
                ->where('spin_date', today()->toDateString())
                ->lockForUpdate()
                ->first();

            if (!$attempt) {
                return ['error' => 'invalid_spin', 'message' => 'This spin is no longer valid. Please spin again.'];
            }

            $ids       = $attempt->question_ids ?? [];
            $questions = $this->loadQuestions($ids);
            if ($questions->count() === 0) {
                return ['error' => 'invalid_question', 'message' => 'Questions unavailable. Please try again tomorrow.'];
            }

            // Already submitted -> replay the stored outcome (no second award).
            if ($attempt->isAnswered()) {
                return $this->submitResult($user, $attempt, $questions, $attempt->answers ?? [], (int) $attempt->xp_awarded, true);
            }

            // Normalise + validate every answer against THIS spin's questions.
            $clean = [];
            foreach ($ids as $qid) {
                $q = $questions->get($qid);
                if (!$q) {
                    return ['error' => 'invalid_question', 'message' => 'A question in this set is unavailable.'];
                }
                if (!array_key_exists($qid, $answers) && !array_key_exists((string) $qid, $answers)) {
                    return ['error' => 'incomplete', 'message' => 'Please answer all questions before submitting.'];
                }
                $optId = (int) ($answers[$qid] ?? $answers[(string) $qid]);
                if (!$q->options->firstWhere('id', $optId)) {
                    return ['error' => 'invalid_option', 'message' => 'An answer option was not recognised.'];
                }
                $clean[$qid] = $optId;
            }

            // Grade + award +5 per correct through the existing engine (idempotent
            // per question/day via the service's date-scoped unique_identifier).
            $xpService = new XpService();
            $correct = 0;
            $xpEarned = 0;
            foreach ($ids as $qid) {
                $q = $questions->get($qid);
                if ($clean[$qid] === (int) $q->correct_option_id) {
                    $correct++;
                    $tx = $xpService->awardXp($user, self::XP_RULE_KEY, 'daily_spin', $q->id);
                    $xpEarned += $tx ? (int) $tx->xp_amount : 0;
                }
            }

            $attempt->answers         = $clean;
            $attempt->correct_count   = $correct;
            $attempt->total_questions = count($ids);
            $attempt->xp_awarded      = $xpEarned;
            $attempt->status          = DailySpinAttempt::STATUS_ANSWERED;
            $attempt->answered_at     = now();
            $attempt->save();

            return $this->submitResult($user->fresh(), $attempt, $questions, $clean, $xpEarned, false);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                          */
    /* ------------------------------------------------------------------ */

    private function todaysAttempt(User $user): ?DailySpinAttempt
    {
        return DailySpinAttempt::where('user_id', $user->id)
            ->where('spin_date', today()->toDateString())
            ->first();
    }

    /** Load questions (with ordered options) keyed by id, in the given order. */
    private function loadQuestions(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }
        $byId = BankQuestion::whereIn('id', $ids)
            ->with(['options' => fn($q) => $q->orderBy('sort_order')])
            ->get()
            ->keyBy('id');

        // Preserve served order, keep only answerable ones.
        return collect($ids)
            ->map(fn($id) => $byId->get($id))
            ->filter(fn($q) => $q && $q->options->count() >= 2)
            ->keyBy('id');
    }

    /**
     * Choose a wheel segment and QUESTIONS_PER_SPIN answerable questions from it,
     * preferring questions this user hasn't been served before.
     *
     * @return array{0:int,1:array,2:Collection}
     */
    private function pickSegmentAndQuestions(User $user): array
    {
        $need      = self::QUESTIONS_PER_SPIN;
        $servedIds = DailySpinAttempt::where('user_id', $user->id)
            ->pluck('question_ids')
            ->flatMap(fn($v) => is_array($v) ? $v : [])
            ->unique()->values()->all();

        $order = array_keys(self::SEGMENTS);
        shuffle($order);

        // Pass 1: a full fresh set from a single segment.
        foreach ($order as $i) {
            $qs = $this->questionsForSegment(self::SEGMENTS[$i], $servedIds, $need);
            if ($qs->count() >= $need) {
                return [$i, self::SEGMENTS[$i], $qs->take($need)->keyBy('id')];
            }
        }

        // Pass 2: relax the "not recently shown" preference.
        foreach ($order as $i) {
            $qs = $this->questionsForSegment(self::SEGMENTS[$i], [], $need);
            if ($qs->count() >= $need) {
                return [$i, self::SEGMENTS[$i], $qs->take($need)->keyBy('id')];
            }
        }

        // Pass 3: best segment we can, topped up from the global pool.
        $i   = $order[0];
        $seg = self::SEGMENTS[$i];
        $qs  = $this->questionsForSegment($seg, [], $need);
        if ($qs->count() < $need) {
            $have = $qs->pluck('id')->all();
            $fill = $this->baseQuestionQuery()
                ->whereNotIn('id', $have)
                ->inRandomOrder()
                ->limit($need - $qs->count())
                ->get();
            $qs = $qs->concat($fill);
        }

        return [$i, $seg, $qs->take($need)->keyBy('id')];
    }

    private function questionsForSegment(array $segment, array $excludeIds, int $limit): Collection
    {
        $query = $this->baseQuestionQuery();

        if (!empty($segment['slug'])) {
            $categoryId = $this->categoryIdBySlug($segment['slug']);
            if (!$categoryId) {
                return collect();
            }
            $query->where('category_id', $categoryId);
        }
        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->inRandomOrder()->limit($limit)->get();
    }

    /** Active, single-answer questions that actually have >=2 options. */
    private function baseQuestionQuery()
    {
        return BankQuestion::query()
            ->where('status', 1)
            ->where('question_type', BankQuestion::TYPE_MCQ_SINGLE)
            ->whereNotNull('correct_option_id')
            // ~43% of the bank has a correct_option_id but NO option rows (orphaned);
            // require a real, answerable set. For questions with >=2 options the
            // correct_option_id is always among them, so this alone is sufficient.
            ->has('options', '>=', 2)
            ->with(['options' => fn($q) => $q->orderBy('sort_order')]);
    }

    private function categoryIdBySlug(string $slug): ?int
    {
        return Cache::remember("daily_spin.cat.$slug", 3600, function () use ($slug) {
            return Category::where('slug', $slug)->value('id');
        });
    }

    /** Spin JSON — the correct answers are deliberately NOT included. */
    private function spinPayload(int $segIndex, array $seg, Collection $questions, DailySpinAttempt $attempt): array
    {
        return [
            'state'          => 'question',
            'spin_id'        => $attempt->id,
            'target_segment' => $segIndex,
            'segment'        => ['key' => $seg['key'], 'label' => $seg['label'], 'emoji' => $seg['emoji']],
            'category'       => $seg['label'],
            'total'          => $questions->count(),
            'questions'      => $questions->values()->map(fn($q) => [
                'id'      => $q->id,
                'text'    => $q->question_text,
                'options' => $q->options->map(fn($o) => ['id' => $o->id, 'text' => $o->option_text])->values(),
            ])->all(),
        ];
    }

    /** Submit JSON — score + full per-question review (correct answer + explanation). */
    private function submitResult(User $user, DailySpinAttempt $attempt, Collection $questions, array $answers, int $xpEarned, bool $replay): array
    {
        $review = $questions->values()->map(function ($q) use ($answers) {
            $your    = $answers[$q->id] ?? ($answers[(string) $q->id] ?? null);
            $correct = $q->options->firstWhere('id', $q->correct_option_id);
            return [
                'question_id'         => $q->id,
                'question_text'       => $q->question_text,
                'your_option_id'      => $your !== null ? (int) $your : null,
                'correct_option_id'   => (int) $q->correct_option_id,
                'correct_option_text' => $correct ? $correct->option_text : null,
                'is_correct'          => $your !== null && (int) $your === (int) $q->correct_option_id,
                'explanation'         => $q->explanation,
            ];
        })->all();

        return [
            'state'         => 'submitted',
            'replay'        => $replay,
            'correct_count' => (int) $attempt->correct_count,
            'total'         => (int) ($attempt->total_questions ?: $questions->count()),
            'xp_earned'     => $xpEarned,
            'review'        => $review,
            'next_spin_at'  => now()->addDay()->startOfDay()->toIso8601String(),
        ] + $this->levelBlock($user);
    }

    /** Current XP + level snapshot for the UI (read fresh). */
    private function levelBlock(User $user): array
    {
        $userXp = $user->xpProfile ?? UserXp::firstOrCreate(
            ['user_id' => $user->id],
            ['total_xp' => 0, 'current_level' => 1]
        );
        $total = (int) $userXp->total_xp;
        $level = Level::getLevelByXp($total);

        return [
            'total_xp'   => $total,
            'level'      => $level ? (int) $level->level_number : (int) $userXp->current_level,
            'level_name' => $level->name ?? null,
        ];
    }
}
