# XP Engine - Quick Reference Guide

## Using the XP System in Code

### Award XP Manually (Admin)

```php
// In controller or service
$user = User::find($userId);
$xpService = new \App\Services\XpService();

// Award XP from rule
$transaction = $xpService->awardXp(
    user: $user,
    eventType: 'quiz_completed',  // XP rule key
    referenceType: 'quiz',
    referenceId: $quizId
);
```

### Process Quiz Completion

```php
use App\Services\GamificationService;

$user = auth()->user();
$quiz = Quiz::find($quizId);

$gamificationService = new GamificationService($user);
$result = $gamificationService->processQuizCompletion(
    quiz: $quiz,
    correctAnswers: 85,
    totalQuestions: 100,
    passed: true,
    isFirstAttempt: true
);

// Result contains:
// - total_xp_earned
// - xp_breakdown (array with component values)
// - level_before / level_after
// - level_up (boolean)
// - new_badges (array)
// - streak_info
```

### Get User's Gamification Status

```php
use App\Services\GamificationService;

$user = auth()->user();
$gamificationService = new GamificationService($user);
$status = $gamificationService->getUserGamificationStatus();

// Returns:
// - total_xp
// - current_level (array)
// - next_level (array)
// - streak (array)
// - badges (array)
```

### Deduct XP (Admin Action)

```php
$xpService = new \App\Services\XpService();

$transaction = $xpService->deductXp(
    user: $user,
    amount: 50,
    reason: 'Cheating detected',
    adminNote: 'Removed duplicate XP',
    adminId: auth()->id()
);
```

### Check User's Level

```php
$user = auth()->user();
$userXp = $user->xpProfile;  // UserXp model

echo $userXp->total_xp;      // Total XP
echo $userXp->current_level; // Level number (1-15)

// Get Level object
$level = \App\Models\Level::getLevelByXp($userXp->total_xp);
echo $level->name;  // e.g., "Master", "Expert"
echo $level->badge_icon;  // e.g., "👑"
```

### Get User's Badges

```php
$user = auth()->user();

$badges = $user->badges()->get();
foreach ($badges as $badge) {
    echo $badge->name;           // Badge name
    echo $badge->icon;           // Badge emoji
    echo $badge->pivot->earned_at; // When earned
}
```

### Get User's Streak

```php
$user = auth()->user();
$streak = $user->streak;

if ($streak) {
    echo $streak->current_count;  // Current streak days
    echo $streak->longest_count;  // Longest streak ever
    echo $streak->last_activity_date;  // Last activity
}
```

### Get XP Transactions

```php
$user = auth()->user();

// All transactions
$transactions = $user->xpTransactions()->latest()->get();

// Specific type
$quizXp = $user->xpTransactions()
    ->where('event_type', 'quiz_completed')
    ->get();

// Within date range
$weekXp = $user->xpTransactions()
    ->where('direction', 'earned')
    ->whereBetween('created_at', [$start, $end])
    ->sum('xp_amount');
```

## API Endpoints

### Get Quiz Result with XP

```
GET /api/quiz/result/{examId}
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "exam_id": 1,
    "exam_title": "Quiz Title",
    "result": {
      "correct_answers": 85,
      "wrong_answers": 10,
      "skipped_answers": 5,
      "total_questions": 100,
      "score_percentage": 85,
      "passed": true
    },
    "xp": {
      "total_earned": 227,
      "breakdown": {
        "quiz_completion": 50,
        "correct_answers": 127,
        "passing_bonus": 25,
        "perfect_score_bonus": 0,
        "first_attempt_bonus": 25
      }
    },
    "gamification": {
      "total_xp": 5420,
      "current_level": {...},
      "next_level": {...},
      "current_streak": {...},
      "newly_unlocked_badges": [...]
    }
  }
}
```

### Get Gamification Profile

```
GET /api/quiz/gamification-profile
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "total_xp": 5420,
    "current_level": {
      "id": 5,
      "level_number": 5,
      "name": "Advanced",
      "icon": "🎖️",
      "color": "#FFD700",
      "required_xp": 3000
    },
    "next_level": {
      "id": 6,
      "level_number": 6,
      "name": "Master",
      "icon": "👑",
      "color": "#FFA500",
      "required_xp": 5000,
      "xp_required": 420
    },
    "streak": {
      "current": 12,
      "longest": 25,
      "last_activity": "2026-08-11 10:30:00",
      "streak_started_at": "2026-08-01 08:15:00"
    },
    "badges": [
      {
        "id": 1,
        "name": "First Quiz",
        "icon": "🎓",
        "color": "#4169E1",
        "reward_xp": 25,
        "earned_at": "2026-08-01 09:00:00"
      }
    ]
  }
}
```

## Admin Panel Routes

### Gamification Dashboard
```
GET /admin/xp/
```

### XP Rules Management
```
GET    /admin/xp/rules              (List)
GET    /admin/xp/rules/create       (Create form)
POST   /admin/xp/rules              (Store)
GET    /admin/xp/rules/{id}/edit    (Edit form)
POST   /admin/xp/rules/{id}         (Update)
DELETE /admin/xp/rules/{id}         (Delete)
PUT    /admin/xp/rules/{id}/status  (Toggle status)
PATCH  /admin/xp/rules/reorder      (Reorder)
```

### User XP Management
```
GET  /admin/xp/users              (List users)
GET  /admin/xp/users/{id}         (View user)
POST /admin/xp/users/{id}/add-xp  (Add XP)
POST /admin/xp/users/{id}/deduct-xp (Deduct XP)
POST /admin/xp/users/{id}/reset-xp  (Reset XP)
```

### Levels Management
```
GET    /admin/xp/levels              (List)
GET    /admin/xp/levels/create       (Create form)
POST   /admin/xp/levels              (Store)
GET    /admin/xp/levels/{id}/edit    (Edit form)
POST   /admin/xp/levels/{id}         (Update)
DELETE /admin/xp/levels/{id}         (Delete)
PUT    /admin/xp/levels/{id}/status  (Toggle status)
```

### Badges Management
```
GET    /admin/xp/badges              (List)
GET    /admin/xp/badges/create       (Create form)
POST   /admin/xp/badges              (Store)
GET    /admin/xp/badges/{id}/edit    (Edit form)
POST   /admin/xp/badges/{id}         (Update)
DELETE /admin/xp/badges/{id}         (Delete)
PUT    /admin/xp/badges/{id}/status  (Toggle status)
```

### Transactions View
```
GET /admin/xp/transactions       (List with search/filter)
GET /admin/xp/transactions/{id}  (View details)
```

### Settings
```
GET  /admin/xp/settings    (View settings)
POST /admin/xp/settings    (Update settings)
```

## Common XP Rule Keys

```php
'quiz_completed'        // User completes a quiz
'quiz_passed'          // User passes a quiz
'perfect_score'        // User gets 100% on a quiz
'first_attempt'        // User completes quiz first time
'correct_answer'       // For each correct answer
'streak_milestone'     // Milestone streaks (7, 14, 30, etc.)
'badge_earned'         // Earning a badge
'level_reached'        // Reaching a new level
```

## Configuration (gamification_settings)

```php
// Get current settings
$settings = \App\Models\GamificationSetting::getInstance();

// Check if enabled
if ($settings->is_enabled && $settings->xp_system_enabled) {
    // Award XP
}

// Get limits
$dailyCap = $settings->daily_xp_cap;      // 300
$weeklyCap = $settings->weekly_xp_cap;    // 1500
$maxPerQuiz = $settings->max_xp_per_quiz; // 500

// Get multipliers
$first = $settings->first_attempt_percentage;      // 100
$second = $settings->second_attempt_percentage;    // 50
$third = $settings->third_plus_attempt_percentage; // 0

// Notifications
$notifyXp = $settings->notify_xp_earned;       // true
$notifyLevel = $settings->notify_level_up;     // true
$notifyBadge = $settings->notify_badge_earned; // true
$notifyStreak = $settings->notify_streak;      // true
```

## Database Queries

### Top Users by XP
```php
$topUsers = \App\Models\UserXp::with('user')
    ->orderBy('total_xp', 'desc')
    ->limit(10)
    ->get();
```

### Today's XP Earned
```php
$todayXp = \App\Models\XpTransaction::where('direction', 'earned')
    ->whereDate('created_at', today())
    ->sum('xp_amount');
```

### User's Weekly Earnings
```php
$weekXp = \App\Models\XpTransaction::where('user_id', $userId)
    ->where('direction', 'earned')
    ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
    ->sum('xp_amount');
```

### Badges Earned This Month
```php
$badges = $user->badges()
    ->wherePivot('created_at', '>=', now()->startOfMonth())
    ->get();
```

## Debugging

### Check if XP was awarded
```php
// Look in xp_transactions
$transactions = \App\Models\XpTransaction::where('user_id', $userId)
    ->where('reference_id', $quizId)
    ->where('reference_type', 'quiz')
    ->get();

// Check unique_identifier for duplicates
$allTransactions = \App\Models\XpTransaction::where('unique_identifier', $uniqueId)
    ->get();
```

### Check level recalculation
```php
$userXp = $user->xpProfile;
$currentLevel = \App\Models\Level::getLevelByXp($userXp->total_xp);
echo "User has {$userXp->total_xp} XP, level {$currentLevel->level_number}";
```

### Check streak status
```php
$streak = $user->streak;
if (!$streak) {
    echo "No streak";
} else {
    echo "Current: {$streak->current_count}, Longest: {$streak->longest_count}";
    echo "Last activity: {$streak->last_activity_date}";
}
```

### Check XP limits
```php
$dailyXp = \App\Models\XpTransaction::where('user_id', $userId)
    ->where('direction', 'earned')
    ->whereDate('created_at', today())
    ->sum('xp_amount');

$settings = \App\Models\GamificationSetting::getInstance();
$remaining = $settings->daily_xp_cap - $dailyXp;
echo "Daily cap: {$dailyXp}/{$settings->daily_xp_cap} ({$remaining} remaining)";
```

## Common Issues & Solutions

### XP Not Being Awarded
1. Check `gamification_settings.is_enabled` = true
2. Check `quiz_xp_settings.xp_enabled` = true for quiz
3. Check daily/weekly limits not exceeded
4. Check logs: `storage/logs/laravel.log`

### Duplicate XP
Shouldn't happen due to idempotency, but check:
1. `xp_transactions.unique_identifier` for duplicates
2. Verify no race conditions in code

### Level Not Updating
1. Verify `levels` table has entries
2. Check `required_xp` values are correct
3. Run manual level recalculation:
   ```php
   $userXp = $user->xpProfile;
   $newLevel = \App\Models\Level::getLevelByXp($userXp->total_xp);
   $userXp->current_level = $newLevel->level_number;
   $userXp->save();
   ```

### Badge Not Unlocking
1. Check badge is `is_active` = true
2. Verify `condition_type` and `condition_data` are correct
3. Check BadgeService logs
4. Manually trigger badge check:
   ```php
   $badgeService = new \App\Services\BadgeService();
   $newBadges = $badgeService->checkAndAwardBadges($user);
   ```
