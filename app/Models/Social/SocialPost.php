<?php

namespace App\Models\Social;

use App\Constants\SocialStatus;
use App\Services\Social\PostStateMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * The master content: written once, then customised per platform in
 * social_post_platforms.
 */
class SocialPost extends Model {

    use SoftDeletes;

    protected $table = 'social_posts';

    protected $fillable = [
        'uuid', 'title', 'caption', 'description', 'hashtags', 'mentions', 'cta',
        'website_url', 'quiz_url', 'social_campaign_id', 'category_id', 'language',
        'content_type', 'status', 'approval_status', 'scheduled_at', 'published_at',
        'source_type', 'source_id', 'utm_enabled', 'ai_generated', 'idempotency_key',
        'created_by', 'approved_by', 'approved_at', 'meta',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at'  => 'datetime',
        'utm_enabled'  => 'boolean',
        'ai_generated' => 'boolean',
        'meta'         => 'array',
    ];

    protected static function booted(): void {
        static::creating(function (self $post) {
            $post->uuid = $post->uuid ?: (string) Str::uuid();
        });
    }

    /* ------------------------------------------------------------- Relations */

    public function targets() {
        return $this->hasMany(SocialPostPlatform::class, 'social_post_id');
    }

    public function campaign() {
        return $this->belongsTo(SocialCampaign::class, 'social_campaign_id');
    }

    public function media() {
        return $this->belongsToMany(SocialMedia::class, 'social_post_media', 'social_post_id', 'social_media_id')
            ->withPivot(['role', 'order', 'platform'])
            ->withTimestamps()
            ->orderBy('social_post_media.order');
    }

    public function jobs() {
        return $this->hasMany(SocialPublishJob::class, 'social_post_id');
    }

    public function creator() {
        return $this->belongsTo(\App\Models\Admin::class, 'created_by');
    }

    public function approver() {
        return $this->belongsTo(\App\Models\Admin::class, 'approved_by');
    }

    /* ---------------------------------------------------------------- Scopes */

    public function scopeStatus($query, $status) {
        return $query->whereIn('status', (array) $status);
    }

    public function scopeDueForPublishing($query) {
        return $query->whereIn('status', [SocialStatus::SCHEDULED, SocialStatus::APPROVED])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    /* ----------------------------------------------------------- Media access */

    public function primaryMedia() {
        return $this->media->firstWhere('pivot.role', 'primary');
    }

    public function thumbnailMedia() {
        return $this->media->firstWhere('pivot.role', 'thumbnail');
    }

    /** Best available image to show in tables and calendars. */
    public function previewImage(): ?string {
        $thumb = $this->thumbnailMedia() ?: $this->primaryMedia();
        if (!$thumb) {
            return null;
        }
        return $thumb->thumbnail_url ?: ($thumb->type === 'image' ? $thumb->url : null);
    }

    /* ------------------------------------------------------------ Transitions */

    /**
     * Moves the post to a new state, refusing moves the state machine forbids.
     *
     * @throws \RuntimeException
     */
    public function transitionTo(string $status, array $extra = []): void {
        PostStateMachine::assert($this->status, $status);
        $this->status = $status;
        foreach ($extra as $key => $value) {
            $this->{$key} = $value;
        }
        $this->save();
    }

    /** Recomputes the master state from the platform targets. */
    public function syncStatusFromTargets(): void {
        $states = $this->targets()->pluck('status')->all();
        if (!$states) {
            return;
        }

        $rolled = PostStateMachine::rollUp($states);
        if ($rolled === $this->status) {
            return;
        }

        // The roll-up is derived truth, so it is applied even where the strict
        // transition table would not allow the jump; the table guards operator
        // actions, not the engine's own bookkeeping.
        $this->status = $rolled;
        if (in_array($rolled, [SocialStatus::PUBLISHED, SocialStatus::PARTIALLY_PUBLISHED], true) && !$this->published_at) {
            $this->published_at = now();
        }
        $this->save();
    }

    /* ---------------------------------------------------------------- Display */

    public function getStatusNameAttribute(): string {
        return SocialStatus::statusName($this->status);
    }

    public function getStatusClassAttribute(): string {
        return SocialStatus::statusClass($this->status);
    }

    public function isEditable(): bool {
        return !in_array($this->status, [SocialStatus::PUBLISHING, SocialStatus::PUBLISHED], true);
    }

    /** Hashtags as a clean array, however the admin typed them. */
    public function hashtagList(): array {
        return collect(preg_split('/[\s,]+/', (string) $this->hashtags))
            ->filter()
            ->map(fn ($t) => '#' . ltrim(trim($t), '#'))
            ->unique()
            ->values()
            ->all();
    }
}
