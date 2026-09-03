<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/**
 * A daily account-level metrics snapshot.
 *
 * Metrics stay null when a platform does not expose them, so charts can
 * distinguish "no data available" from a genuine zero.
 */
class SocialAnalytic extends Model {

    protected $table = 'social_analytics';

    protected $fillable = [
        'social_account_id', 'platform', 'date', 'followers', 'followers_delta',
        'impressions', 'reach', 'views', 'likes', 'comments', 'shares', 'saves',
        'clicks', 'watch_time_seconds', 'posts_published', 'engagement_rate', 'raw',
    ];

    protected $casts = [
        'date'            => 'date',
        'raw'             => 'array',
        'engagement_rate' => 'float',
    ];

    public function account() {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function scopeBetween($query, $from, $to) {
        return $query->whereBetween('date', [$from, $to]);
    }

    /** Interactions counted toward the engagement rate. */
    public function engagements(): int {
        return (int) $this->likes + (int) $this->comments + (int) $this->shares + (int) $this->saves;
    }
}
