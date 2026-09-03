<?php

namespace App\Models\Social;

use App\Constants\SocialStatus;
use App\Services\Social\PostStateMachine;
use Illuminate\Database\Eloquent\Model;

/**
 * One platform's version of a post, with its own copy, state and result.
 *
 * This is the row the publisher actually acts on. Keeping the result per
 * platform is what makes "YouTube published, X failed" representable instead of
 * collapsing into a single misleading status.
 */
class SocialPostPlatform extends Model {

    protected $table = 'social_post_platforms';

    protected $fillable = [
        'social_post_id', 'social_account_id', 'platform', 'payload', 'status',
        'scheduled_at', 'published_at', 'platform_post_id', 'platform_url',
        'attempts', 'last_attempt_at', 'next_attempt_at',
        'error_code', 'error_message', 'idempotency_key', 'metrics', 'metrics_synced_at',
    ];

    protected $casts = [
        'payload'           => 'array',
        'metrics'           => 'array',
        'scheduled_at'      => 'datetime',
        'published_at'      => 'datetime',
        'last_attempt_at'   => 'datetime',
        'next_attempt_at'   => 'datetime',
        'metrics_synced_at' => 'datetime',
    ];

    /* ------------------------------------------------------------- Relations */

    public function post() {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function account() {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function attemptLogs() {
        return $this->hasMany(SocialPublishAttempt::class, 'social_post_platform_id')->orderByDesc('id');
    }

    /* ---------------------------------------------------------------- Scopes */

    public function scopeFailed($query) {
        return $query->where('status', SocialStatus::FAILED);
    }

    public function scopePublished($query) {
        return $query->where('status', SocialStatus::PUBLISHED);
    }

    /* ------------------------------------------------------------- Payload IO */

    public function value(string $key, $default = null) {
        return data_get($this->payload, $key, $default);
    }

    public function setValue(string $key, $value): void {
        $payload = $this->payload ?? [];
        data_set($payload, $key, $value);
        $this->payload = $payload;
    }

    /* ------------------------------------------------------------ Transitions */

    public function transitionTo(string $status, array $extra = []): void {
        PostStateMachine::assert($this->status, $status, true);
        $this->status = $status;
        foreach ($extra as $key => $value) {
            $this->{$key} = $value;
        }
        $this->save();
    }

    public function markPublished(?string $platformPostId, ?string $url = null): void {
        $this->status           = SocialStatus::PUBLISHED;
        $this->platform_post_id = $platformPostId;
        $this->platform_url     = $url;
        $this->published_at     = now();
        $this->error_code       = null;
        $this->error_message    = null;
        $this->next_attempt_at  = null;
        $this->save();
    }

    public function markFailed(?string $code, ?string $message, ?\DateTimeInterface $nextAttempt = null): void {
        $this->status          = $nextAttempt ? SocialStatus::RETRYING : SocialStatus::FAILED;
        $this->error_code      = $code ? mb_substr($code, 0, 80) : null;
        $this->error_message   = $message;
        $this->next_attempt_at = $nextAttempt;
        $this->save();
    }

    /* ---------------------------------------------------------------- Display */

    public function getPlatformNameAttribute(): string {
        return SocialStatus::platformName($this->platform);
    }

    public function getStatusNameAttribute(): string {
        return SocialStatus::statusName($this->status);
    }

    public function getStatusClassAttribute(): string {
        return SocialStatus::statusClass($this->status);
    }

    public function getIconAttribute(): string {
        return SocialStatus::platformIcon($this->platform);
    }

    /** Metric shortcut used by the posts table and analytics screens. */
    public function metric(string $key, $default = 0) {
        return data_get($this->metrics, $key, $default);
    }
}
