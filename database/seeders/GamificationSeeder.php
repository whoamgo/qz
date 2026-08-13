<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\GamificationSetting;
use App\Models\Level;
use App\Models\XpRule;
use Illuminate\Database\Seeder;

class GamificationSeeder extends Seeder {
    public function run(): void {
        $this->seedXpRules();
        $this->seedLevels();
        $this->seedBadges();
        $this->seedGamificationSettings();
    }

    protected function seedXpRules(): void {
        $rules = [
            // Quiz & Answer Rules
            ['name' => 'Quiz Completed', 'key' => 'quiz_completed', 'description' => 'XP for completing a quiz', 'xp_value' => 20, 'category' => 'quiz', 'daily_limit' => 50, 'sort_order' => 1],
            ['name' => 'Correct Easy Answer', 'key' => 'correct_easy_answer', 'description' => 'XP for answering an easy question correctly', 'xp_value' => 1, 'category' => 'quiz', 'daily_limit' => 200, 'sort_order' => 2],
            ['name' => 'Correct Medium Answer', 'key' => 'correct_medium_answer', 'description' => 'XP for answering a medium question correctly', 'xp_value' => 2, 'category' => 'quiz', 'daily_limit' => 150, 'sort_order' => 3],
            ['name' => 'Correct Hard Answer', 'key' => 'correct_hard_answer', 'description' => 'XP for answering a hard question correctly', 'xp_value' => 3, 'category' => 'quiz', 'daily_limit' => 100, 'sort_order' => 4],
            ['name' => 'Correct Expert Answer', 'key' => 'correct_expert_answer', 'description' => 'XP for answering an expert question correctly', 'xp_value' => 5, 'category' => 'quiz', 'daily_limit' => 50, 'sort_order' => 5],
            ['name' => 'Quiz Passed', 'key' => 'quiz_passed', 'description' => 'Bonus XP for passing a quiz', 'xp_value' => 20, 'category' => 'quiz', 'daily_limit' => 50, 'sort_order' => 6],
            ['name' => 'Perfect Score', 'key' => 'perfect_score', 'description' => 'Bonus XP for getting a perfect score', 'xp_value' => 50, 'category' => 'quiz', 'daily_limit' => 20, 'sort_order' => 7],
            ['name' => 'First Attempt', 'key' => 'first_attempt', 'description' => 'Bonus XP for completing on first attempt', 'xp_value' => 10, 'category' => 'quiz', 'daily_limit' => 50, 'sort_order' => 8],
            ['name' => 'Daily Quiz Completed', 'key' => 'daily_quiz_completed', 'description' => 'Daily quiz completion bonus', 'xp_value' => 20, 'category' => 'quiz', 'daily_limit' => 1, 'sort_order' => 9],
            ['name' => 'Weekly Quiz Completed', 'key' => 'weekly_quiz_completed', 'description' => 'Weekly quiz completion bonus', 'xp_value' => 30, 'category' => 'quiz', 'daily_limit' => 7, 'sort_order' => 10],
            ['name' => 'Monthly Quiz Completed', 'key' => 'monthly_quiz_completed', 'description' => 'Monthly quiz completion bonus', 'xp_value' => 50, 'category' => 'quiz', 'daily_limit' => 1, 'sort_order' => 11],
            ['name' => 'Mock Test Completed', 'key' => 'mock_test_completed', 'description' => 'Bonus XP for completing mock test', 'xp_value' => 50, 'category' => 'quiz', 'daily_limit' => 10, 'sort_order' => 12],
            ['name' => 'PYQ Completed', 'key' => 'pyq_completed', 'description' => 'Bonus XP for completing previous year questions', 'xp_value' => 30, 'category' => 'quiz', 'daily_limit' => 20, 'sort_order' => 13],

            // Streak Rules
            ['name' => '3 Day Streak', 'key' => 'streak_3_days', 'description' => 'Bonus XP for 3 day streak', 'xp_value' => 20, 'category' => 'streak', 'cooldown_minutes' => 1440, 'sort_order' => 14],
            ['name' => '7 Day Streak', 'key' => 'streak_7_days', 'description' => 'Bonus XP for 7 day streak', 'xp_value' => 50, 'category' => 'streak', 'cooldown_minutes' => 1440, 'sort_order' => 15],
            ['name' => '14 Day Streak', 'key' => 'streak_14_days', 'description' => 'Bonus XP for 14 day streak', 'xp_value' => 100, 'category' => 'streak', 'cooldown_minutes' => 1440, 'sort_order' => 16],
            ['name' => '30 Day Streak', 'key' => 'streak_30_days', 'description' => 'Bonus XP for 30 day streak', 'xp_value' => 250, 'category' => 'streak', 'cooldown_minutes' => 1440, 'sort_order' => 17],
            ['name' => '60 Day Streak', 'key' => 'streak_60_days', 'description' => 'Bonus XP for 60 day streak', 'xp_value' => 500, 'category' => 'streak', 'cooldown_minutes' => 1440, 'sort_order' => 18],
            ['name' => '100 Day Streak', 'key' => 'streak_100_days', 'description' => 'Bonus XP for 100 day streak', 'xp_value' => 1000, 'category' => 'streak', 'cooldown_minutes' => 1440, 'sort_order' => 19],

            // Learning Rules
            ['name' => 'Weak Topic Practice', 'key' => 'weak_topic_practice', 'description' => 'Bonus XP for practicing weak topics', 'xp_value' => 10, 'category' => 'learning', 'daily_limit' => 50, 'sort_order' => 20],

            // Other Rules
            ['name' => 'First Quiz', 'key' => 'first_quiz', 'description' => 'One-time bonus for first quiz', 'xp_value' => 50, 'category' => 'other', 'daily_limit' => 1, 'sort_order' => 21],
            ['name' => 'Challenge Won', 'key' => 'challenge_won', 'description' => 'Bonus XP for winning a challenge', 'xp_value' => 30, 'category' => 'other', 'daily_limit' => 10, 'sort_order' => 22],
        ];

        foreach ($rules as $rule) {
            XpRule::updateOrCreate(['key' => $rule['key']], $rule);
        }
    }

    protected function seedLevels(): void {
        $levels = [
            ['level_number' => 1, 'name' => 'Beginner', 'required_xp' => 0, 'badge_color' => '#808080', 'reward_xp' => 0],
            ['level_number' => 2, 'name' => 'Learner', 'required_xp' => 100, 'badge_color' => '#4169E1', 'reward_xp' => 50],
            ['level_number' => 3, 'name' => 'Explorer', 'required_xp' => 300, 'badge_color' => '#32CD32', 'reward_xp' => 100],
            ['level_number' => 4, 'name' => 'Scholar', 'required_xp' => 600, 'badge_color' => '#FFD700', 'reward_xp' => 150],
            ['level_number' => 5, 'name' => 'Expert', 'required_xp' => 1000, 'badge_color' => '#FF8C00', 'reward_xp' => 200],
            ['level_number' => 6, 'name' => 'Master', 'required_xp' => 2000, 'badge_color' => '#FF1493', 'reward_xp' => 300],
            ['level_number' => 7, 'name' => 'Pro', 'required_xp' => 3500, 'badge_color' => '#9370DB', 'reward_xp' => 400],
            ['level_number' => 8, 'name' => 'Champion', 'required_xp' => 5000, 'badge_color' => '#00CED1', 'reward_xp' => 500],
            ['level_number' => 9, 'name' => 'Legend', 'required_xp' => 7500, 'badge_color' => '#FFD700', 'reward_xp' => 600],
            ['level_number' => 10, 'name' => 'Quiz Master', 'required_xp' => 10000, 'badge_color' => '#FF4500', 'reward_xp' => 1000],
            ['level_number' => 11, 'name' => 'Grand Master', 'required_xp' => 15000, 'badge_color' => '#DC143C', 'reward_xp' => 1500],
            ['level_number' => 12, 'name' => 'Quiz Champion', 'required_xp' => 25000, 'badge_color' => '#8A2BE2', 'reward_xp' => 2500],
            ['level_number' => 13, 'name' => 'Quiz Legend', 'required_xp' => 40000, 'badge_color' => '#20B2AA', 'reward_xp' => 4000],
            ['level_number' => 14, 'name' => 'Knowledge King', 'required_xp' => 60000, 'badge_color' => '#FFD700', 'reward_xp' => 6000],
            ['level_number' => 15, 'name' => 'Quiz Emperor', 'required_xp' => 100000, 'badge_color' => '#FF69B4', 'reward_xp' => 10000],
        ];

        foreach ($levels as $level) {
            Level::updateOrCreate(['level_number' => $level['level_number']], $level);
        }
    }

    protected function seedBadges(): void {
        $badges = [
            // Quiz Count Badges
            ['name' => 'First Quiz', 'slug' => 'first-quiz', 'description' => 'Complete your first quiz', 'condition_type' => 'quiz_count', 'condition_data' => ['required_count' => 1], 'reward_xp' => 50, 'sort_order' => 1],
            ['name' => 'Quiz Starter', 'slug' => 'quiz-starter', 'description' => 'Complete 5 quizzes', 'condition_type' => 'quiz_count', 'condition_data' => ['required_count' => 5], 'reward_xp' => 75, 'sort_order' => 2],

            // Question Count Badges
            ['name' => '100 Questions', 'slug' => '100-questions', 'description' => 'Answer 100 questions correctly', 'condition_type' => 'correct_answer_count', 'condition_data' => ['required_count' => 100], 'reward_xp' => 100, 'sort_order' => 3],
            ['name' => '500 Questions', 'slug' => '500-questions', 'description' => 'Answer 500 questions correctly', 'condition_type' => 'correct_answer_count', 'condition_data' => ['required_count' => 500], 'reward_xp' => 200, 'sort_order' => 4],
            ['name' => '1000 Questions', 'slug' => '1000-questions', 'description' => 'Answer 1000 questions correctly', 'condition_type' => 'correct_answer_count', 'condition_data' => ['required_count' => 1000], 'reward_xp' => 300, 'sort_order' => 5],
            ['name' => '2500 Questions', 'slug' => '2500-questions', 'description' => 'Answer 2500 questions correctly', 'condition_type' => 'correct_answer_count', 'condition_data' => ['required_count' => 2500], 'reward_xp' => 500, 'sort_order' => 6],
            ['name' => '5000 Questions', 'slug' => '5000-questions', 'description' => 'Answer 5000 questions correctly', 'condition_type' => 'correct_answer_count', 'condition_data' => ['required_count' => 5000], 'reward_xp' => 1000, 'sort_order' => 7],

            // Streak Badges
            ['name' => '7 Day Streak', 'slug' => '7-day-streak', 'description' => 'Maintain a 7-day streak', 'condition_type' => 'streak_days', 'condition_data' => ['required_days' => 7], 'reward_xp' => 150, 'sort_order' => 8],
            ['name' => '14 Day Streak', 'slug' => '14-day-streak', 'description' => 'Maintain a 14-day streak', 'condition_type' => 'streak_days', 'condition_data' => ['required_days' => 14], 'reward_xp' => 250, 'sort_order' => 9],
            ['name' => '30 Day Streak', 'slug' => '30-day-streak', 'description' => 'Maintain a 30-day streak', 'condition_type' => 'streak_days', 'condition_data' => ['required_days' => 30], 'reward_xp' => 500, 'sort_order' => 10],
            ['name' => '60 Day Streak', 'slug' => '60-day-streak', 'description' => 'Maintain a 60-day streak', 'condition_type' => 'streak_days', 'condition_data' => ['required_days' => 60], 'reward_xp' => 1000, 'sort_order' => 11],
            ['name' => '100 Day Streak', 'slug' => '100-day-streak', 'description' => 'Maintain a 100-day streak', 'condition_type' => 'streak_days', 'condition_data' => ['required_days' => 100], 'reward_xp' => 2000, 'sort_order' => 12],

            // Perfect Score Badges
            ['name' => 'Perfect Score', 'slug' => 'perfect-score', 'description' => 'Get a perfect score', 'condition_type' => 'perfect_score_count', 'condition_data' => ['required_count' => 1], 'reward_xp' => 200, 'sort_order' => 13],
            ['name' => 'Perfect 5', 'slug' => 'perfect-5', 'description' => 'Get 5 perfect scores', 'condition_type' => 'perfect_score_count', 'condition_data' => ['required_count' => 5], 'reward_xp' => 300, 'sort_order' => 14],
            ['name' => 'Perfect 10', 'slug' => 'perfect-10', 'description' => 'Get 10 perfect scores', 'condition_type' => 'perfect_score_count', 'condition_data' => ['required_count' => 10], 'reward_xp' => 500, 'sort_order' => 15],
            ['name' => 'Perfect 25', 'slug' => 'perfect-25', 'description' => 'Get 25 perfect scores', 'condition_type' => 'perfect_score_count', 'condition_data' => ['required_count' => 25], 'reward_xp' => 1000, 'sort_order' => 16],

            // GK Badges
            ['name' => 'GK Beginner', 'slug' => 'gk-beginner', 'description' => 'Complete 5 GK quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'General Knowledge', 'required_count' => 5], 'reward_xp' => 100, 'sort_order' => 17],
            ['name' => 'GK Expert', 'slug' => 'gk-expert', 'description' => 'Complete 20 GK quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'General Knowledge', 'required_count' => 20], 'reward_xp' => 300, 'sort_order' => 18],
            ['name' => 'GK Master', 'slug' => 'gk-master', 'description' => 'Complete 50 GK quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'General Knowledge', 'required_count' => 50], 'reward_xp' => 500, 'sort_order' => 19],

            // SSC Badges
            ['name' => 'SSC Beginner', 'slug' => 'ssc-beginner', 'description' => 'Complete 5 SSC quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'SSC', 'required_count' => 5], 'reward_xp' => 100, 'sort_order' => 20],
            ['name' => 'SSC Expert', 'slug' => 'ssc-expert', 'description' => 'Complete 20 SSC quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'SSC', 'required_count' => 20], 'reward_xp' => 300, 'sort_order' => 21],
            ['name' => 'SSC Master', 'slug' => 'ssc-master', 'description' => 'Complete 50 SSC quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'SSC', 'required_count' => 50], 'reward_xp' => 500, 'sort_order' => 22],

            // Banking Badges
            ['name' => 'Banking Beginner', 'slug' => 'banking-beginner', 'description' => 'Complete 5 Banking quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'Banking', 'required_count' => 5], 'reward_xp' => 100, 'sort_order' => 23],
            ['name' => 'Banking Expert', 'slug' => 'banking-expert', 'description' => 'Complete 20 Banking quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'Banking', 'required_count' => 20], 'reward_xp' => 300, 'sort_order' => 24],
            ['name' => 'Banking Master', 'slug' => 'banking-master', 'description' => 'Complete 50 Banking quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'Banking', 'required_count' => 50], 'reward_xp' => 500, 'sort_order' => 25],

            // UPSC Badges
            ['name' => 'UPSC Beginner', 'slug' => 'upsc-beginner', 'description' => 'Complete 5 UPSC quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'UPSC', 'required_count' => 5], 'reward_xp' => 100, 'sort_order' => 26],
            ['name' => 'UPSC Expert', 'slug' => 'upsc-expert', 'description' => 'Complete 20 UPSC quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'UPSC', 'required_count' => 20], 'reward_xp' => 300, 'sort_order' => 27],
            ['name' => 'UPSC Master', 'slug' => 'upsc-master', 'description' => 'Complete 50 UPSC quizzes', 'condition_type' => 'category_quiz_count', 'condition_data' => ['category' => 'UPSC', 'required_count' => 50], 'reward_xp' => 500, 'sort_order' => 28],

            // Quiz Level Badges
            ['name' => 'Quiz Master', 'slug' => 'quiz-master', 'description' => 'Reach Level 10', 'condition_type' => 'total_xp', 'condition_data' => ['required_xp' => 10000], 'reward_xp' => 0, 'sort_order' => 29],
            ['name' => 'Quiz Champion', 'slug' => 'quiz-champion', 'description' => 'Reach Level 12', 'condition_type' => 'total_xp', 'condition_data' => ['required_xp' => 25000], 'reward_xp' => 0, 'sort_order' => 30],
            ['name' => 'Quiz Legend', 'slug' => 'quiz-legend', 'description' => 'Reach Level 13', 'condition_type' => 'total_xp', 'condition_data' => ['required_xp' => 40000], 'reward_xp' => 0, 'sort_order' => 31],

            // Leaderboard Badges
            ['name' => 'Top 100', 'slug' => 'top-100', 'description' => 'Reach Top 100 in leaderboard', 'condition_type' => 'leaderboard_rank', 'condition_data' => ['required_rank' => 100], 'reward_xp' => 100, 'sort_order' => 32],
            ['name' => 'Top 50', 'slug' => 'top-50', 'description' => 'Reach Top 50 in leaderboard', 'condition_type' => 'leaderboard_rank', 'condition_data' => ['required_rank' => 50], 'reward_xp' => 250, 'sort_order' => 33],
            ['name' => 'Top 10', 'slug' => 'top-10', 'description' => 'Reach Top 10 in leaderboard', 'condition_type' => 'leaderboard_rank', 'condition_data' => ['required_rank' => 10], 'reward_xp' => 500, 'sort_order' => 34],
            ['name' => 'Number 1', 'slug' => 'number-1', 'description' => 'Reach #1 in leaderboard', 'condition_type' => 'leaderboard_rank', 'condition_data' => ['required_rank' => 1], 'reward_xp' => 1000, 'sort_order' => 35],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(['slug' => $badge['slug']], $badge);
        }
    }

    protected function seedGamificationSettings(): void {
        GamificationSetting::updateOrCreate(
            [],
            [
                'xp_system_enabled' => true,
                'levels_enabled' => true,
                'badges_enabled' => true,
                'streaks_enabled' => true,
                'leaderboard_enabled' => true,
                'daily_xp_cap' => 300,
                'weekly_xp_cap' => 1500,
                'max_xp_per_quiz' => 500,
                'first_attempt_percentage' => 100,
                'second_attempt_percentage' => 50,
                'third_plus_attempt_percentage' => 0,
                'notify_xp_earned' => true,
                'notify_level_up' => true,
                'notify_badge_earned' => true,
                'notify_streak' => true,
                'leaderboard_sort_by' => 'xp',
                'leaderboard_period' => 2,
                'leaderboard_users_shown' => 100,
            ]
        );
    }
}
