<?php

namespace App\Services;

use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\DailySpinAttempt;
use App\Models\Level;
use App\Models\User;
use App\Models\UserXp;
use App\Models\XpRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Backend brain for "Daily Spin & Win".
 *
 * Every trust-sensitive decision lives here and is validated against the
 * authenticated user and the server date — the frontend only animates:
 *   - eligibility / one-spin-per-day  (DB unique + row status)
 *   - which segment & question are served (never leaks the correct answer)
 *   - whether an answer is correct
 *   - the +5 XP reward (through the existing XpService, idempotent)
 */
class DailySpinService
{
    /** XP rule key seeded by the migration; reward/on-off managed in XP Rules admin. */
    const XP_RULE_KEY = 'daily_spin';

    /**
     * Wheel segments. Purely visual on the client; the backend decides which one
     * the user "lands" on. Each maps to a real top-level category by slug so the
     * question shown matches the segment label. `slug === null` = Bonus (any topic).
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

    /** Segment metadata for the view (index + label + emoji), no DB coupling. */
    public static function segments(): array
    {
        $out = [];
        foreach (self::SEGMENTS as $i => $s) {
            $out[] = ['index' => $i, 'key' => $s['key'], 'label' => $s['label'], 'emoji' => $s['emoji']];
        }
        return $out;
    }

    /** Is the whole feature switched on? Reuses the existing XP rule's active flag. */
    public function isEnabled(): bool
    {
        return XpRule::where('key', self::XP_RULE_KEY)->where('is_active', true)->exists();
    }

    /** Reward amount, read from the existing XP rule (admin-editable). */
    public function rewardXp(): int
    {
        return (int) (XpRule::where('key', self::XP_RULE_KEY)->value('xp_value') ?? 5);
    }

    /**
     * State for the homepage on load. Never creates a row.
     * Returns one of: available | completed  (guests are handled in the controller).
     */
    public function getStatus(User $user): array
    {
        $attempt = $this->todaysAttempt($user);

        if ($attempt && $attempt->isAnswered()) {
            return [
                'state'        => 'completed',
                'is_correct'   => (bool) $attempt->is_correct,
                'xp_awarded'   => (int) $attempt->xp_awarded,
                'next_spin_at' => now()->addDay()->startOfDay()->toIso8601String(),
            ] + $this->levelBlock($user);
        }

        // No attempt yet, or spun-but-not-answered (which spin() will resume).
        return ['state' => 'available'] + $this->levelBlock($user);
    }

    /**
     * Resolve today's spin. Creates the row on first spin of the day; a repeat call
     * the same day resumes the SAME question (a refresh never burns a new question,
     * and an already-answered day is reported as completed). Race-safe via a locked
     * transaction on top of the UNIQUE(user_id, spin_date) guarantee.
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
                    'state'        => 'already_completed',
                    'is_correct'   => (bool) $attempt->is_correct,
                    'xp_awarded'   => (int) $attempt->xp_awarded,
                    'next_spin_at' => now()->addDay()->startOfDay()->toIso8601String(),
                ] + $this->levelBlock($user);
            }

            // Resume an in-progress spin with its original question.
            if ($attempt) {
                $question = BankQuestion::with(['options' => fn($q) => $q->orderBy('sort_order')])
                    ->find($attempt->question_id);

                if (!$question) {
                    // Question vanished (rare) — repick so the user isn't stuck.
                    [$segIndex, $seg, $question] = $this->pickSegmentAndQuestion($user);
                    $attempt->update([
                        'segment_key'   => $seg['key'],
                        'segment_index' => $segIndex,
                        'category_id'   => $question->category_id,
                        'question_id'   => $question->id,
                    ]);
                }

                return $this->spinPayload($attempt->segment_index ?? 0, self::SEGMENTS[$attempt->segment_index ?? 0], $question, $attempt);
            }

            // First spin today: pick and persist.
            [$segIndex, $seg, $question] = $this->pickSegmentAndQuestion($user);

            $attempt = DailySpinAttempt::create([
                'user_id'       => $user->id,
                'spin_date'     => $today,
                'segment_key'   => $seg['key'],
                'segment_index' => $segIndex,
                'category_id'   => $question->category_id,
                'question_id'   => $question->id,
                'status'        => DailySpinAttempt::STATUS_SPUN,
            ]);

            return $this->spinPayload($segIndex, $seg, $question, $attempt);
        });
    }

    /**
     * Validate and grade an answer. Idempotent: a second submission returns the
     * first result and never re-awards XP. Correctness and reward are decided here.
     */
    public function answer(User $user, int $spinId, int $optionId): array
    {
        return DB::transaction(function () use ($user, $spinId, $optionId) {
            $attempt = DailySpinAttempt::where('id', $spinId)
                ->where('user_id', $user->id)
                ->where('spin_date', today()->toDateString())
                ->lockForUpdate()
                ->first();

            if (!$attempt) {
                return ['error' => 'invalid_spin', 'message' => 'This spin is no longer valid. Please spin again.'];
            }

            $question = BankQuestion::with(['options' => fn($q) => $q->orderBy('sort_order')])
                ->find($attempt->question_id);

            if (!$question) {
                return ['error' => 'invalid_question', 'message' => 'Question unavailable. Please try again tomorrow.'];
            }

            // Already answered → replay stored outcome (no second XP award).
            if ($attempt->isAnswered()) {
                return $this->answerResult($user, $attempt, $question, (int) $attempt->xp_awarded, /*replay*/ true);
            }

            // The chosen option must belong to THIS question (blocks tampered ids).
            $chosen = $question->options->firstWhere('id', $optionId);
            if (!$chosen) {
                return ['error' => 'invalid_option', 'message' => 'That answer option was not recognised.'];
            }

            $isCorrect = (int) $optionId === (int) $question->correct_option_id;

            $attempt->selected_option_id = $optionId;
            $attempt->is_correct         = $isCorrect;
            $attempt->status             = DailySpinAttempt::STATUS_ANSWERED;
            $attempt->answered_at        = now();

            $xpEarned = 0;
            if ($isCorrect) {
                // Award through the existing engine. reference (daily_spin, attempt id)
                // + the service's date-scoped unique_identifier make it idempotent, so
                // even a duplicate request cannot grant the +5 twice.
                $xpService   = new XpService();
                $transaction = $xpService->awardXp(
                    $user,
                    self::XP_RULE_KEY,           // event/rule key
                    'daily_spin',                // reference_type
                    $attempt->id                 // reference_id
                );
                $xpEarned = $transaction ? (int) $transaction->xp_amount : 0;
            }

            $attempt->xp_awarded = $xpEarned;
            $attempt->save();

            return $this->answerResult($user, $attempt, $question, $xpEarned, /*replay*/ false);
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

    /**
     * Choose a wheel segment (in random order) and a fresh question from its
     * category, preferring questions this user has not been served before. Always
     * returns a valid question with a correct option and at least two options.
     *
     * @return array{0:int,1:array,2:BankQuestion}
     */
    private function pickSegmentAndQuestion(User $user): array
    {
        $servedIds = DailySpinAttempt::where('user_id', $user->id)
            ->pluck('question_id')->all();

        $order = array_keys(self::SEGMENTS);
        shuffle($order);

        // Pass 1: honour the "not recently shown" preference.
        foreach ($order as $i) {
            $q = $this->questionForSegment(self::SEGMENTS[$i], $servedIds);
            if ($q) {
                return [$i, self::SEGMENTS[$i], $q];
            }
        }

        // Pass 2: relax the exclusion (user has seen everything in those pools).
        foreach ($order as $i) {
            $q = $this->questionForSegment(self::SEGMENTS[$i], []);
            if ($q) {
                return [$i, self::SEGMENTS[$i], $q];
            }
        }

        // Final fallback: any active single-answer question, mapped to Bonus.
        $bonusIndex = $this->segmentIndexByKey('bonus');
        $q = $this->baseQuestionQuery()->inRandomOrder()->first();

        return [$bonusIndex, self::SEGMENTS[$bonusIndex], $q];
    }

    private function questionForSegment(array $segment, array $excludeIds): ?BankQuestion
    {
        $query = $this->baseQuestionQuery();

        if (!empty($segment['slug'])) {
            $categoryId = $this->categoryIdBySlug($segment['slug']);
            if (!$categoryId) {
                return null;
            }
            $query->where('category_id', $categoryId);
        }

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->inRandomOrder()->first();
    }

    /** Active, single-answer questions that have a defined correct option. */
    private function baseQuestionQuery()
    {
        return BankQuestion::query()
            ->where('status', 1)
            ->where('question_type', BankQuestion::TYPE_MCQ_SINGLE)
            ->whereNotNull('correct_option_id')
            ->with(['options' => fn($q) => $q->orderBy('sort_order')]);
    }

    private function categoryIdBySlug(string $slug): ?int
    {
        return Cache::remember("daily_spin.cat.$slug", 3600, function () use ($slug) {
            return Category::where('slug', $slug)->value('id');
        });
    }

    private function segmentIndexByKey(string $key): int
    {
        foreach (self::SEGMENTS as $i => $s) {
            if ($s['key'] === $key) {
                return $i;
            }
        }
        return count(self::SEGMENTS) - 1;
    }

    /** Spin JSON — the correct answer is deliberately NOT included. */
    private function spinPayload(int $segIndex, array $seg, BankQuestion $question, DailySpinAttempt $attempt): array
    {
        return [
            'state'          => 'question',
            'spin_id'        => $attempt->id,
            'target_segment' => $segIndex,
            'segment'        => ['key' => $seg['key'], 'label' => $seg['label'], 'emoji' => $seg['emoji']],
            'category'       => $seg['label'],
            'question'       => [
                'id'      => $question->id,
                'text'    => $question->question_text,
                'options' => $question->options->map(fn($o) => [
                    'id'   => $o->id,
                    'text' => $o->option_text,
                ])->values(),
            ],
        ];
    }

    /** Answer JSON — includes the correct option + explanation for learning. */
    private function answerResult(User $user, DailySpinAttempt $attempt, BankQuestion $question, int $xpEarned, bool $replay): array
    {
        $correct = $question->options->firstWhere('id', $question->correct_option_id);

        return [
            'state'                => 'answered',
            'replay'               => $replay,
            'correct'              => (bool) $attempt->is_correct,
            'selected_option_id'   => (int) $attempt->selected_option_id,
            'correct_option_id'    => (int) $question->correct_option_id,
            'correct_option_text'  => $correct ? $correct->option_text : null,
            'explanation'          => $question->explanation,
            'xp_earned'            => $xpEarned,
            'next_spin_at'         => now()->addDay()->startOfDay()->toIso8601String(),
        ] + $this->levelBlock($user->fresh());
    }

    /** Current XP + level snapshot for the UI (read fresh). */
    private function levelBlock(User $user): array
    {
        $userXp   = $user->xpProfile ?? UserXp::firstOrCreate(
            ['user_id' => $user->id],
            ['total_xp' => 0, 'current_level' => 1]
        );
        $total    = (int) $userXp->total_xp;
        $level    = Level::getLevelByXp($total);

        return [
            'total_xp'   => $total,
            'level'      => $level ? (int) $level->level_number : (int) $userXp->current_level,
            'level_name' => $level->name ?? null,
        ];
    }
}
