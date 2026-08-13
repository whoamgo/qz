<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamificationSetting extends Model {
    protected $table = 'gamification_settings';
    protected $fillable = [
        'is_enabled',
        'xp_system_enabled',
        'levels_enabled',
        'badges_enabled',
        'streaks_enabled',
        'leaderboard_enabled',
        'daily_xp_cap',
        'weekly_xp_cap',
        'max_xp_per_quiz',
        'first_attempt_percentage',
        'second_attempt_percentage',
        'third_plus_attempt_percentage',
        'notify_xp_earned',
        'notify_level_up',
        'notify_badge_earned',
        'notify_streak',
        'leaderboard_sort_by',
        'leaderboard_period',
        'leaderboard_users_shown',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'xp_system_enabled' => 'boolean',
        'levels_enabled' => 'boolean',
        'badges_enabled' => 'boolean',
        'streaks_enabled' => 'boolean',
        'leaderboard_enabled' => 'boolean',
        'notify_xp_earned' => 'boolean',
        'notify_level_up' => 'boolean',
        'notify_badge_earned' => 'boolean',
        'notify_streak' => 'boolean',
    ];

    public static function get() {
        return self::first() ?? self::create([]);
    }

    public static function getInstance() {
        return self::get();
    }
}
