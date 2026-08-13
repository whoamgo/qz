# XP Engine Integration - Complete Implementation Guide

## Overview

This document outlines the complete XP & Gamification Engine integration with the existing Quiz/Exam system in Laravel 11. The system provides centralized XP management, automatic badge and level progression, and comprehensive gamification features.

## Architecture

### Core Components

#### 1. **Services** (`app/Services/`)

- **GamificationService**: Main orchestrator for XP operations
  - `processQuizCompletion()`: Handles complete XP workflow after quiz completion
  - `getUserGamificationStatus()`: Retrieves user's current XP, levels, badges, and streaks
  
- **XpService**: Low-level XP transaction management
  - `awardQuizCompletionXp()`: Awards XP with all bonuses and multipliers
  - `awardQuizComponentXp()`: Awards XP for individual components (completion, passing, etc.)
  - `awardCorrectAnswerXp()`: Awards XP based on correct answers and difficulty
  - `deductXp()`: Removes XP from user (admin action)
  - Handles daily/weekly limits, attempt multipliers, and prevents duplicate XP
  
- **StreakService**: User streak tracking and management
  - `updateStreak()`: Updates or creates user streak
  - `breakStreak()`: Breaks streak after inactivity
  - `getCurrentStreak()`, `getLongestStreak()`: Retrieves streak data
  
- **BadgeService**: Badge condition checking and awarding
  - `checkAndAwardBadges()`: Evaluates all badge conditions and awards eligible badges
  - Supports 9 condition types: quiz_count, question_count, correct_answer_count, perfect_score_count, streak_days, total_xp, category_quiz_count, leaderboard_rank
  
- **QuizXpCalculator**: XP calculation based on quiz settings
  - Calculates XP breakdown by component
  - Applies quiz-specific overrides
  
- **QuizEvaluationService**: Evaluates exam answers and triggers XP workflow
  - `evaluateExam()`: Calculates score from submitted answers
  - `evaluateExamWithXp()`: Evaluates and awards XP in one transaction

#### 2. **Models** (`app/Models/`)

| Model | Purpose |
|-------|---------|
| `XpRule` | Defines XP earning rules (quiz_completed, quiz_passed, perfect_score, etc.) |
| `UserXp` | User XP profile with total_xp and current_level |
| `XpTransaction` | Immutable ledger of all XP earned/deducted |
| `Level` | Level definitions with required_xp thresholds |
| `Badge` | Badge definitions with condition types and reward_xp |
| `UserBadge` | Pivot table linking users to earned badges |
| `UserStreak` | User's current and longest streak tracking |
| `QuizXpSetting` | Quiz-specific XP configuration (overrides) |
| `GamificationSetting` | Global system configuration and feature toggles |
| `UserXpClaim` | Daily/weekly bonus claim tracking |

#### 3. **Controllers** (`app/Http/Controllers/`)

- **User/ExamController**: Handles quiz submission and evaluation
  - Updated `submit()`: Evaluates answers and triggers XP workflow
  - Updated `view()`: Includes gamification status in response
  
- **Api/QuizController**: REST API endpoints for XP and results
  - `GET /api/quiz/result/{examId}`: Get complete result with XP breakdown
  - `GET /api/quiz/gamification-profile`: Get user's gamification profile
  
- **Admin/GamificationControllers**: Admin panel for XP management
  - XpDashboardController: Statistics and overview
  - XpRulesController: Manage earning rules
  - LevelController: Configure levels
  - BadgeController: Create/edit badges
  - GamificationSettingsController: Global configuration

#### 4. **Database** (`database/`)

**Migrations:**
- `2026_08_11_000001_create_xp_system_tables.php`: Initial XP system tables
- `2026_08_11_000002_fix_quiz_xp_settings_relationship.php`: Quiz-XP relationship fix
- `2026_08_11_000003_add_xp_fields_to_attend_exams_table.php`: XP fields on exam attempts

**Seeders:**
- `GamificationSeeder`: Seeds 22 XP rules, 15 levels, and 35+ badges

#### 5. **Routes** (`routes/`)

**Admin Routes** (`routes/admin.php`):
```php
Route::prefix('xp')->name('xp.')->group(function () {
    // Dashboard and statistics
    Route::get('/', 'XpDashboardController@index')->name('dashboard');
    
    // XP Rules management
    Route::resource('rules', 'XpRulesController');
    Route::put('rules/{id}/status', 'XpRulesController@status')->name('rules.status');
    Route::patch('rules/reorder', 'XpRulesController@reorder')->name('rules.reorder');
    
    // User XP management
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', 'UserXpController@index')->name('index');
        Route::get('{id}', 'UserXpController@show')->name('show');
        Route::post('{id}/add-xp', 'UserXpController@addXp')->name('add');
        Route::post('{id}/deduct-xp', 'UserXpController@deductXp')->name('deduct');
        Route::post('{id}/reset-xp', 'UserXpController@resetXp')->name('reset');
    });
    
    // Levels management
    Route::resource('levels', 'LevelController');
    Route::put('levels/{id}/status', 'LevelController@status')->name('levels.status');
    
    // Badges management
    Route::resource('badges', 'BadgeController');
    Route::put('badges/{id}/status', 'BadgeController@status')->name('badges.status');
    
    // Transactions
    Route::get('transactions', 'XpTransactionController@index')->name('transactions.index');
    Route::get('transactions/{id}', 'XpTransactionController@show')->name('transactions.show');
    
    // Settings
    Route::get('settings', 'GamificationSettingsController@index')->name('settings.index');
    Route::post('settings', 'GamificationSettingsController@update')->name('settings.update');
});
```

**API Routes** (`routes/api.php`):
```php
Route::middleware('auth:sanctum')->group(function () {
    // Quiz results with XP
    Route::controller('QuizController')->prefix('quiz')->group(function () {
        Route::get('result/{examId}', 'getResult')->name('result');
        Route::get('gamification-profile', 'getGamificationProfile')->name('gamification.profile');
    });
});
```

## XP Workflow - Complete Flow

### When User Completes a Quiz

1. **Quiz Submission** (`ExamController::submit()`)
   - User submits answers
   - Answers stored in `exam_answers` table
   - Exam marked as `WAITING_RESULT`
   - Triggers `QuizEvaluationService::evaluateExamWithXp()`

2. **Answer Evaluation** (`QuizEvaluationService::evaluateExam()`)
   - Compares submitted answers against correct answers
   - Calculates:
     - Correct answers count
     - Score percentage
     - Pass/Fail status
     - Perfect score status
   - Updates `attend_exams` table with results

3. **XP Calculation & Awarding** (`GamificationService::processQuizCompletion()`)
   - Verifies it's first attempt
   - Calls `XpService::awardQuizCompletionXp()`
   - Creates multiple `XpTransaction` records:
     - Quiz completion XP
     - Correct answer XP (based on difficulty)
     - Passing bonus (if passed)
     - Perfect score bonus (if perfect)
     - First attempt bonus (if first attempt)
   - Updates `user_xp.total_xp`
   - Verifies idempotency via `unique_identifier`

4. **Level Recalculation**
   - Checks if user crossed level threshold
   - Updates `user_xp.current_level`
   - Triggers level-up notification if applicable

5. **Streak Update** (`StreakService::updateStreak()`)
   - Updates current streak
   - Records streak start date
   - Triggers streak expiry check

6. **Badge Checking** (`BadgeService::checkAndAwardBadges()`)
   - Evaluates all badge conditions:
     - Quiz count milestones
     - Correct answer count milestones
     - Perfect score count milestones
     - Streak milestones
     - XP thresholds
     - Category-specific achievements
   - Awards eligible badges
   - Creates `user_badges` records

### API Response Example

**GET** `/api/quiz/result/{examId}`
```json
{
  "success": true,
  "data": {
    "exam_id": 1,
    "exam_title": "General Knowledge Quiz",
    "result": {
      "correct_answers": 85,
      "wrong_answers": 10,
      "skipped_answers": 5,
      "total_questions": 100,
      "score_percentage": 85,
      "passed": true
    },
    "xp": {
      "total_earned": 345,
      "breakdown": {
        "quiz_completion": 50,
        "correct_answers": 127,
        "passing_bonus": 25,
        "perfect_score_bonus": 0,
        "first_attempt_bonus": 25,
        "total": 227
      }
    },
    "gamification": {
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
      "current_streak": {
        "current": 12,
        "longest": 25,
        "last_activity": "2026-08-11 10:30:00",
        "streak_started_at": "2026-08-01 08:15:00"
      },
      "newly_unlocked_badges": [
        {
          "id": 3,
          "name": "Quiz Master",
          "icon": "🎓",
          "color": "#4169E1",
          "reward_xp": 50
        }
      ]
    }
  }
}
```

## Anti-Fraud Protection

The system includes multiple layers of XP farming protection:

### 1. **Attempt Multipliers**
- 1st attempt: 100% XP
- 2nd attempt: 50% XP
- 3rd+ attempt: 0% XP
- Configurable via `gamification_settings`

### 2. **Daily/Weekly Caps**
- Daily XP cap: 300 XP (default)
- Weekly XP cap: 1,500 XP (default)
- Checked before awarding any XP

### 3. **Rule-Specific Limits**
- Each rule can have `daily_limit` (max times per day)
- Cooldown periods between awards (in minutes)

### 4. **Idempotency**
- Each XP award has `unique_identifier`
- Prevents duplicate XP if request is retried
- Format: `{userId}_{eventType}_{referenceType}_{referenceId}_{date}_{uniqueId}`

### 5. **Transaction Ledger**
- All XP changes recorded in immutable `xp_transactions` table
- Deductions never delete records, only add negative transactions
- Full audit trail of all XP activity

## Configuration

### Global Settings (`gamification_settings` table)

```php
[
    'is_enabled' => true,
    'xp_system_enabled' => true,
    'levels_enabled' => true,
    'badges_enabled' => true,
    'streaks_enabled' => true,
    'leaderboard_enabled' => false,
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
    'leaderboard_sort_by' => 'xp',      // 'xp' or 'level'
    'leaderboard_period' => 'all_time',  // 'all_time', 'monthly', 'weekly'
    'leaderboard_users_shown' => 100,
]
```

### Quiz-Specific Settings (`quiz_xp_settings` table)

```php
[
    'quiz_id' => 1,
    'xp_enabled' => true,
    'use_global_rules' => true,
    'completion_xp' => 50,
    'passing_bonus_xp' => 25,
    'perfect_score_bonus_xp' => 100,
    'first_attempt_bonus_xp' => 25,
    'metadata' => [], // JSON field for custom data
]
```

## Database Tables Schema

### `user_xp`
```sql
- id
- user_id
- total_xp
- current_level (level_number)
- xp_this_week
- xp_this_month
- last_xp_activity
- created_at, updated_at
```

### `xp_transactions`
```sql
- id
- user_id
- event_type (quiz_completed, quiz_passed, perfect_score, etc.)
- reference_type (quiz, badge, manual)
- reference_id
- xp_amount
- direction (earned, deducted)
- description
- source (system, admin, user)
- admin_id
- admin_note
- unique_identifier
- metadata (JSON)
- created_at, updated_at
```

### `xp_rules`
```sql
- id
- key (unique, e.g., quiz_completed)
- name
- description
- xp_value
- category (quiz, learning, streak, other)
- daily_limit
- cooldown_minutes
- sort_order
- is_active
- created_at, updated_at
```

### `levels`
```sql
- id
- level_number
- name
- description
- required_xp
- reward_xp
- badge_icon
- badge_color
- sort_order
- is_active
- created_at, updated_at
```

### `badges`
```sql
- id
- slug
- name
- description
- icon
- color
- condition_type
- condition_data (JSON)
- reward_xp
- is_active
- created_at, updated_at
```

### `user_badges`
```sql
- id
- user_id
- badge_id
- earned_at
- created_at, updated_at
```

### `user_streaks`
```sql
- id
- user_id
- current_count
- longest_count
- last_activity_date
- streak_started_at
- created_at, updated_at
```

### `attend_exams` (Updated)
```sql
- id
- user_id
- exam_id
- start_time
- end_time
- is_submit
- status
- correct_count (NEW)
- incorrect_count (NEW)
- pass_percentage (NEW)
- xp_awarded (NEW)
- created_at, updated_at
```

## Running Migrations

```bash
php artisan migrate

# Seed initial data (22 rules, 15 levels, 35+ badges)
php artisan db:seed --class=GamificationSeeder
```

## Testing the Integration

### 1. **Admin Panel**
- Navigate to Admin Dashboard
- Go to Gamification > Dashboard
- View XP statistics, top users, recent transactions

### 2. **Quiz Submission**
- User submits a quiz
- Check `xp_transactions` for new entries
- Verify `user_xp.total_xp` increased
- Check `user_badges` for new badges

### 3. **API Endpoint**
```bash
# Get quiz result with XP breakdown
curl -X GET \
  "http://localhost:8000/api/quiz/result/{examId}" \
  -H "Authorization: Bearer {token}"

# Get user's gamification profile
curl -X GET \
  "http://localhost:8000/api/quiz/gamification-profile" \
  -H "Authorization: Bearer {token}"
```

## Important Notes

1. **Idempotency**: The system is designed to be idempotent. Submitting the same quiz multiple times won't award duplicate XP.

2. **Transaction Atomicity**: All XP operations are wrapped in database transactions. Either all changes succeed or none do.

3. **Performance**: Uses eager loading and database-level filtering for efficiency.

4. **Exam-Quiz Mapping**: The system automatically creates virtual Quiz objects from Exam data for compatibility.

5. **Level Calculation**: Levels are calculated dynamically from `required_xp` threshold. Users don't need to "reach" levels; they automatically progress.

6. **Streak Expiry**: Streaks expire after 1 day of inactivity (configurable in StreakService).

## Troubleshooting

### XP not being awarded
- Check `gamification_settings.is_enabled` is true
- Verify `quiz_xp_settings.xp_enabled` is true for the quiz
- Check if daily/weekly caps are hit
- Look for errors in `storage/logs/laravel.log`

### Duplicate XP awarded
- Check `xp_transactions.unique_identifier` for duplicates
- This shouldn't happen due to idempotency, but check if transactions table constraint exists

### Badges not unlocking
- Verify badge is `is_active = true`
- Check `condition_type` and `condition_data` JSON format
- Look at BadgeService logs

### Level not updating
- Verify `levels` table has entries with `is_active = true`
- Check `required_xp` thresholds are correct and ascending

## Future Enhancements

- Leaderboard generation (global, monthly, category-wise)
- XP trading/gifting between users
- Challenge system (bonus XP for completing specific combinations)
- Seasonal achievements
- Real-time notifications for XP and badge awards
- Analytics dashboard showing XP distribution, badge popularity
