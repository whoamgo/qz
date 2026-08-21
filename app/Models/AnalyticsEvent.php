<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per tracked interaction (page view, click or business event).
 * Append-only: rows are never updated, so timestamps are disabled and only
 * created_at is kept. Only status = 'valid' rows are counted in analytics.
 */
class AnalyticsEvent extends Model {
    public $timestamps = false;

    protected $fillable = [
        'event_type', 'status', 'user_id', 'visitor_id', 'session_id',
        'page_path', 'page_url', 'page_title', 'referer',
        'element_name', 'element_category', 'element_id', 'element_type',
        'ip_address', 'country_code', 'country_name',
        'device_type', 'browser', 'operating_system', 'user_agent',
        'dedupe_hash', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'user_id'    => 'integer',
    ];

    // Status constants — the single source of truth for event acceptance.
    const STATUS_VALID        = 'valid';
    const STATUS_DUPLICATE    = 'duplicate';
    const STATUS_RATE_LIMITED = 'rate_limited';
    const STATUS_BOT          = 'bot';

    const TYPE_PAGE_VIEW = 'page_view';
    const TYPE_CLICK     = 'click';

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* --------------------------------------------------------------- scopes */

    /** Only events that actually count towards analytics totals. */
    public function scopeValid(Builder $q): Builder {
        return $q->where('status', self::STATUS_VALID);
    }

    public function scopeOfType(Builder $q, string $type): Builder {
        return $q->where('event_type', $type);
    }

    /** Inclusive date-range filter on the authoritative server timestamp. */
    public function scopeInRange(Builder $q, $from = null, $to = null): Builder {
        if ($from) {
            $q->where('created_at', '>=', $from);
        }
        if ($to) {
            $q->where('created_at', '<=', $to);
        }
        return $q;
    }
}
