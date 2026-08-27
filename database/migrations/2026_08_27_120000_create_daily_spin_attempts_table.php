<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/*
| Daily Spin & Win — one spin per authenticated user per calendar day.
|
| The one-per-day guarantee is enforced by the DB via UNIQUE(user_id, spin_date),
| never by the frontend. XP is awarded through the existing XpService using the
| "daily_spin" rule seeded below, so admins can toggle it / change the reward from
| the existing XP Rules screen without any new admin module.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_spin_attempts')) {
            Schema::create('daily_spin_attempts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->date('spin_date');

                // Which wheel segment the backend chose (visual + which category the
                // question was drawn from). Kept for auditing; never trusted from client.
                $table->string('segment_key', 40)->nullable();
                $table->unsignedTinyInteger('segment_index')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();

                // The question the user must answer, and their response.
                $table->unsignedBigInteger('question_id');
                $table->unsignedBigInteger('selected_option_id')->nullable();
                $table->boolean('is_correct')->nullable();
                $table->unsignedInteger('xp_awarded')->default(0);

                // 'spun'  = wheel resolved, awaiting answer
                // 'answered' = answer submitted (terminal); spin consumed for the day
                $table->string('status', 20)->default('spun');
                $table->timestamp('answered_at')->nullable();
                $table->timestamps();

                // The heart of the daily limit: one row per user per day.
                $table->unique(['user_id', 'spin_date']);
                $table->index('question_id');
                $table->index(['user_id', 'status']);
            });
        }

        // Seed the XP rule the feature awards through. Idempotent so re-running is safe.
        // Reward and on/off are now managed from the existing XP Rules admin screen.
        DB::table('xp_rules')->updateOrInsert(
            ['key' => 'daily_spin'],
            [
                'name'          => 'Daily Spin & Win',
                'description'   => 'Daily Spin Question Reward',
                'xp_value'      => 5,
                'is_active'     => 1,
                'daily_limit'   => 1,   // hard cap at the XP layer too (belt & suspenders)
                'category'      => 'engagement',
                'sort_order'    => 100,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_spin_attempts');
        // Leave the xp_rules row in place: removing it could orphan historical
        // xp_transactions' event_type reference. Deactivate instead if unwanted.
    }
};
