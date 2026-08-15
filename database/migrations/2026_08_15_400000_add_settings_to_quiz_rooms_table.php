<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-room overrides for how many questions to play and the time limit.
 * Both nullable → when the host leaves them empty the room falls back to the
 * quiz's own question_limit and time_limit, so nothing changes for defaults.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('quiz_rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('question_count')->nullable()->after('max_players');
            $table->unsignedSmallInteger('time_limit')->nullable()->after('question_count'); // minutes; 0 = no limit
        });
    }

    public function down(): void {
        Schema::table('quiz_rooms', function (Blueprint $table) {
            $table->dropColumn(['question_count', 'time_limit']);
        });
    }
};
