<?php

namespace App\Services;

use App\Models\User;
use App\Models\Quiz;
use App\Models\Level;
use App\Models\Badge;
use Illuminate\Support\Facades\DB;

class GamificationService {
    private XpService $xpService;
    private StreakService $streakService;
    private BadgeService $badgeService;
    private User $user;

    public function __construct(User $user) {
        $this->user = $user;
        $this->xpService = new XpService();
        $this->streakService = new StreakService();
        $this->badgeService = new BadgeService();
    }

    /**
     * Process quiz completion and award all XP, badges, and streaks
     */
    public function processQuizCompletion(
        Quiz $quiz,
        int $correctAnswers,
        int $totalQuestions,
        bool $passed,
        bool $isFirstAttempt
    ): array {
        try {
            DB::beginTransaction();

            // Check if this is actually the first attempt
            $attemptCount = DB::table('xp_transactions')
                ->where('user_id', $this->user->id)
                ->where('reference_type', 'quiz')
                ->where('reference_id', $quiz->id)
                ->where('event_type', 'quiz_completed')
                ->count();

            $isFirstAttempt = $attemptCount === 0;
            $perfect = $correctAnswers === $totalQuestions;

            // Reload user XP profile to get current state
            $this->user = $this->user->fresh();

            // Award XP for quiz completion
            $xpResult = $this->xpService->awardQuizCompletionXp(
                $this->user,
                $quiz->id,
                $correctAnswers,
                $totalQuestions,
                $passed,
                $perfect,
                $isFirstAttempt
            );

            // Reload user XP after XP award
            $this->user = $this->user->fresh();

            // Update streak
            $this->streakService->updateStreak($this->user);

            // Check for new badges
            $newBadges = $this->badgeService->checkAndAwardBadges($this->user);

            // Prepare result
            $result = [
                'total_xp_earned' => $xpResult['total_xp'],
                'xp_breakdown' => [
                    'quiz_completion' => $xpResult['breakdown']['completion'],
                    'correct_answers' => $xpResult['breakdown']['correct_answers'],
                    'passing_bonus' => $xpResult['breakdown']['passing_bonus'],
                    'perfect_bonus' => $xpResult['breakdown']['perfect_bonus'],
                    'first_attempt_bonus' => $xpResult['breakdown']['first_attempt_bonus'],
                ],
                'level_before' => $xpResult['level_before'],
                'level_after' => $xpResult['level_after'],
                'level_up' => $xpResult['level_up'],
                'new_badges' => $newBadges->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'icon' => $b->icon,
                    'color' => $b->color,
                    'reward_xp' => $b->reward_xp,
                ])->toArray(),
                'streak_info' => $this->getStreakInfo(),
            ];

            DB::commit();

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'error' => $e->getMessage(),
                'total_xp_earned' => 0,
                'xp_breakdown' => [],
                'level_up' => false,
                'new_badges' => [],
                'streak_info' => [],
            ];
        }
    }

    /**
     * Get complete user gamification status
     */
    public function getUserGamificationStatus(): array {
        $this->user = $this->user->fresh();
        $userXp = $this->user->xpProfile;

        // Get current level by XP
        $currentLevel = Level::getLevelByXp($userXp?->total_xp ?? 0);

        // Get next level
        $nextLevel = Level::active()
            ->where('required_xp', '>', $currentLevel?->required_xp ?? 0)
            ->orderBy('required_xp', 'asc')
            ->first();

        return [
            'total_xp' => $userXp?->total_xp ?? 0,
            'current_level' => $currentLevel ? [
                'id' => $currentLevel->id,
                'level_number' => $currentLevel->level_number,
                'name' => $currentLevel->name,
                'icon' => $currentLevel->badge_icon,
                'color' => $currentLevel->badge_color,
                'required_xp' => $currentLevel->required_xp,
            ] : null,
            'next_level' => $nextLevel ? [
                'id' => $nextLevel->id,
                'level_number' => $nextLevel->level_number,
                'name' => $nextLevel->name,
                'icon' => $nextLevel->badge_icon,
                'color' => $nextLevel->badge_color,
                'required_xp' => $nextLevel->required_xp,
                'xp_required' => max(0, $nextLevel->required_xp - ($userXp?->total_xp ?? 0)),
            ] : null,
            'streak' => $this->getStreakInfo(),
            'badges' => $this->user->badges()
                ->get(['badges.*'])
                ->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'icon' => $b->icon,
                    'color' => $b->color,
                    'reward_xp' => $b->reward_xp,
                    'earned_at' => $b->pivot->earned_at ?? null,
                ])
                ->toArray(),
        ];
    }

    /**
     * Get streak information
     */
    private function getStreakInfo(): ?array {
        $streak = $this->user->streak;
        if (!$streak) {
            return null;
        }

        return [
            'current' => $streak->current_count,
            'longest' => $streak->longest_count,
            'last_activity' => $streak->last_activity_date,
            'streak_started_at' => $streak->streak_started_at,
        ];
    }
}
