<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single append-only table backing the first-party analytics system. It holds
 * every event type (page_view, click and business events) with a status so the
 * same table powers page analytics, click analytics, country analytics and the
 * raw debug view. Only rows with status = 'valid' are counted in totals.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            // What + whether it counts.
            $table->string('event_type', 40);              // page_view | click | quiz_started | ...
            $table->string('status', 20)->default('valid'); // valid | duplicate | rate_limited | bot

            // Who (never trusted from the client for user_id — resolved server-side).
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('visitor_id', 40)->nullable();   // first-party anonymous cookie id
            $table->string('session_id', 64)->nullable();

            // Where.
            $table->string('page_path', 191)->nullable();   // 191 keeps it indexable on utf8mb4
            $table->text('page_url')->nullable();
            $table->string('page_title', 255)->nullable();
            $table->text('referer')->nullable();

            // Click / event element details (null for page views).
            $table->string('element_name', 191)->nullable();
            $table->string('element_category', 100)->nullable();
            $table->string('element_id', 191)->nullable();
            $table->string('element_type', 50)->nullable();

            // Network / geo / device.
            $table->string('ip_address', 45)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('country_name', 100)->nullable();
            $table->string('device_type', 20)->nullable();  // desktop | mobile | tablet
            $table->string('browser', 50)->nullable();
            $table->string('operating_system', 50)->nullable();
            $table->text('user_agent')->nullable();

            // Dedup fingerprint (for debugging the duplicate logic).
            $table->string('dedupe_hash', 64)->nullable();

            // Immutable event — only a creation timestamp is kept.
            $table->timestamp('created_at')->nullable();

            // ---- Indexes -------------------------------------------------
            // Primary dashboard access pattern: valid events of a type in a
            // date range. This composite covers most aggregate queries.
            $table->index(['event_type', 'status', 'created_at'], 'ae_type_status_created');

            // Grouping / filtering singles named in the spec.
            $table->index('page_path', 'ae_page_path');
            $table->index('element_name', 'ae_element_name');
            $table->index('country_code', 'ae_country_code');
            $table->index('visitor_id', 'ae_visitor_id');
            $table->index('session_id', 'ae_session_id');
            $table->index('user_id', 'ae_user_id');
            $table->index('ip_address', 'ae_ip_address');
            // Standalone created_at for retention pruning deletes.
            $table->index('created_at', 'ae_created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('analytics_events');
    }
};
