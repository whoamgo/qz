<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\GamificationSetting;
use App\Models\Level;
use App\Models\QuizXpSetting;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserXp;
use App\Models\XpRule;
use App\Models\XpTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XpService {
    protected $settings;

    public function __construct() {
        $this->settings = GamificationSetting::get();
    }

    /**
     * Award XP to a user
     *
     * @param User $user
     * @param string $eventType Rule key (e.g., 'quiz_completed')
     * @param string|null $referenceType Type of reference (quiz, question, badge, manual)
     * @param int|null $referenceId ID of the reference
     * @param string|null $adminNote Note for admin actions
     * @param string $source Source of XP (system, admin, user)
     * @param int|null $adminId Admin ID if admin awarded
     * @return XpTransaction|null
     */
    public function awardXp(
        User $user,
        string $eventType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $adminNote = null,
        string $source = 'system',
        ?int $adminId = null
    ): ?XpTransaction {
        if (!$this->settings->xp_system_enabled) {
            return null;
        }

        $rule = XpRule::active()->where('key', $eventType)->first();
        if (!$rule) {
            Log::warning("XP Rule not found: {$eventType}");
            return null;
        }

        $userXp = $user->xpProfile ?? UserXp::firstOrCreate(
            ['user_id' => $user->id],
            ['total_xp' => 0, 'current_level' => 1]
        );

        // Check if XP should be awarded for this quiz (if applicable)
        if ($referenceType === 'quiz' && $referenceId) {
            if (!$this->canAwardXpForQuiz($user, $referenceId)) {
                return null;
            }
        }

        // Calculate actual XP amount considering limits and multipliers
        $xpAmount = $this->calculateXpAmount($user, $rule, $referenceType, $referenceId);

        if ($xpAmount <= 0) {
            return null;
        }

        // Check daily limit
        if (!$this->checkDailyLimit($user, $xpAmount)) {
            Log::info("Daily XP limit reached for user {$user->id}");
            return null;
        }

        // Check weekly limit
        if (!$this->checkWeeklyLimit($user, $xpAmount)) {
            Log::info("Weekly XP limit reached for user {$user->id}");
            return null;
        }

        // Check rule-specific limits
        if ($rule->daily_limit && !$this->checkRuleDailyLimit($user, $rule, $xpAmount)) {
            Log::info("Rule daily limit reached for user {$user->id}, rule: {$rule->key}");
            return null;
        }

        // Create unique identifier for idempotency
        $uniqueIdentifier = $this->generateUniqueIdentifier($user->id, $eventType, $referenceType, $referenceId);

        // Check if transaction already exists (prevent duplicate XP)
        if (XpTransaction::where('unique_identifier', $uniqueIdentifier)->exists()) {
            Log::info("Duplicate XP transaction prevented for user {$user->id}, rule: {$eventType}");
            return null;
        }

        DB::beginTransaction();
        try {
            // Create transaction
            $transaction = XpTransaction::create([
                'user_id' => $user->id,
                'event_type' => $eventType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'xp_amount' => $xpAmount,
                'direction' => 'earned',
                'description' => $rule->description,
                'source' => $source,
                'admin_id' => $adminId,
                'admin_note' => $adminNote,
                'unique_identifier' => $uniqueIdentifier,
                'metadata' => [
                    'rule_key' => $rule->key,
                    'base_xp' => $rule->xp_value,
                ],
            ]);

            // Update user XP totals
            $userXp->total_xp += $xpAmount;
            $userXp->xp_this_week += $xpAmount;
            $userXp->xp_this_month += $xpAmount;
            $userXp->last_xp_activity = now();

            // Update current level
            $newLevel = Level::getLevelByXp($userXp->total_xp);
            if ($newLevel) {
                $oldLevel = $userXp->current_level;
                $userXp->current_level = $newLevel->level_number;

                // Notify if level up
                if ($newLevel->level_number > $oldLevel && $this->settings->notify_level_up) {
                    $this->notifyLevelUp($user, $oldLevel, $newLevel);
                }
            }

            $userXp->save();

            // Update streak
            if ($this->settings->streaks_enabled) {
                $streakService = new StreakService();
                $streakService->updateStreak($user);
            }

            // Check for badge conditions
            if ($this->settings->badges_enabled) {
                $badgeService = new BadgeService();
                $badgeService->checkAndAwardBadges($user);
            }

            // Notify XP earned
            if ($this->settings->notify_xp_earned) {
                $this->notifyXpEarned($user, $xpAmount, $rule->name);
            }

            DB::commit();

            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error awarding XP to user {$user->id}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Deduct XP from a user (admin action)
     */
    public function deductXp(
        User $user,
        int $amount,
        string $reason,
        ?string $adminNote = null,
        int $adminId = null
    ): ?XpTransaction {
        $userXp = $user->xpProfile;
        if (!$userXp) {
            return null;
        }

        DB::beginTransaction();
        try {
            $transaction = XpTransaction::create([
                'user_id' => $user->id,
                'event_type' => 'admin_deduction',
                'reference_type' => 'manual',
                'xp_amount' => $amount,
                'direction' => 'deducted',
                'description' => $reason,
                'source' => 'admin',
                'admin_id' => $adminId,
                'admin_note' => $adminNote,
                'unique_identifier' => 'deduction_' . uniqid(),
            ]);

            $userXp->total_xp = max(0, $userXp->total_xp - $amount);
            $userXp->save();

            // Recalculate level
            $newLevel = Level::getLevelByXp($userXp->total_xp);
            if ($newLevel) {
                $userXp->current_level = $newLevel->level_number;
                $userXp->save();
            }

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error deducting XP from user {$user->id}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Calculate XP amount considering difficulty, attempts, and other factors
     */
    protected function calculateXpAmount(
        User $user,
        XpRule $rule,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): int {
        $baseXp = $rule->xp_value;

        // Check if this is a repeat attempt and apply multiplier
        if ($referenceType === 'quiz' && $referenceId) {
            $attemptNumber = $this->getAttemptNumber($user, $referenceId);
            if ($attemptNumber > 1) {
                $multiplier = $this->getAttemptMultiplier($attemptNumber);
                $baseXp = (int) ($baseXp * ($multiplier / 100));
            }
        }

        // Cap by max XP per quiz if applicable
        if ($referenceType === 'quiz') {
            $baseXp = min($baseXp, $this->settings->max_xp_per_quiz);
        }

        return $baseXp;
    }

    /**
     * Get the attempt number for a quiz by a user
     */
    protected function getAttemptNumber(User $user, int $quizId): int {
        return DB::table('xp_transactions')
            ->where('user_id', $user->id)
            ->where('reference_type', 'quiz')
            ->where('reference_id', $quizId)
            ->where('event_type', 'quiz_completed')
            ->count() + 1;
    }

    /**
     * Get attempt multiplier based on attempt number
     */
    protected function getAttemptMultiplier(int $attemptNumber): int {
        if ($attemptNumber === 1) {
            return $this->settings->first_attempt_percentage;
        } elseif ($attemptNumber === 2) {
            return $this->settings->second_attempt_percentage;
        } else {
            return $this->settings->third_plus_attempt_percentage;
        }
    }

    /**
     * Check if daily XP limit is exceeded
     */
    protected function checkDailyLimit(User $user, int $newXp): bool {
        $dailyXp = XpTransaction::where('user_id', $user->id)
            ->where('direction', 'earned')
            ->whereDate('created_at', today())
            ->sum('xp_amount');

        return ($dailyXp + $newXp) <= $this->settings->daily_xp_cap;
    }

    /**
     * Check if weekly XP limit is exceeded
     */
    protected function checkWeeklyLimit(User $user, int $newXp): bool {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $weeklyXp = XpTransaction::where('user_id', $user->id)
            ->where('direction', 'earned')
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->sum('xp_amount');

        return ($weeklyXp + $newXp) <= $this->settings->weekly_xp_cap;
    }

    /**
     * Check if rule-specific daily limit is exceeded
     */
    protected function checkRuleDailyLimit(User $user, XpRule $rule, int $newXp): bool {
        if (!$rule->daily_limit) {
            return true;
        }

        $count = XpTransaction::where('user_id', $user->id)
            ->where('event_type', $rule->key)
            ->whereDate('created_at', today())
            ->count();

        return $count < $rule->daily_limit;
    }

    /**
     * Check if XP can be awarded for a quiz
     */
    protected function canAwardXpForQuiz(User $user, int $quizId): bool {
        $quizSettings = QuizXpSetting::where('quiz_id', $quizId)->first();
        if (!$quizSettings || !$quizSettings->xp_enabled) {
            return false;
        }

        return true;
    }

    /**
     * Award XP for quiz completion with all bonuses and multipliers
     */
    public function awardQuizCompletionXp(
        User $user,
        int $quizId,
        int $correctAnswers,
        int $totalQuestions,
        bool $passed,
        bool $perfect,
        bool $isFirstAttempt = true
    ): array {
        $result = [
            'total_xp' => 0,
            'breakdown' => [
                'completion' => 0,
                'correct_answers' => 0,
                'passing_bonus' => 0,
                'perfect_bonus' => 0,
                'first_attempt_bonus' => 0,
            ],
            'transactions' => [],
            'level_before' => null,
            'level_after' => null,
            'level_up' => false,
        ];

        if (!$this->settings->xp_system_enabled) {
            return $result;
        }

        $userXp = $user->xpProfile ?? UserXp::firstOrCreate(
            ['user_id' => $user->id],
            ['total_xp' => 0, 'current_level' => 1]
        );

        $levelBefore = $userXp->current_level;

        try {
            DB::beginTransaction();

            // Generate unique identifier for this quiz completion
            $uniqueId = "quiz_{$quizId}_user_{$user->id}_" . now()->format('Y-m-d-H-i-s') . '_' . uniqid();

            // Award completion XP
            $completionXp = $this->awardQuizComponentXp(
                $user,
                $userXp,
                $quizId,
                'quiz_completed',
                $uniqueId . '_completion'
            );
            $result['breakdown']['completion'] = $completionXp;
            $result['total_xp'] += $completionXp;

            // Award correct answer XP
            $correctXp = $this->awardCorrectAnswerXp($user, $userXp, $quizId, $correctAnswers, $totalQuestions, $uniqueId);
            $result['breakdown']['correct_answers'] = $correctXp;
            $result['total_xp'] += $correctXp;

            // Award passing bonus
            if ($passed) {
                $passingXp = $this->awardQuizComponentXp(
                    $user,
                    $userXp,
                    $quizId,
                    'quiz_passed',
                    $uniqueId . '_passing'
                );
                $result['breakdown']['passing_bonus'] = $passingXp;
                $result['total_xp'] += $passingXp;
            }

            // Award perfect score bonus
            if ($perfect) {
                $perfectXp = $this->awardQuizComponentXp(
                    $user,
                    $userXp,
                    $quizId,
                    'perfect_score',
                    $uniqueId . '_perfect'
                );
                $result['breakdown']['perfect_bonus'] = $perfectXp;
                $result['total_xp'] += $perfectXp;
            }

            // Award first attempt bonus
            if ($isFirstAttempt) {
                $firstAttemptXp = $this->awardQuizComponentXp(
                    $user,
                    $userXp,
                    $quizId,
                    'first_attempt',
                    $uniqueId . '_first'
                );
                $result['breakdown']['first_attempt_bonus'] = $firstAttemptXp;
                $result['total_xp'] += $firstAttemptXp;
            }

            // Update level
            $newLevel = Level::getLevelByXp($userXp->total_xp);
            if ($newLevel && $newLevel->level_number !== $levelBefore) {
                $userXp->current_level = $newLevel->level_number;
                $result['level_up'] = true;

                if ($this->settings->notify_level_up) {
                    $this->notifyLevelUp($user, $levelBefore, $newLevel);
                }
            }

            $userXp->save();

            // Get level data
            $levelBeforeObj = Level::where('level_number', $levelBefore)->first();
            $levelAfterObj = Level::where('level_number', $newLevel?->level_number ?? $levelBefore)->first();

            $result['level_before'] = $levelBeforeObj?->toArray() ?? null;
            $result['level_after'] = $levelAfterObj?->toArray() ?? null;

            DB::commit();

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error awarding quiz completion XP: {$e->getMessage()}");
            $result['error'] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Award XP for a specific quiz component
     */
    protected function awardQuizComponentXp(
        User $user,
        UserXp $userXp,
        int $quizId,
        string $eventType,
        string $uniqueId
    ): int {
        $rule = XpRule::active()->where('key', $eventType)->first();
        if (!$rule) {
            return 0;
        }

        $xpAmount = $rule->xp_value;

        // Check limits
        if (!$this->checkDailyLimit($user, $xpAmount) || !$this->checkWeeklyLimit($user, $xpAmount)) {
            return 0;
        }

        if ($rule->daily_limit && !$this->checkRuleDailyLimit($user, $rule, $xpAmount)) {
            return 0;
        }

        // Prevent duplicates
        if (XpTransaction::where('unique_identifier', $uniqueId)->exists()) {
            return 0;
        }

        // Create transaction
        XpTransaction::create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'reference_type' => 'quiz',
            'reference_id' => $quizId,
            'xp_amount' => $xpAmount,
            'direction' => 'earned',
            'description' => $rule->description,
            'source' => 'system',
            'unique_identifier' => $uniqueId,
            'metadata' => ['rule_key' => $rule->key],
        ]);

        // Update totals
        $userXp->total_xp += $xpAmount;
        $userXp->xp_this_week += $xpAmount;
        $userXp->xp_this_month += $xpAmount;
        $userXp->last_xp_activity = now();

        return $xpAmount;
    }

    /**
     * Award XP for correct answers based on difficulty
     */
    protected function awardCorrectAnswerXp(
        User $user,
        UserXp $userXp,
        int $quizId,
        int $correctAnswers,
        int $totalQuestions,
        string $uniqueBaseId
    ): int {
        if ($correctAnswers <= 0) {
            return 0;
        }

        $quizSettings = QuizXpSetting::where('quiz_id', $quizId)->first();
        if (!$quizSettings || !$quizSettings->use_global_rules) {
            return 0;
        }

        // Get difficulty-based XP per correct answer
        $difficultyMap = [
            'easy' => 5,
            'medium' => 10,
            'hard' => 15,
        ];

        $quiz = \App\Models\Quiz::find($quizId);
        if (!$quiz) {
            return 0;
        }

        $xpPerAnswer = $difficultyMap[$quiz->difficulty] ?? 10;
        $totalXp = $correctAnswers * $xpPerAnswer;

        // Check limits
        if (!$this->checkDailyLimit($user, $totalXp) || !$this->checkWeeklyLimit($user, $totalXp)) {
            return 0;
        }

        $uniqueId = $uniqueBaseId . '_correct_answers';

        if (XpTransaction::where('unique_identifier', $uniqueId)->exists()) {
            return 0;
        }

        // Create transaction
        XpTransaction::create([
            'user_id' => $user->id,
            'event_type' => 'correct_answer',
            'reference_type' => 'quiz',
            'reference_id' => $quizId,
            'xp_amount' => $totalXp,
            'direction' => 'earned',
            'description' => "Earned $totalXp XP for $correctAnswers correct answers",
            'source' => 'system',
            'unique_identifier' => $uniqueId,
            'metadata' => [
                'correct_answers' => $correctAnswers,
                'xp_per_answer' => $xpPerAnswer,
            ],
        ]);

        // Update totals
        $userXp->total_xp += $totalXp;
        $userXp->xp_this_week += $totalXp;
        $userXp->xp_this_month += $totalXp;

        return $totalXp;
    }

    /**
     * Generate unique identifier for preventing duplicate XP
     */
    protected function generateUniqueIdentifier(
        int $userId,
        string $eventType,
        ?string $referenceType,
        ?int $referenceId
    ): string {
        $parts = [$userId, $eventType];

        if ($referenceType && $referenceId) {
            $parts[] = "{$referenceType}_{$referenceId}";
        }

        // Add day component to allow daily limits
        $parts[] = now()->format('Y-m-d');

        return implode('_', $parts);
    }

    /**
     * Notify user of XP earned
     */
    protected function notifyXpEarned(User $user, int $xp, string $ruleName): void {
        // TODO: Implement notification
    }

    /**
     * Notify user of level up
     */
    protected function notifyLevelUp(User $user, int $oldLevel, Level $newLevel): void {
        // TODO: Implement notification
    }

    /**
     * Reset weekly and monthly XP counts
     */
    public function resetPeriodicalXp(): void {
        $startOfWeek = now()->startOfWeek();
        $startOfMonth = now()->startOfMonth();

        // Reset weekly XP if it's a new week
        UserXp::whereRaw("DATE(updated_at) < DATE(?)", [$startOfWeek->toDateString()])
            ->update(['xp_this_week' => 0]);

        // Reset monthly XP if it's a new month
        UserXp::whereRaw("DATE(updated_at) < DATE(?)", [$startOfMonth->toDateString()])
            ->update(['xp_this_month' => 0]);
    }

    /**
     * Get total XP stats
     */
    public function getTotalXpStats() {
        return [
            'total_awarded' => XpTransaction::where('direction', 'earned')->sum('xp_amount'),
            'today' => XpTransaction::where('direction', 'earned')
                ->whereDate('created_at', today())
                ->sum('xp_amount'),
            'this_week' => XpTransaction::where('direction', 'earned')
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('xp_amount'),
            'this_month' => XpTransaction::where('direction', 'earned')
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('xp_amount'),
        ];
    }

    /**
     * Get top users by XP
     */
    public function getTopUsers(int $limit = 10) {
        return UserXp::with('user')
            ->where('total_xp', '>', 0)
            ->orderBy('total_xp', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get user XP history
     */
    public function getUserXpHistory(User $user, int $limit = 50) {
        return XpTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
