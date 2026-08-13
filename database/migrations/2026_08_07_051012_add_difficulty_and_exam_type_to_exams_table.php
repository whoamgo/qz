<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium')->after('category_id');
            $table->enum('exam_type', ['free', 'paid'])->default('free')->after('difficulty');
            $table->decimal('price', 12, 2)->default(0)->after('exam_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['difficulty', 'exam_type', 'price']);
        });
    }
};
