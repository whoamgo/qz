<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable-content tables (caption templates, hashtag groups), the analytics
 * snapshot store and the unified inbox.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('social_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();

            // daily_quiz | current_affairs | new_blog | new_video | ... | custom
            $table->string('category', 40)->default('custom');

            // Null = generic template usable for every platform.
            $table->string('platform', 30)->nullable();

            $table->string('title_template', 500)->nullable();
            $table->text('caption_template')->nullable();
            $table->longText('description_template')->nullable();
            $table->text('hashtag_template')->nullable();
            $table->string('cta_template', 255)->nullable();

            // Variable names discovered at save time, shown as chips in the UI.
            $table->json('variables')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_active'], 'st_category_active_idx');
        });

        Schema::create('social_hashtag_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();

            // Stored normalised (lowercase, leading #, de-duplicated).
            $table->json('hashtags');
            $table->unsignedSmallInteger('hashtag_count')->default(0);

            $table->string('platform', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('social_analytics', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('social_account_id')->nullable();
            $table->string('platform', 30);
            $table->date('date');

            // Account-level daily snapshot. Absent metrics stay null rather than
            // zero so charts can tell "no data" from "genuinely zero".
            $table->unsignedBigInteger('followers')->nullable();
            $table->integer('followers_delta')->nullable();
            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('saves')->nullable();
            $table->unsignedBigInteger('clicks')->nullable();
            $table->unsignedBigInteger('watch_time_seconds')->nullable();
            $table->unsignedInteger('posts_published')->default(0);
            $table->decimal('engagement_rate', 8, 4)->nullable();

            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'date'], 'san_account_date_uq');
            $table->index(['platform', 'date'], 'san_platform_date_idx');

            $table->foreign('social_account_id', 'san_account_fk')
                ->references('id')->on('social_accounts')->cascadeOnDelete();
        });

        Schema::create('social_comments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('social_account_id')->nullable();
            $table->unsignedBigInteger('social_post_platform_id')->nullable();
            $table->string('platform', 30);

            // comment | mention | reply
            $table->string('kind', 20)->default('comment');

            $table->string('external_id', 191);
            $table->string('parent_external_id', 191)->nullable();
            $table->string('platform_post_id', 191)->nullable();

            $table->string('author_name', 191)->nullable();
            $table->string('author_handle', 191)->nullable();
            $table->string('author_avatar', 500)->nullable();

            $table->text('message')->nullable();
            $table->string('permalink', 500)->nullable();

            $table->boolean('is_read')->default(false);
            $table->boolean('is_replied')->default(false);
            $table->boolean('is_hidden')->default(false);

            $table->timestamp('posted_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'external_id'], 'scm_platform_external_uq');
            $table->index(['is_read', 'posted_at'], 'scm_read_posted_idx');
        });

        Schema::create('social_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('social_account_id')->nullable();
            $table->string('platform', 30);

            $table->string('conversation_id', 191)->nullable();
            $table->string('external_id', 191);

            $table->string('direction', 10)->default('in'); // in | out
            $table->string('sender_name', 191)->nullable();
            $table->string('sender_handle', 191)->nullable();

            $table->text('message')->nullable();
            $table->json('attachments')->nullable();

            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'external_id'], 'smsg_platform_external_uq');
            $table->index(['platform', 'conversation_id'], 'smsg_conversation_idx');
        });

        Schema::create('social_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30);

            // Platform-supplied event id where available, otherwise a hash of
            // the raw body. Unique, so a replayed webhook is a no-op.
            $table->string('event_id', 191);
            $table->string('signature', 191)->nullable();

            $table->string('status', 20)->default('received');
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'event_id'], 'swe_platform_event_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('social_webhook_events');
        Schema::dropIfExists('social_messages');
        Schema::dropIfExists('social_comments');
        Schema::dropIfExists('social_analytics');
        Schema::dropIfExists('social_hashtag_groups');
        Schema::dropIfExists('social_templates');
    }
};
