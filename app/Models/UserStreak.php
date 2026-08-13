<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStreak extends Model {
    protected $table = 'user_streaks';
    protected $fillable = [
        'user_id',
        'current_streak',
        'longest_streak',
        'last_activity',
        'streak_started_at',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'streak_started_at' => 'datetime',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
