<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XpTransaction extends Model {
    protected $table = 'xp_transactions';
    protected $fillable = [
        'user_id',
        'event_type',
        'reference_type',
        'reference_id',
        'xp_amount',
        'direction',
        'description',
        'source',
        'admin_id',
        'admin_note',
        'unique_identifier',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function admin() {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function scopeEarned($query) {
        return $query->where('direction', 'earned');
    }

    public function scopeDeducted($query) {
        return $query->where('direction', 'deducted');
    }

    public function scopeByEvent($query, $eventType) {
        return $query->where('event_type', $eventType);
    }

    public function scopeByReference($query, $referenceType, $referenceId) {
        return $query->where('reference_type', $referenceType)->where('reference_id', $referenceId);
    }

    public function scopeDateRange($query, $startDate, $endDate) {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
