# XP & Gamification System Setup Guide

## Overview

This document provides complete setup and integration instructions for the XP & Gamification Management module.

## Quick Setup

### 1. Run Migrations

```bash
php artisan migrate
```

This creates all required XP & Gamification tables:
- `xp_rules` - XP earning rules
- `user_xp` - User XP profiles
- `xp_transactions` - XP transaction ledger
- `levels` - Level definitions
- `badges` - Badge definitions
- `user_badges` - User earned badges
- `user_streaks` - User streak tracking
- `gamification_settings` - Global configuration
- `quiz_xp_settings` - Quiz-specific XP settings
- `user_xp_claims` - Daily/weekly bonus claims

### 2. Seed Default Data

```bash
php artisan db:seed --class=GamificationSeeder
```

This creates:
- 16 default XP rules (Quiz, Streak, Other categories)
- 10 level definitions (Beginner → Quiz Master)
- 5 default badges
- Global gamification settings

### 3. Access Admin Panel

Navigate to: `Admin → Gamification`

You'll see the following sections:
- **XP Dashboard** - Statistics and insights
- **XP Rules** - Manage XP earning rules
- **User XP** - Manage individual user XP
- **XP Transactions** - Transaction ledger
- **Levels** - Configure level thresholds
- **Badges & Rewards** - Manage badge conditions
- **Settings** - Global configuration

---

## Integration with Quiz System

### Award XP When Quiz is Completed

Add this to the Quiz Result/Completion endpoint in `ExamController`:

```php
<?php
use App\Services\XpService;

class ExamController extends Controller {
    protected $xpService;

    public function __construct() {
        $this->xpService = new XpService();
    }

    public function submitQuiz(Request $request, $examId) {
        // ... existing quiz logic ...

        $exam = Exam::findOrFail($examId);
        $user = auth()->user();
        $score = $this->calculateScore($request); // Your scoring logic
        $passed = $score >= $exam->pass_percentage;

        // Award XP for quiz completion
        $this->xpService->awardXp(
            $user,
            'quiz_completed',
            'quiz',
            $examId
        );

        // Award XP for each correct answer (by difficulty)
        foreach ($request->answers as $questionId => $selectedOption) {
            $question = Question::findOrFail($questionId);
            $isCorrect = $this->isAnswerCorrect($question, $selectedOption);

            if ($isCorrect) {
                $eventKey = match($question->difficulty ?? 'medium') {
                    'easy' => 'correct_easy_answer',
                    'hard' => 'correct_hard_answer',
                    'expert' => 'correct_expert_answer',
                    default => 'correct_medium_answer',
                };

                $this->xpService->awardXp(
                    $user,
                    $eventKey,
                    'question',
                    $questionId
                );
            }
        }

        // Award XP for passing
        if ($passed) {
            $this->xpService->awardXp(
                $user,
                'quiz_passed',
                'quiz',
                $examId
            );

            // Award XP for perfect score
            if ($score == 100) {
                $this->xpService->awardXp(
                    $user,
                    'perfect_score',
                    'quiz',
                    $examId
                );
            }
        }

        return response()->json(['success' => true, 'score' => $score]);
    }
}
```

---

## Using the XP Service

### Award XP

```php
use App\Services\XpService;

$xpService = new XpService();

// Simple XP award
$xpService->awardXp(
    $user,
    'quiz_completed', // Rule key
    'quiz',           // Reference type
    123               // Reference ID
);

// With admin note
$xpService->awardXp(
    $user,
    'quiz_completed',
    'quiz',
    123,
    'Bonus for referral',
    'admin',
    $adminId
);
```

### Deduct XP

```php
$xpService->deductXp(
    $user,
    100,                    // Amount
    'Rule violation',       // Reason
    'User flagged',         // Admin note
    $adminId                // Admin ID
);
```

### Get User XP Stats

```php
$totalXpStats = $xpService->getTotalXpStats();
// Returns: ['total_awarded', 'today', 'this_week', 'this_month']

$topUsers = $xpService->getTopUsers(10);
// Returns: Top 10 users by XP

$history = $xpService->getUserXpHistory($user, 50);
// Returns: Last 50 XP transactions for user
```

---

## Streak System

### Update Streak on Activity

```php
use App\Services\StreakService;

$streakService = new StreakService();

// Called whenever user completes meaningful activity
$streakService->updateStreak($user);

// Get current streak
$current = $streakService->getCurrentStreak($user);

// Get longest streak
$longest = $streakService->getLongestStreak($user);
```

### Break Expired Streaks (Cron Job)

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule) {
    $schedule->daily()->call(function () {
        $streakService = new \App\Services\StreakService();
        $streakService->breakExpiredStreaks();
    });
}
```

---

## Badge System

### Check & Award Badges

```php
use App\Services\BadgeService;

$badgeService = new BadgeService();

// Auto-check and award applicable badges
$badgeService->checkAndAwardBadges($user);

// Manual badge award
$badge = Badge::where('slug', 'first-quiz')->first();
$badgeService->awardBadge($user, $badge);

// Get user badges
$badges = $badgeService->getUserBadges($user);

// Get badge progress
$progress = $badgeService->getBadgeProgress($user, $badge);
```

---

## Admin Features

### Manage XP Rules

**Path**: Admin → Gamification → XP Rules

- **Create/Edit**: Add custom XP rules
- **Enable/Disable**: Toggle rule activation
- **Daily Limit**: Max times per day
- **Cooldown**: Delay between awards
- **Reorder**: Drag-to-reorder rules

### Manage User XP

**Path**: Admin → Gamification → User XP

- **View**: See user's total XP, level, streak
- **Add XP**: Manually award XP (creates transaction)
- **Deduct XP**: Remove XP with reason (creates negative transaction)
- **Reset**: Zero out user's XP completely
- **History**: View all XP transactions for user

### View XP Transactions

**Path**: Admin → Gamification → XP Transactions

- **Search**: By user, event type, date range
- **Filter**: By direction (earned/deducted), source
- **Details**: See transaction metadata
- **Immutable**: All transactions are permanent (never deleted)

### Configure Levels

**Path**: Admin → Gamification → Levels

- **Create/Edit**: Define level thresholds
- **XP Required**: Amount needed to reach level
- **Rewards**: Bonus XP for level up
- **Badges**: Associate badges with levels
- **Status**: Enable/disable levels

### Manage Badges

**Path**: Admin → Gamification → Badges & Rewards

- **Condition Types**: Quiz count, streak days, total XP, etc.
- **Condition Data**: JSON configuration for badge conditions
- **Rewards**: XP bonus for earning badge
- **Icon**: Custom badge icon/color
- **Status**: Activate/deactivate badges

### Global Settings

**Path**: Admin → Gamification → Settings

**XP Limits**:
- Daily XP Cap: Max XP per day
- Weekly XP Cap: Max XP per week
- Max XP per Quiz: Ceiling per quiz

**Attempt Multipliers**:
- First Attempt: 100% XP
- Second Attempt: 50% XP
- Third+ Attempt: 0% XP (prevent farming)

**Features**:
- Enable/Disable XP, Levels, Badges, Streaks, Leaderboard

**Notifications**:
- XP Earned: Notify user
- Level Up: Notify on level change
- Badge Earned: Notify on badge unlock
- Streak: Notify on streak milestones

**Leaderboard**:
- Sort By: XP, Accuracy, Quiz Count
- Period: Daily, Weekly, Monthly, All-Time
- Users Shown: 10-1000

---

## Anti XP Farming Protection

The system includes built-in anti-farming measures:

### 1. Attempt Multipliers
- First attempt: 100% XP
- Second attempt: 50% XP
- Third+ attempt: 0% XP (configurable)

### 2. Daily/Weekly Limits
- Daily XP cap (default: 5000)
- Weekly XP cap (default: 30000)
- Rule-specific daily limits

### 3. Cooldown
- Per-rule cooldown (e.g., can't get 3-day streak bonus daily)

### 4. Idempotency
- Unique identifiers prevent duplicate XP
- Same quiz + same user + same day = only 1 XP award

### 5. Configurable Limits
Admin can adjust all limits in Settings

---

## Database Schema

### xp_rules
```
id, name, key (unique), description, xp_value, is_active, 
daily_limit, weekly_limit, cooldown_minutes, sort_order, category, metadata
```

### user_xp
```
id, user_id (unique), total_xp, current_level, xp_this_week, 
xp_this_month, last_xp_activity
```

### xp_transactions (Immutable)
```
id, user_id, event_type, reference_type, reference_id, xp_amount, 
direction (earned/deducted), description, source (system/admin/user), 
admin_id, admin_note, unique_identifier (unique), metadata, created_at
```

### levels
```
id, level_number (unique), name, required_xp, description, 
badge_icon, badge_color, reward_xp, is_active, sort_order
```

### badges
```
id, name, slug (unique), description, icon, color, condition_type, 
condition_data (json), reward_xp, is_active, sort_order, times_earned
```

### user_badges
```
id, user_id, badge_id, earned_at
```

### user_streaks
```
id, user_id (unique), current_streak, longest_streak, 
last_activity, streak_started_at
```

### gamification_settings
```
id, xp_system_enabled, levels_enabled, badges_enabled, streaks_enabled, 
leaderboard_enabled, daily_xp_cap, weekly_xp_cap, max_xp_per_quiz,
first_attempt_percentage, second_attempt_percentage, third_plus_attempt_percentage,
notify_xp_earned, notify_level_up, notify_badge_earned, notify_streak,
leaderboard_sort_by, leaderboard_period, leaderboard_users_shown
```

### quiz_xp_settings
```
id, exam_id (unique), xp_enabled, use_global_rules, completion_xp, 
passing_bonus_xp, perfect_score_bonus_xp, first_attempt_bonus_xp, metadata
```

---

## Example: Complete Quiz Flow

```php
// 1. User attempts quiz
$attempt = AttendExam::create([
    'user_id' => $user->id,
    'exam_id' => $quiz->id,
    'status' => Status::EXAM_COMPLETED,
]);

// 2. User submits answers
$correct = 0;
$total = $quiz->questions->count();

foreach ($request->answers as $questionId => $selectedOptionId) {
    $question = Question::findOrFail($questionId);
    $isCorrect = $question->correct_option_id == $selectedOptionId;

    if ($isCorrect) {
        $correct++;

        // Award XP for correct answer
        $xpService->awardXp(
            $user,
            "correct_{$question->difficulty}_answer",
            'question',
            $questionId
        );
    }
}

// 3. Calculate score
$percentage = ($correct / $total) * 100;

// 4. Award quiz completion XP
$xpService->awardXp(
    $user,
    'quiz_completed',
    'quiz',
    $quiz->id
);

// 5. Award passing bonus if applicable
if ($percentage >= $quiz->pass_percentage) {
    $xpService->awardXp(
        $user,
        'quiz_passed',
        'quiz',
        $quiz->id
    );

    // 6. Award perfect score bonus
    if ($percentage == 100) {
        $xpService->awardXp(
            $user,
            'perfect_score',
            'quiz',
            $quiz->id
        );
    }

    // 7. Create certificate (for paid exams)
    if ($quiz->isPaid()) {
        GetCertificateUser::create([
            'user_id' => $user->id,
            'attend_exam_id' => $attempt->id,
        ]);
    }
}

// 8. Update streak
$streakService = new StreakService();
$streakService->updateStreak($user);

// 9. Check and award badges
$badgeService = new BadgeService();
$badgeService->checkAndAwardBadges($user);

// 10. Return results
return [
    'score' => $percentage,
    'passed' => $percentage >= $quiz->pass_percentage,
    'xp_earned' => $xpEarned,
    'current_level' => $user->xpProfile->current_level,
    'next_level_xp' => $nextLevel->required_xp ?? null,
];
```

---

## API Usage (Future)

The XP system can be exposed via API for mobile apps:

```php
// GET /api/user/xp
// Returns: user's XP profile, level, badges, streaks

// GET /api/xp/rules
// Returns: all active XP rules

// GET /api/leaderboard
// Returns: top users by XP

// GET /api/user/xp/history
// Returns: user's XP transaction history
```

---

## Troubleshooting

### XP Not Being Awarded

1. Check if XP system is enabled in Settings
2. Verify rule exists and is active: `XpRule::where('key', 'rule_key')->first()`
3. Check daily/weekly limits haven't been hit
4. Verify unique identifier isn't duplicated
5. Check logs: `storage/logs/laravel.log`

### User Level Not Updating

1. Verify levels are created in database
2. Check level XP thresholds are correct
3. Run: `php artisan db:seed --class=GamificationSeeder`

### Badges Not Awarding

1. Check if badges are enabled in Settings
2. Verify badge condition type and data are correct
3. Review badge condition logic in `BadgeService`

---

## Performance Considerations

### Indexes
- `xp_transactions.user_id`
- `xp_transactions.event_type`
- `xp_transactions.created_at`
- `user_xp.total_xp`
- `user_xp.current_level`
- `user_badges.user_id`
- `user_badges.earned_at`

All indexes are created in the migration.

### Caching
Consider caching for frequently accessed data:

```php
// Cache user XP profile (5 minutes)
$userXp = Cache::remember("user_xp_{$user->id}", 300, function() {
    return $user->xpProfile;
});
```

---

## Support & Customization

The module is fully extensible:

- **Custom XP Rules**: Add to database, create event triggers
- **Custom Badge Conditions**: Extend `BadgeService::checkCondition()`
- **Custom Notifications**: Implement in notification methods
- **Custom Leaderboard**: Create new controller/view

For questions or issues, refer to service classes:
- `app/Services/XpService.php`
- `app/Services/StreakService.php`
- `app/Services/BadgeService.php`

---

## File Structure

```
app/
  ├── Models/
  │   ├── XpRule.php
  │   ├── UserXp.php
  │   ├── XpTransaction.php
  │   ├── Level.php
  │   ├── Badge.php
  │   ├── UserBadge.php
  │   ├── UserStreak.php
  │   ├── QuizXpSetting.php
  │   ├── GamificationSetting.php
  │   └── UserXpClaim.php
  ├── Services/
  │   ├── XpService.php
  │   ├── StreakService.php
  │   └── BadgeService.php
  ├── Http/Controllers/Admin/
  │   ├── XpDashboardController.php
  │   ├── XpRulesController.php
  │   ├── UserXpController.php
  │   ├── XpTransactionController.php
  │   ├── LevelController.php
  │   ├── BadgeController.php
  │   └── GamificationSettingsController.php
  └── Seeders/
      └── GamificationSeeder.php

database/
  └── migrations/
      └── 2026_08_11_000001_create_xp_system_tables.php

routes/
  └── admin.php (+ gamification routes)

resources/views/admin/gamification/
  ├── dashboard.blade.php
  ├── xp_rules/
  │   ├── index.blade.php
  │   └── form.blade.php
  ├── user_xp/
  │   ├── index.blade.php
  │   └── show.blade.php
  ├── xp_transactions/
  │   ├── index.blade.php
  │   └── show.blade.php
  ├── levels/
  │   ├── index.blade.php
  │   └── form.blade.php
  ├── badges/
  │   ├── index.blade.php
  │   └── form.blade.php
  └── settings/
      └── index.blade.php
```

---

**Version**: 1.0.0  
**Last Updated**: August 11, 2026  
**Status**: Production Ready
