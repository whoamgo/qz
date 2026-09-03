<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core Social Media Center tables: connected accounts, the single settings row
 * and the append-only audit trail.
 *
 * Credentials live in `social_accounts.credentials` as a single encrypted blob
 * (see SocialAccount's `encrypted:array` cast). Nothing in this schema is
 * intended to reach the browser - the model hides the column and the API
 * resources never serialise it.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30);

            // Human label shown in the UI, plus the platform's own identifiers.
            $table->string('name', 120)->nullable();
            $table->string('username', 120)->nullable();
            $table->string('external_id', 191)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->string('profile_url', 500)->nullable();

            // 0 disconnected, 1 connected, 2 token expired, 3 permission issue, 4 error
            $table->unsignedTinyInteger('status')->default(0);
            $table->boolean('is_default')->default(false);

            // Encrypted blob: access token, refresh token, page/business ids,
            // per-account app credentials. Never selected into a view.
            $table->text('credentials')->nullable();
            $table->text('scopes')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('token_refreshed_at')->nullable();

            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error', 500)->nullable();

            $table->unsignedBigInteger('followers')->default(0);
            $table->unsignedBigInteger('following')->default(0);
            $table->unsignedBigInteger('media_count')->default(0);

            $table->json('meta')->nullable();

            $table->unsignedBigInteger('connected_by')->nullable();
            $table->timestamps();

            $table->index(['platform', 'status'], 'sa_platform_status_idx');
            $table->unique(['platform', 'external_id'], 'sa_platform_external_uq');
        });

        Schema::create('social_settings', function (Blueprint $table) {
            $table->id();

            $table->boolean('enabled')->default(true);
            $table->boolean('require_approval')->default(false);
            $table->boolean('utm_enabled')->default(true);
            $table->string('utm_medium', 60)->default('social');
            $table->string('utm_source_map', 500)->nullable();

            $table->boolean('auto_draft_from_quiz')->default(false);
            $table->boolean('ai_enabled')->default(false);
            $table->boolean('ai_auto_publish')->default(false);

            // Publishing engine knobs.
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->unsignedInteger('retry_base_seconds')->default(60);
            $table->unsignedInteger('request_timeout')->default(120);
            $table->unsignedInteger('job_lease_seconds')->default(600);
            $table->unsignedInteger('schedule_grace_minutes')->default(60);

            // Upload limits enforced server-side (megabytes).
            $table->unsignedInteger('max_image_mb')->default(15);
            $table->unsignedInteger('max_video_mb')->default(512);

            $table->string('default_timezone', 64)->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
        });

        Schema::create('social_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name', 120)->nullable();
            $table->string('action', 80);
            $table->string('platform', 30)->nullable();

            // Polymorphic-ish pointer kept loose so deleting a post does not
            // orphan the log entry (audit rows are never cascade-deleted).
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('result', 20)->default('success');
            $table->text('description')->nullable();

            // Redacted context only - SocialAuditLogger strips token-ish keys.
            $table->json('context')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at'], 'sal_action_created_idx');
            $table->index(['subject_type', 'subject_id'], 'sal_subject_idx');
            $table->index('admin_id', 'sal_admin_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('social_audit_logs');
        Schema::dropIfExists('social_settings');
        Schema::dropIfExists('social_accounts');
    }
};
