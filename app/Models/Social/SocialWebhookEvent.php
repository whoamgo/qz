<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/**
 * A received platform webhook.
 *
 * (platform, event_id) is unique, which is what makes replay protection work:
 * a webhook delivered twice hits the unique index on the second insert and is
 * discarded instead of being processed again.
 */
class SocialWebhookEvent extends Model {

    protected $table = 'social_webhook_events';

    protected $fillable = [
        'platform', 'event_id', 'signature', 'status', 'payload', 'error', 'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    protected $hidden = ['signature'];
}
