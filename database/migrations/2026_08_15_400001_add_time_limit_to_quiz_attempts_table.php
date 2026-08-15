<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-attempt time limit (minutes). Normally null — the attempt uses the quiz's
 * time_limit. A multiplayer room sets this so the whole room plays on the
 * host's chosen clock without touching the underlying quiz.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('time_limit')->nullable()->after('total_questions');
        });
    }

    public function down(): void {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('time_limit');
        });
    }
};
