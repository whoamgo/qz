<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\XpTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BadgeService {
    /**
     * Check and award badges to user
     */
    public function checkAndAwardBadges(User $user): void {
        $badges = Badge::active()->get();

        foreach ($badges as $badge) {
            if ($this->userHasEarned($user, $badge)) {
                continue;
            }

            if ($this->checkCondition($user, $badge)) {
                $this->awardBadge($user, $badge);
            }
        }
    }

    /**
     * Award badge to user
     */
    public function awardBadge(User $user, Badge $badge): bool {
        try {
            DB::beginTransaction();

            $userBadge = UserBadge::create([
                'user_id' => $user->id,
                'badge_id' => $badge->id,
                'earned_at' => now(),
            ]);

            // Award badge XP
            if ($badge->reward_xp > 0) {
                $xpService = new XpService();
                $xpService->awardXp(
                    $user,
                    'badge_earned',
                    'badge',
                    $badge->id
                );
            }

            // Increment times earned
            $badge->increment('times_earned');

            DB::commit();

            // Notify user
            $this->notifyBadgeEarned($user, $badge);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error awarding badge {$badge->id} to user {$user->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check if user has already earned badge
     */
    public function userHasEarned(User $user, Badge $badge): bool {
        return UserBadge::where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->exists();
    }

    /**
     * Check if user meets badge condition
     */
    protected function checkCondition(User $user, Badge $badge): bool {
        $conditionType = $badge->condition_type;
        $conditionData = $badge->condition_data;

        return match ($conditionType) {
            'quiz_count' => $this->checkQuizCount($user, $conditionData),
            'question_count' => $this->checkQuestionCount($user, $conditionData),
            'correct_answer_count' => $this->checkCorrectAnswerCount($user, $conditionData),
            'perfect_score_count' => $this->checkPerfectScoreCount($user, $conditionData),
            'streak_days' => $this->checkStreakDays($user, $conditionData),
            'total_xp' => $this->checkTotalXp($user, $conditionData),
            'category_quiz_count' => $this->checkCategoryQuizCount($user, $conditionData),
            'exam_quiz_count' => $this->checkExamQuizCount($user, $conditionData),
            'leaderboard_rank' => $this->checkLeaderboardRank($user, $conditionData),
            default => false,
        };
    }

    protected function checkQuizCount(User $user, array $data): bool {
        $count = XpTransaction::where('user_id', $user->id)
            ->where('event_type', 'quiz_completed')
            ->distinct('reference_id')
            ->count('reference_id');

        return $count >= ($data['required_count'] ?? 0);
    }

    protected function checkQuestionCount(User $user, array $data): bool {
        $count = XpTransaction::where('user_id', $user->id)
            ->where('event_type', 'like', 'correct_%_answer')
            ->count();

        return $count >= ($data['required_count'] ?? 0);
    }

    protected function checkCorrectAnswerCount(User $user, array $data): bool {
        $count = XpTransaction::where('user_id', $user->id)
            ->where('event_type', 'like', 'correct_%_answer')
            ->count();

        return $count >= ($data['required_count'] ?? 0);
    }

    protected function checkPerfectScoreCount(User $user, array $data): bool {
        $count = XpTransaction::where('user_id', $user->id)
            ->where('event_type', 'perfect_score')
            ->distinct('reference_id')
            ->count('reference_id');

        return $count >= ($data['required_count'] ?? 0);
    }

    protected function checkStreakDays(User $user, array $data): bool {
        $streak = $user->streak?->current_streak ?? 0;
        return $streak >= ($data['required_days'] ?? 0);
    }

    protected function checkTotalXp(User $user, array $data): bool {
        $totalXp = $user->xpProfile?->total_xp ?? 0;
        return $totalXp >= ($data['required_xp'] ?? 0);
    }

    protected function checkCategoryQuizCount(User $user, array $data): bool {
        // TODO: Implement when category quiz count available
        return false;
    }

    protected function checkExamQuizCount(User $user, array $data): bool {
        // TODO: Implement when exam quiz count available
        return false;
    }

    protected function checkLeaderboardRank(User $user, array $data): bool {
        // TODO: Implement leaderboard rank check
        return false;
    }

    /**
     * Notify user of badge earned
     */
    protected function notifyBadgeEarned(User $user, Badge $badge): void {
        // TODO: Implement notification
    }

    /**
     * Get user's badges
     */
    public function getUserBadges(User $user) {
        return UserBadge::where('user_id', $user->id)
            ->with('badge')
            ->orderBy('earned_at', 'desc')
            ->get();
    }

    /**
     * Get badge progress for user
     */
    public function getBadgeProgress(User $user, Badge $badge): array {
        if ($this->userHasEarned($user, $badge)) {
            return [
                'earned' => true,
                'progress' => 100,
                'earned_at' => UserBadge::where('user_id', $user->id)
                    ->where('badge_id', $badge->id)
                    ->first()?->earned_at,
            ];
        }

        // Calculate progress based on condition type
        $progress = $this->calculateProgress($user, $badge);

        return [
            'earned' => false,
            'progress' => $progress,
            'earned_at' => null,
        ];
    }

    protected function calculateProgress(User $user, Badge $badge): int {
        // TODO: Implement progress calculation based on condition type
        return 0;
    }
}
