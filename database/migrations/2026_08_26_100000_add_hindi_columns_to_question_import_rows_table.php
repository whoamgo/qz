<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Hindi columns on the question-import staging table so the importer can
 * stage optional Hindi content alongside the English row before promotion. All
 * nullable → existing/English-only imports are unaffected. Fully reversible.
 * (Mirrors only the English fields the importer actually stages: question,
 * options A-D, explanation. `hint` is not part of the question import.)
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('question_import_rows', function (Blueprint $table) {
            $add = function (string $col, string $after) use ($table) {
                if (!Schema::hasColumn('question_import_rows', $col)) {
                    $table->text($col)->nullable()->after($after);
                }
            };
            $add('question_hi', 'question');
            $add('option_a_hi', 'option_a');
            $add('option_b_hi', 'option_b');
            $add('option_c_hi', 'option_c');
            $add('option_d_hi', 'option_d');
            $add('explanation_hi', 'explanation');
        });
    }

    public function down(): void {
        Schema::table('question_import_rows', function (Blueprint $table) {
            foreach (['question_hi', 'option_a_hi', 'option_b_hi', 'option_c_hi', 'option_d_hi', 'explanation_hi'] as $col) {
                if (Schema::hasColumn('question_import_rows', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
