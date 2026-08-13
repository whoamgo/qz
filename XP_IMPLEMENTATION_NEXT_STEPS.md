# XP Engine - Next Steps & Deployment Guide

## Pre-Deployment Checklist

### 1. Run Migrations
```bash
php artisan migrate

# Verify tables created:
# - attend_exams (updated with: correct_count, incorrect_count, pass_percentage, xp_awarded)
# - All XP system tables should already exist from previous migration
```

### 2. Run Seeders (Optional but Recommended)
```bash
# Seed XP system data (22 rules, 15 levels, 35+ badges)
php artisan db:seed --class=GamificationSeeder

# Or seed all:
php artisan db:seed
```

### 3. Clear Caches
```bash
php artisan cache:clear
php artisan config:cache
php artisan view:clear
php artisan route:cache
```

### 4. Verify File Locations

Files created/modified:
```
✅ app/Services/GamificationService.php (NEW)
✅ app/Services/QuizXpCalculator.php (NEW)
✅ app/Services/QuizEvaluationService.php (NEW)
✅ app/Services/XpService.php (EXTENDED)
✅ app/Http/Controllers/Api/QuizController.php (NEW)
✅ app/Http/Controllers/User/ExamController.php (UPDATED)
✅ app/Models/UserXp.php (UPDATED - added level relationship)
✅ app/Models/AttendExam.php (UPDATED - added XP fields)
✅ app/Models/GamificationSetting.php (UPDATED - added getInstance())
✅ app/Models/Quiz.php (UPDATED - added xpSettings relationship)
✅ database/migrations/2026_08_11_000002_fix_quiz_xp_settings_relationship.php
✅ database/migrations/2026_08_11_000003_add_xp_fields_to_attend_exams_table.php
✅ routes/api.php (UPDATED with quiz routes)

Documentation:
✅ XP_ENGINE_INTEGRATION.md (Complete guide)
✅ XP_ENGINE_COMPLETE_SUMMARY.md (Implementation summary)
✅ XP_QUICK_REFERENCE.md (Developer reference)
✅ XP_IMPLEMENTATION_NEXT_STEPS.md (This file)
```

## Testing

### 1. Unit Testing (Manual)

**Test Quiz Submission with XP:**
```bash
# Start a quiz, submit answers
# Verify xp_transactions table has new entries
php artisan tinker

# In tinker:
$attendExam = AttendExam::latest()->first();
$attendExam->xp_awarded; // Should see XP awarded value

$transactions = XpTransaction::where('reference_id', $attendExam->exam_id)
    ->where('user_id', $attendExam->user_id)
    ->get();

$transactions->each(function($t) {
    echo "{$t->event_type}: {$t->xp_amount} XP\n";
});
```

### 2. API Testing

**Get Quiz Result:**
```bash
curl -X GET \
  "http://localhost:8000/api/quiz/result/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Get Gamification Profile:**
```bash
curl -X GET \
  "http://localhost:8000/api/quiz/gamification-profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### 3. Admin Panel Testing

1. Navigate to: `/admin/xp/` (Gamification Dashboard)
2. Check XP statistics
3. View top users
4. Check recent transactions
5. Test XP rules management
6. Verify levels and badges

## Common Customizations

### Change XP Values for Quiz Components

```php
// Modify default quiz XP settings
$quizXpSetting = QuizXpSetting::where('quiz_id', $quizId)->first();
$quizXpSetting->update([
    'completion_xp' => 75,           // Changed from 50
    'passing_bonus_xp' => 50,        // Changed from 25
    'perfect_score_bonus_xp' => 150, // Changed from 100
    'first_attempt_bonus_xp' => 40,  // Changed from 25
]);
```

### Adjust Global XP Caps

```php
// Admin console or tinker
$settings = GamificationSetting::getInstance();
$settings->update([
    'daily_xp_cap' => 500,      // Increased from 300
    'weekly_xp_cap' => 2500,    // Increased from 1500
    'first_attempt_percentage' => 100,
    'second_attempt_percentage' => 75,  // Changed from 50
    'third_plus_attempt_percentage' => 25, // Changed from 0
]);
```

### Create Custom XP Rule

```php
// Via Artisan or admin panel
XpRule::create([
    'key' => 'perfect_streak_10',
    'name' => 'Perfect Streak (10 Days)',
    'description' => 'Award XP for maintaining 10-day streak',
    'xp_value' => 250,
    'category' => 'streak',
    'daily_limit' => null,
    'cooldown_minutes' => 0,
    'sort_order' => 50,
    'is_active' => true,
]);
```

## Monitoring & Logging

### Check Application Logs
```bash
# Real-time logs
tail -f storage/logs/laravel.log

# Search for XP-related entries
grep -i "xp\|gamification" storage/logs/laravel.log

# Check for errors
grep -i "error" storage/logs/laravel.log | tail -50
```

### Database Monitoring

```php
// Check XP system health
php artisan tinker

# Total XP awarded
XpTransaction::where('direction', 'earned')->sum('xp_amount');

# Today's XP
XpTransaction::where('direction', 'earned')
    ->whereDate('created_at', today())
    ->sum('xp_amount');

# Users with XP
User::whereHas('xpProfile', function($q) {
    $q->where('total_xp', '>', 0);
})->count();

# Total badges awarded
UserBadge::count();

# Active streaks
UserStreak::where('current_count', '>', 0)->count();
```

## Performance Optimization

### Database Indexes (If Not Present)
```sql
-- Add indexes for faster queries
ALTER TABLE xp_transactions ADD INDEX idx_user_created (user_id, created_at);
ALTER TABLE xp_transactions ADD INDEX idx_reference (reference_type, reference_id);
ALTER TABLE user_badges ADD INDEX idx_user_earned (user_id, earned_at);
ALTER TABLE user_xp ADD INDEX idx_total_xp (total_xp DESC);
```

### Query Optimization
```php
// Good: Eager load relationships
$users = User::with('xpProfile', 'badges', 'streak')
    ->where('active', true)
    ->get();

// Bad: Causes N+1 queries
$users = User::all();
foreach ($users as $user) {
    echo $user->xpProfile->total_xp; // Query per user!
}
```

## Troubleshooting

### Issue: XP not awarded after quiz submission
**Solution:**
```php
// 1. Check if system is enabled
GamificationSetting::getInstance()->is_enabled; // Should be true

// 2. Check quiz settings
$quiz = Quiz::find($quizId);
$quiz->xpSettings->xp_enabled; // Should be true

// 3. Check limits
$xpService = new XpService();
$xpService->checkDailyLimit($user, 100); // Returns bool

// 4. Check logs
tail -f storage/logs/laravel.log | grep -i "xp"
```

### Issue: Duplicate XP awarded
**Solution:**
```php
// Check for duplicate transactions
$duplicates = XpTransaction::where('unique_identifier', $identifier)
    ->get();
    
// This shouldn't happen, but if it does:
// Delete the duplicate (keep the first)
$duplicates->skip(1)->each->delete();
```

### Issue: Level not updating
**Solution:**
```php
// Manually recalculate levels for a user
$user = User::find($userId);
$userXp = $user->xpProfile;

$newLevel = Level::getLevelByXp($userXp->total_xp);
$userXp->current_level = $newLevel->level_number;
$userXp->save();

echo "User now has level {$newLevel->level_number} ({$newLevel->name})";
```

### Issue: Badge not unlocking
**Solution:**
```php
// Manually check badges
$badgeService = new BadgeService();
$newBadges = $badgeService->checkAndAwardBadges($user);

echo "Awarded " . count($newBadges) . " badges";
```

## Deployment to Production

### Pre-Production Checklist
```
☐ All migrations run successfully
☐ XP_ENGINE_INTEGRATION.md reviewed
☐ API endpoints tested with real data
☐ Admin panel functionality verified
☐ XP awarding tested on production DB
☐ Performance tested under load
☐ Logging and monitoring configured
☐ Error handling verified
☐ Database backups created
☐ Rollback plan documented
```

### Deployment Steps
```bash
# 1. Backup database
mysqldump -u root -p qiz_database > qiz_backup_$(date +%Y%m%d).sql

# 2. Pull latest code
git pull origin main

# 3. Install dependencies
composer install --no-dev

# 4. Run migrations
php artisan migrate --force

# 5. Seed data (if needed)
php artisan db:seed --class=GamificationSeeder --force

# 6. Clear and cache
php artisan cache:clear
php artisan config:cache
php artisan view:clear
php artisan route:cache

# 7. Verify
php artisan tinker
# > GamificationSetting::getInstance()->is_enabled
# > true (should return true)
```

### Monitoring Post-Deployment
```bash
# 1. Check error logs
tail -f storage/logs/laravel.log

# 2. Monitor database
watch -n 5 'mysql -e "SELECT COUNT(*) as transactions FROM xp_transactions;"'

# 3. Test APIs
curl http://your-domain/api/quiz/result/1 -H "Authorization: Bearer token"

# 4. Monitor performance
php artisan tinker
XpTransaction::count(); # Should see new transactions
```

## Rollback Plan

If issues arise:
```bash
# 1. Rollback migrations
php artisan migrate:rollback

# 2. Restore database from backup
mysql -u root -p qiz_database < qiz_backup_20260811.sql

# 3. Clear caches
php artisan cache:clear
php artisan view:clear

# 4. Verify system
php artisan tinker
```

## Support

### Documentation
- `XP_ENGINE_INTEGRATION.md` - Complete architecture and configuration
- `XP_QUICK_REFERENCE.md` - Code examples and API reference
- `XP_ENGINE_COMPLETE_SUMMARY.md` - Implementation details

### Getting Help
1. Check logs: `storage/logs/laravel.log`
2. Run diagnostics in tinker
3. Review relevant documentation
4. Check database state directly

## Success Indicators

After deployment, you should see:
✅ Users earning XP after quiz submission
✅ XP breakdown in API response
✅ Levels calculated automatically
✅ Badges unlocking on qualification
✅ Streaks tracking activity
✅ Admin panel showing statistics
✅ No XP duplication on retry
✅ Limits enforced correctly

## Next Features to Build

Once XP engine is stable:
1. Frontend XP display components
2. Leaderboard page
3. Achievement showcase
4. Custom challenges
5. Social sharing of achievements
6. XP notifications push
7. Seasonal events
8. Partnership badges

---

**Questions?** Refer to the documentation files or check the code comments for implementation details.
