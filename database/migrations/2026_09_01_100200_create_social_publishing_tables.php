<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The durable publishing queue.
 *
 * This install has no `jobs` table and no queue worker, so the Social Center
 * carries its own persistent job table instead of relying on Laravel's queue.
 * Rows are claimed with a leased `reserved_at` + `reserved_token` so two
 * concurrent runners (cron overlapping with a manual run) can never process the
 * same job twice, and every attempt is recorded separately for the UI.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('social_publish_jobs', function (Blueprint $table) {
            $table->id();

            // publish | retry | sync_analytics | refresh_token | sync_inbox
            $table->string('type', 30)->default('publish');

            $table->unsignedBigInteger('social_post_id')->nullable();
            $table->unsignedBigInteger('social_post_platform_id')->nullable();
            $table->unsignedBigInteger('social_account_id')->nullable();
            $table->string('platform', 30)->nullable();

            // queued | processing | completed | failed | cancelled
            $table->string('status', 20)->default('queued');

            // Unique across the table: the same logical unit of work can only
            // ever be enqueued once, which is what stops a double-clicked
            // publish from producing two platform posts.
            $table->string('idempotency_key', 100);

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->unsignedTinyInteger('priority')->default(5);

            $table->timestamp('available_at')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->string('reserved_token', 64)->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->json('payload')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique('idempotency_key', 'spj_idem_uq');
            $table->index(['status', 'available_at', 'priority'], 'spj_claim_idx');
            $table->index('social_post_id', 'spj_post_idx');
            $table->index('reserved_at', 'spj_reserved_idx');

            $table->foreign('social_post_id', 'spj_post_fk')
                ->references('id')->on('social_posts')->cascadeOnDelete();
            $table->foreign('social_post_platform_id', 'spj_target_fk')
                ->references('id')->on('social_post_platforms')->cascadeOnDelete();
            $table->foreign('social_account_id', 'spj_account_fk')
                ->references('id')->on('social_accounts')->nullOnDelete();
        });

        Schema::create('social_publish_attempts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('social_publish_job_id')->nullable();
            $table->unsignedBigInteger('social_post_platform_id')->nullable();
            $table->string('platform', 30)->nullable();

            $table->unsignedTinyInteger('attempt_no')->default(1);

            // success | failed | skipped
            $table->string('status', 20)->default('failed');

            $table->string('endpoint', 255)->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();

            // Redacted summaries only. SocialHttpClient strips Authorization
            // headers and token query parameters before anything lands here.
            $table->json('request_summary')->nullable();
            $table->text('response_summary')->nullable();

            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('retryable')->default(false);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamps();

            $table->index('social_publish_job_id', 'spa_job_idx');
            $table->index('social_post_platform_id', 'spa_target_idx');
        });

        Schema::create('social_automations', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->text('description')->nullable();

            // daily_quiz | current_affairs | new_blog | new_quiz | custom
            $table->string('source_type', 40)->default('custom');
            $table->json('source_config')->nullable();

            // daily | weekly | monthly | interval
            $table->string('frequency', 20)->default('daily');
            $table->json('days_of_week')->nullable();
            $table->time('run_time')->nullable();
            $table->unsignedInteger('interval_minutes')->nullable();
            $table->string('timezone', 64)->nullable();

            $table->json('platforms')->nullable();
            $table->unsignedBigInteger('social_template_id')->nullable();
            $table->unsignedBigInteger('social_hashtag_group_id')->nullable();
            $table->unsignedBigInteger('social_campaign_id')->nullable();

            // When false the automation creates a draft/pending post instead of
            // publishing - the emergency stop below overrides everything.
            $table->boolean('auto_publish')->default(false);
            $table->boolean('is_active')->default(false);

            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->text('last_error')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at'], 'sau_active_next_idx');
        });

        Schema::create('social_bulk_imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_name', 191)->nullable();
            $table->string('path', 500)->nullable();

            // pending | previewed | processing | completed | failed | cancelled
            $table->string('status', 20)->default('pending');

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('created_posts')->default(0);

            $table->json('options')->nullable();
            $table->text('error')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('social_bulk_import_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('social_bulk_import_id');
            $table->unsignedInteger('row_number')->default(0);

            $table->json('data')->nullable();
            $table->json('errors')->nullable();

            // pending | valid | invalid | imported | skipped
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('social_post_id')->nullable();

            $table->timestamps();

            $table->index(['social_bulk_import_id', 'status'], 'sbir_import_status_idx');
            $table->foreign('social_bulk_import_id', 'sbir_import_fk')
                ->references('id')->on('social_bulk_imports')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('social_bulk_import_rows');
        Schema::dropIfExists('social_bulk_imports');
        Schema::dropIfExists('social_automations');
        Schema::dropIfExists('social_publish_attempts');
        Schema::dropIfExists('social_publish_jobs');
    }
};
