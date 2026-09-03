<?php

namespace App\Models\Social;

use App\Constants\SocialStatus;
use Illuminate\Database\Eloquent\Model;

/** An inbound comment or mention pulled from a platform's official API. */
class SocialComment extends Model {

    protected $table = 'social_comments';

    protected $fillable = [
        'social_account_id', 'social_post_platform_id', 'platform', 'kind',
        'external_id', 'parent_external_id', 'platform_post_id',
        'author_name', 'author_handle', 'author_avatar', 'message', 'permalink',
        'is_read', 'is_replied', 'is_hidden', 'posted_at', 'meta',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'is_replied' => 'boolean',
        'is_hidden'  => 'boolean',
        'posted_at'  => 'datetime',
        'meta'       => 'array',
    ];

    public function account() {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function target() {
        return $this->belongsTo(SocialPostPlatform::class, 'social_post_platform_id');
    }

    public function scopeUnread($query) {
        return $query->where('is_read', false);
    }

    public function getIconAttribute(): string {
        return SocialStatus::platformIcon($this->platform);
    }
}
