<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/*
| Daily Spin: 1 question -> 10 questions per spin, answered together and submitted
| once. Stores the served question set + the submitted answers + the score. The
| legacy single-question columns (question_id, selected_option_id, is_correct)
| are kept nullable for backward compatibility but are no longer written.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_spin_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_spin_attempts', 'question_ids')) {
                $table->json('question_ids')->nullable()->after('category_id');        // ordered ids served
            }
            if (!Schema::hasColumn('daily_spin_attempts', 'answers')) {
                $table->json('answers')->nullable()->after('question_ids');             // {question_id: option_id}
            }
            if (!Schema::hasColumn('daily_spin_attempts', 'total_questions')) {
                $table->unsignedTinyInteger('total_questions')->default(10)->after('answers');
            }
            if (!Schema::hasColumn('daily_spin_attempts', 'correct_count')) {
                $table->unsignedTinyInteger('correct_count')->default(0)->after('total_questions');
            }
        });

        // The reward is now +5 per correct answer, up to 10 awards in one submit,
        // so lift the rule's per-day limit from 1 to 10 (global XP caps still apply).
        DB::table('xp_rules')->where('key', 'daily_spin')->update([
            'daily_limit' => 10,
            'description' => 'Daily Spin Question Reward (+5 per correct)',
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('daily_spin_attempts', function (Blueprint $table) {
            foreach (['question_ids', 'answers', 'total_questions', 'correct_count'] as $c) {
                if (Schema::hasColumn('daily_spin_attempts', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        DB::table('xp_rules')->where('key', 'daily_spin')->update(['daily_limit' => 1]);
    }
};
