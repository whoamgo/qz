<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Hindi content columns for bank_questions. All NULLABLE so existing
 * (English) questions remain valid unchanged. Fully reversible.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('bank_questions', function (Blueprint $table) {
            $add = function (string $col, callable $def) use ($table) {
                if (!Schema::hasColumn('bank_questions', $col)) {
                    $def($table);
                }
            };

            $add('question_text_hi', fn($t) => $t->text('question_text_hi')->nullable()->after('question_text'));
            $add('explanation_hi',   fn($t) => $t->text('explanation_hi')->nullable()->after('explanation'));
            $add('hint_hi',          fn($t) => $t->string('hint_hi', 255)->nullable()->after('hint'));
        });
    }

    public function down(): void {
        Schema::table('bank_questions', function (Blueprint $table) {
            foreach (['question_text_hi', 'explanation_hi', 'hint_hi'] as $col) {
                if (Schema::hasColumn('bank_questions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
