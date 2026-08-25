<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Hindi content column for bank_options. NULLABLE so existing options
 * remain valid. Fully reversible.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('bank_options', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_options', 'option_text_hi')) {
                $table->text('option_text_hi')->nullable()->after('option_text');
            }
        });
    }

    public function down(): void {
        Schema::table('bank_options', function (Blueprint $table) {
            if (Schema::hasColumn('bank_options', 'option_text_hi')) {
                $table->dropColumn('option_text_hi');
            }
        });
    }
};
