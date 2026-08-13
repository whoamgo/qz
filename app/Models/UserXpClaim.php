<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserXpClaim extends Model {
    protected $table = 'user_xp_claims';
    protected $fillable = [
        'user_id',
        'claim_type',
        'xp_amount',
        'claimed_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function scopeByType($query, $type) {
        return $query->where('claim_type', $type);
    }
}
