<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Badge extends Model {
    use SoftDeletes;

    protected $table = 'badges';
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'condition_type',
        'condition_data',
        'reward_xp',
        'is_active',
        'sort_order',
        'times_earned',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'condition_data' => 'json',
    ];

    public function users() {
        return $this->belongsToMany(User::class, 'user_badges', 'badge_id', 'user_id')
                    ->withTimestamps()
                    ->withPivot('earned_at');
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeByCondition($query, $conditionType) {
        return $query->where('condition_type', $conditionType);
    }
}
