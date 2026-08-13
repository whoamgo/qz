<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserStreak;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StreakService {
    /**
     * Update user streak based on activity
     */
    public function updateStreak(User $user): ?UserStreak {
        $streak = $user->streak ?? UserStreak::firstOrCreate(
            ['user_id' => $user->id],
            [
                'current_streak' => 0,
                'longest_streak' => 0,
                'last_activity' => null,
                'streak_started_at' => null,
            ]
        );

        $lastActivity = $streak->last_activity?->toDateString();
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();

        // If activity is today, don't increment (only count once per day)
        if ($lastActivity === $today) {
            return $streak;
        }

        // If last activity was yesterday, continue the streak
        if ($lastActivity === $yesterday) {
            $streak->current_streak += 1;
        } else {
            // Streak broken or first activity
            $streak->current_streak = 1;
            $streak->streak_started_at = now();
        }

        // Update longest streak if current is higher
        if ($streak->current_streak > $streak->longest_streak) {
            $streak->longest_streak = $streak->current_streak;
        }

        $streak->last_activity = now();
        $streak->save();

        return $streak;
    }

    /**
     * Break user's streak
     */
    public function breakStreak(User $user): void {
        $streak = $user->streak;
        if ($streak) {
            $streak->current_streak = 0;
            $streak->streak_started_at = null;
            $streak->save();
        }
    }

    /**
     * Get user's current streak
     */
    public function getCurrentStreak(User $user): int {
        $streak = $user->streak;
        if (!$streak || !$streak->last_activity) {
            return 0;
        }

        // If last activity was more than 1 day ago, streak is broken
        if ($streak->last_activity->diffInDays(now()) > 1) {
            return 0;
        }

        return $streak->current_streak;
    }

    /**
     * Get user's longest streak
     */
    public function getLongestStreak(User $user): int {
        return $user->streak?->longest_streak ?? 0;
    }

    /**
     * Check and break expired streaks (cron job)
     */
    public function breakExpiredStreaks(): void {
        $yesterday = today()->subDay()->toDateString();

        $expiredStreaks = UserStreak::where('current_streak', '>', 0)
            ->where(function ($query) use ($yesterday) {
                $query->whereNull('last_activity')
                    ->orWhereRaw("DATE(last_activity) < '{$yesterday}'");
            })
            ->get();

        foreach ($expiredStreaks as $streak) {
            $streak->current_streak = 0;
            $streak->streak_started_at = null;
            $streak->save();
        }

        Log::info("Broke " . $expiredStreaks->count() . " expired streaks");
    }
}
