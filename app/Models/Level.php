<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Level extends Model {
    use SoftDeletes;

    protected $table = 'levels';
    protected $fillable = [
        'level_number',
        'name',
        'required_xp',
        'description',
        'badge_icon',
        'badge_color',
        'reward_xp',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query) {
        return $query->orderBy('level_number', 'asc');
    }

    public function scopeByXp($query, $xp) {
        return $query->where('required_xp', '<=', $xp)->orderBy('required_xp', 'desc');
    }

    public static function getLevelByXp($xp) {
        return self::active()->byXp($xp)->first();
    }
}
