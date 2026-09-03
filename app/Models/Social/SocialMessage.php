<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/** A direct message thread entry (WhatsApp / Messenger / Instagram DM). */
class SocialMessage extends Model {

    protected $table = 'social_messages';

    protected $fillable = [
        'social_account_id', 'platform', 'conversation_id', 'external_id',
        'direction', 'sender_name', 'sender_handle', 'message', 'attachments',
        'is_read', 'sent_at', 'meta',
    ];

    protected $casts = [
        'attachments' => 'array',
        'meta'        => 'array',
        'is_read'     => 'boolean',
        'sent_at'     => 'datetime',
    ];

    public function account() {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function scopeUnread($query) {
        return $query->where('is_read', false)->where('direction', 'in');
    }
}
