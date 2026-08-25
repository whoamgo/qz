<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Hindi columns on the quiz-import staging table so the quiz importer can
 * stage optional Hindi content (quiz title/description + question, options A-D,
 * explanation) alongside the English row before promotion. All nullable →
 * existing/English-only imports are unaffected. Fully reversible.
 * (Mirrors Step 9's question-import staging columns; the quiz importer adds the
 * two quiz-level Hindi fields on top.)
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('quiz_import_rows', function (Blueprint $table) {
            $add = function (string $col, string $after) use ($table) {
                if (!Schema::hasColumn('quiz_import_rows', $col)) {
                    $table->text($col)->nullable()->after($after);
                }
            };
            $add('quiz_title_hi', 'quiz_title');
            $add('quiz_description_hi', 'quiz_description');
            $add('question_hi', 'question');
            $add('option_a_hi', 'option_a');
            $add('option_b_hi', 'option_b');
            $add('option_c_hi', 'option_c');
            $add('option_d_hi', 'option_d');
            $add('explanation_hi', 'explanation');
        });
    }

    public function down(): void {
        Schema::table('quiz_import_rows', function (Blueprint $table) {
            foreach ([
                'quiz_title_hi', 'quiz_description_hi', 'question_hi',
                'option_a_hi', 'option_b_hi', 'option_c_hi', 'option_d_hi', 'explanation_hi',
            ] as $col) {
                if (Schema::hasColumn('quiz_import_rows', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
