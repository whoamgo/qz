<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content-side tables: campaigns, the media library, master posts and the
 * per-platform versions of each post.
 *
 * A `social_post` is the master content the admin authors once. Each selected
 * platform gets exactly one `social_post_platforms` row holding the customised
 * copy, its own state and its own publish result - so one platform failing can
 * never mark the whole post published.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('social_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->json('platforms')->nullable();
            $table->text('hashtags')->nullable();
            $table->string('utm_campaign', 100)->nullable();

            // draft | active | paused | completed | archived
            $table->string('status', 20)->default('draft');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date'], 'sc_status_start_idx');
        });

        Schema::create('social_media', function (Blueprint $table) {
            $table->id();

            $table->string('disk', 30)->default('public');
            $table->string('path', 500);
            $table->string('filename', 191);
            $table->string('original_name', 191)->nullable();

            $table->string('type', 20);            // image | video
            $table->string('mime', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size')->default(0);

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration', 10, 2)->nullable();
            $table->string('aspect_ratio', 20)->nullable();

            $table->string('thumbnail_path', 500)->nullable();

            // sha256 of the stored bytes - de-duplicates re-uploads of the same
            // asset instead of filling the library with copies.
            $table->string('checksum', 64)->nullable();

            $table->string('title', 191)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->string('tags', 500)->nullable();
            $table->string('folder', 100)->nullable();

            $table->unsignedInteger('usage_count')->default(0);
            $table->json('meta')->nullable();

            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('checksum', 'sm_checksum_uq');
            $table->index(['type', 'created_at'], 'sm_type_created_idx');
            $table->index('folder', 'sm_folder_idx');
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('title', 191)->nullable();
            $table->text('caption')->nullable();
            $table->longText('description')->nullable();
            $table->text('hashtags')->nullable();
            $table->string('mentions', 500)->nullable();
            $table->string('cta', 191)->nullable();

            $table->string('website_url', 500)->nullable();
            $table->string('quiz_url', 500)->nullable();

            $table->unsignedBigInteger('social_campaign_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('language', 20)->default('english');

            // text | image | video | reel | short | link
            $table->string('content_type', 20)->default('text');

            // Master state - see App\Constants\SocialStatus.
            $table->string('status', 25)->default('draft');
            $table->string('approval_status', 25)->default('not_required');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            // Where this content came from, so Quiz Mitra entities can deep-link
            // back. Loose pointer on purpose: sources live in many tables.
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->boolean('utm_enabled')->default(true);
            $table->boolean('ai_generated')->default(false);

            // Set by the client on first submit; a unique index turns a
            // double-clicked "Publish" into one row instead of two.
            $table->string('idempotency_key', 80)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('idempotency_key', 'sp_idem_uq');
            $table->index(['status', 'scheduled_at'], 'sp_status_sched_idx');
            $table->index(['source_type', 'source_id'], 'sp_source_idx');
            $table->index('social_campaign_id', 'sp_campaign_idx');

            $table->foreign('social_campaign_id', 'sp_campaign_fk')
                ->references('id')->on('social_campaigns')->nullOnDelete();
        });

        Schema::create('social_post_platforms', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('social_post_id');
            $table->unsignedBigInteger('social_account_id')->nullable();
            $table->string('platform', 30);

            // Platform-specific copy after customisation: title, caption,
            // tags, visibility, playlist, first_comment, thread parts, ...
            $table->json('payload')->nullable();

            $table->string('status', 25)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->string('platform_post_id', 191)->nullable();
            $table->string('platform_url', 500)->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();

            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();

            // Sent to the platform when it supports client-side dedupe, and
            // used as our own "did this already run" key.
            $table->string('idempotency_key', 80);

            $table->json('metrics')->nullable();
            $table->timestamp('metrics_synced_at')->nullable();

            $table->timestamps();

            $table->unique('idempotency_key', 'spp_idem_uq');
            $table->unique(['social_post_id', 'platform', 'social_account_id'], 'spp_post_platform_uq');
            $table->index(['status', 'scheduled_at'], 'spp_status_sched_idx');
            $table->index(['platform', 'status'], 'spp_platform_status_idx');

            $table->foreign('social_post_id', 'spp_post_fk')
                ->references('id')->on('social_posts')->cascadeOnDelete();
            $table->foreign('social_account_id', 'spp_account_fk')
                ->references('id')->on('social_accounts')->nullOnDelete();
        });

        Schema::create('social_post_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('social_post_id');
            $table->unsignedBigInteger('social_media_id');

            // primary | thumbnail | extra - lets a post carry a video plus its
            // custom thumbnail plus carousel images without extra columns.
            $table->string('role', 20)->default('primary');
            $table->unsignedSmallInteger('order')->default(0);

            // Null means "applies to every platform"; otherwise this asset is
            // only used for the named platform.
            $table->string('platform', 30)->nullable();

            $table->timestamps();

            $table->index(['social_post_id', 'role'], 'spm_post_role_idx');
            $table->foreign('social_post_id', 'spm_post_fk')
                ->references('id')->on('social_posts')->cascadeOnDelete();
            $table->foreign('social_media_id', 'spm_media_fk')
                ->references('id')->on('social_media')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('social_post_media');
        Schema::dropIfExists('social_post_platforms');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_media');
        Schema::dropIfExists('social_campaigns');
    }
};
