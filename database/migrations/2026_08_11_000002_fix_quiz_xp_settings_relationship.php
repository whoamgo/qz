<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('quiz_xp_settings', function (Blueprint $table) {
            // Drop the old foreign key
            $table->dropForeign(['exam_id']);

            // Rename column from exam_id to quiz_id
            $table->renameColumn('exam_id', 'quiz_id');

            // Add new foreign key for quizzes table
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::table('quiz_xp_settings', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['quiz_id']);

            // Rename column back
            $table->renameColumn('quiz_id', 'exam_id');

            // Add old foreign key back
            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
        });
    }
};
