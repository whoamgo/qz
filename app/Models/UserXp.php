<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserXp extends Model {
    protected $table = 'user_xp';
    protected $fillable = [
        'user_id',
        'total_xp',
        'current_level',
        'xp_this_week',
        'xp_this_month',
        'last_xp_activity',
    ];

    protected $casts = [
        'last_xp_activity' => 'datetime',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany {
        return $this->hasMany(XpTransaction::class, 'user_id', 'user_id');
    }

    public function badges(): HasMany {
        return $this->hasMany(UserBadge::class, 'user_id', 'user_id');
    }

    public function streak() {
        return $this->hasOne(UserStreak::class, 'user_id', 'user_id');
    }

    public function level() {
        return $this->belongsTo(Level::class, 'current_level', 'level_number');
    }
}
