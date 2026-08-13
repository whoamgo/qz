<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // XP Rules Table - Stores all XP earning rules
        Schema::create('xp_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Quiz Completed"
            $table->string('key')->unique(); // e.g., "quiz_completed"
            $table->text('description')->nullable();
            $table->integer('xp_value'); // Base XP for this rule
            $table->boolean('is_active')->default(true);
            $table->integer('daily_limit')->nullable(); // Max times per day
            $table->integer('weekly_limit')->nullable(); // Max times per week
            $table->integer('cooldown_minutes')->default(0); // Cooldown between awards
            $table->integer('sort_order')->default(0);
            $table->string('category')->default('other'); // quiz, learning, streak, other
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();
            $table->softDeletes();
            $table->index('key');
            $table->index('is_active');
            $table->index('category');
        });

        // User XP Table - Tracks total XP per user
        Schema::create('user_xp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->bigInteger('total_xp')->default(0);
            $table->integer('current_level')->default(1);
            $table->bigInteger('xp_this_week')->default(0);
            $table->bigInteger('xp_this_month')->default(0);
            $table->timestamp('last_xp_activity')->nullable();
            $table->timestamps();
            $table->unique('user_id');
            $table->index('total_xp');
            $table->index('current_level');
        });

        // XP Transactions Table - Immutable ledger of all XP changes
        Schema::create('xp_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // quiz_completed, correct_answer, etc.
            $table->string('reference_type')->nullable(); // quiz, question, badge, manual
            $table->bigInteger('reference_id')->nullable(); // quiz_id, question_id, badge_id
            $table->integer('xp_amount');
            $table->enum('direction', ['earned', 'deducted'])->default('earned');
            $table->text('description')->nullable();
            $table->string('source')->default('system'); // system, admin, user
            $table->bigInteger('admin_id')->nullable(); // If admin adjustment
            $table->string('admin_note')->nullable(); // Reason for admin adjustment
            $table->string('unique_identifier')->nullable(); // For idempotency
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();
            $table->index('user_id');
            $table->index('event_type');
            $table->index('reference_type');
            $table->index('direction');
            $table->index('created_at');
            $table->unique('unique_identifier');
        });

        // Levels Table - XP level definitions
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->integer('level_number')->unique();
            $table->string('name');
            $table->bigInteger('required_xp');
            $table->text('description')->nullable();
            $table->string('badge_icon')->nullable(); // Icon/image path
            $table->string('badge_color')->nullable(); // Badge color
            $table->integer('reward_xp')->default(0); // Bonus XP for reaching level
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index('level_number');
            $table->index('required_xp');
            $table->index('is_active');
        });

        // Badges Table - Achievement badges
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Icon/image path
            $table->string('color')->nullable(); // Badge color
            $table->string('condition_type'); // quiz_count, question_count, streak_days, etc.
            $table->json('condition_data'); // Condition parameters
            $table->integer('reward_xp')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('times_earned')->default(0); // How many users have earned
            $table->timestamps();
            $table->softDeletes();
            $table->index('slug');
            $table->index('is_active');
        });

        // User Badges Table - Tracks earned badges per user
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('badge_id')->constrained()->onDelete('cascade');
            $table->timestamp('earned_at');
            $table->timestamps();
            $table->unique(['user_id', 'badge_id']);
            $table->index('user_id');
            $table->index('badge_id');
            $table->index('earned_at');
        });

        // User Streaks Table - Tracks user streaks
        Schema::create('user_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->timestamp('last_activity')->nullable();
            $table->timestamp('streak_started_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
            $table->index('current_streak');
            $table->index('longest_streak');
        });

        // Quiz XP Settings Table - Override global XP rules per quiz
        Schema::create('quiz_xp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->boolean('xp_enabled')->default(true);
            $table->boolean('use_global_rules')->default(true);
            $table->integer('completion_xp')->nullable();
            $table->integer('passing_bonus_xp')->nullable();
            $table->integer('perfect_score_bonus_xp')->nullable();
            $table->integer('first_attempt_bonus_xp')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('exam_id');
        });

        // Gamification Settings Table - Global configuration
        Schema::create('gamification_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('xp_system_enabled')->default(true);
            $table->boolean('levels_enabled')->default(true);
            $table->boolean('badges_enabled')->default(true);
            $table->boolean('streaks_enabled')->default(true);
            $table->boolean('leaderboard_enabled')->default(true);

            // XP Limits
            $table->integer('daily_xp_cap')->default(5000);
            $table->integer('weekly_xp_cap')->default(30000);
            $table->integer('max_xp_per_quiz')->default(500);

            // Repeat Attempt Multiplier
            $table->integer('first_attempt_percentage')->default(100);
            $table->integer('second_attempt_percentage')->default(50);
            $table->integer('third_plus_attempt_percentage')->default(0);

            // Notifications
            $table->boolean('notify_xp_earned')->default(true);
            $table->boolean('notify_level_up')->default(true);
            $table->boolean('notify_badge_earned')->default(true);
            $table->boolean('notify_streak')->default(true);

            // Leaderboard Settings
            $table->string('leaderboard_sort_by')->default('xp'); // xp, accuracy, quiz_count
            $table->integer('leaderboard_period')->default(2); // 1=daily, 2=weekly, 3=monthly, 4=all_time
            $table->integer('leaderboard_users_shown')->default(100);

            $table->timestamps();
        });

        // User XP Claim History - Track claimed daily/weekly bonuses
        Schema::create('user_xp_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('claim_type'); // daily_login, weekly_bonus, etc.
            $table->integer('xp_amount');
            $table->timestamp('claimed_at');
            $table->timestamps();
            $table->unique(['user_id', 'claim_type', 'claimed_at']);
            $table->index('user_id');
            $table->index('claim_type');
        });
    }

    public function down(): void {
        Schema::dropIfExists('user_xp_claims');
        Schema::dropIfExists('gamification_settings');
        Schema::dropIfExists('quiz_xp_settings');
        Schema::dropIfExists('user_streaks');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('levels');
        Schema::dropIfExists('xp_transactions');
        Schema::dropIfExists('user_xp');
        Schema::dropIfExists('xp_rules');
    }
};
