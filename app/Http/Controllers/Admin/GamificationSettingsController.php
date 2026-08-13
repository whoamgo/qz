<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GamificationSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GamificationSettingsController extends Controller {
    public function index(): View {
        $pageTitle = "Gamification Settings";
        $settings = GamificationSetting::get();

        $leaderboardPeriods = [
            1 => 'Daily',
            2 => 'Weekly',
            3 => 'Monthly',
            4 => 'All Time',
        ];

        $sortByOptions = [
            'xp' => 'Total XP',
            'accuracy' => 'Quiz Accuracy',
            'quiz_count' => 'Quizzes Completed',
        ];

        return view('admin.gamification.settings.index', compact(
            'pageTitle',
            'settings',
            'leaderboardPeriods',
            'sortByOptions'
        ));
    }

    public function update(Request $request) {
        $request->validate([
            'xp_system_enabled' => 'required|boolean',
            'levels_enabled' => 'required|boolean',
            'badges_enabled' => 'required|boolean',
            'streaks_enabled' => 'required|boolean',
            'leaderboard_enabled' => 'required|boolean',
            'daily_xp_cap' => 'required|integer|min:100',
            'weekly_xp_cap' => 'required|integer|min:100',
            'max_xp_per_quiz' => 'required|integer|min:10',
            'first_attempt_percentage' => 'required|integer|min:0|max:100',
            'second_attempt_percentage' => 'required|integer|min:0|max:100',
            'third_plus_attempt_percentage' => 'required|integer|min:0|max:100',
            'notify_xp_earned' => 'required|boolean',
            'notify_level_up' => 'required|boolean',
            'notify_badge_earned' => 'required|boolean',
            'notify_streak' => 'required|boolean',
            'leaderboard_sort_by' => 'required|in:xp,accuracy,quiz_count',
            'leaderboard_period' => 'required|in:1,2,3,4',
            'leaderboard_users_shown' => 'required|integer|min:10|max:1000',
        ]);

        $settings = GamificationSetting::get();
        $settings->update($request->all());

        $notify[] = ['success', 'Gamification settings updated successfully'];
        return back()->withNotify($notify);
    }
}
