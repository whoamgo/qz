<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a role to admin accounts.
 *
 * The panel had no notion of admin roles before this, so every existing admin
 * is a super admin - downgrading them here would lock people out of the panel
 * they already administer. Only the Social Media Center enforces this column
 * today (see App\Services\Social\SocialPermission); the rest of the panel keeps
 * its previous "any authenticated admin" behaviour.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('role', 30)->default('super_admin')->after('username');
        });
    }

    public function down(): void {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
