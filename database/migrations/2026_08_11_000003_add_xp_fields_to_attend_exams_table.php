<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('attend_exams', function (Blueprint $table) {
            // Add columns if they don't exist
            if (!Schema::hasColumn('attend_exams', 'correct_count')) {
                $table->integer('correct_count')->nullable()->default(0)->after('start_time');
            }
            if (!Schema::hasColumn('attend_exams', 'incorrect_count')) {
                $table->integer('incorrect_count')->nullable()->default(0)->after('correct_count');
            }
            if (!Schema::hasColumn('attend_exams', 'pass_percentage')) {
                $table->integer('pass_percentage')->nullable()->default(0)->after('incorrect_count');
            }
            if (!Schema::hasColumn('attend_exams', 'xp_awarded')) {
                $table->integer('xp_awarded')->nullable()->default(0)->after('pass_percentage');
            }
        });
    }

    public function down(): void {
        Schema::table('attend_exams', function (Blueprint $table) {
            if (Schema::hasColumn('attend_exams', 'correct_count')) {
                $table->dropColumn('correct_count');
            }
            if (Schema::hasColumn('attend_exams', 'incorrect_count')) {
                $table->dropColumn('incorrect_count');
            }
            if (Schema::hasColumn('attend_exams', 'pass_percentage')) {
                $table->dropColumn('pass_percentage');
            }
            if (Schema::hasColumn('attend_exams', 'xp_awarded')) {
                $table->dropColumn('xp_awarded');
            }
        });
    }
};
