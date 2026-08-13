<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class XpRule extends Model {
    use SoftDeletes;

    protected $table = 'xp_rules';
    protected $fillable = [
        'name',
        'key',
        'description',
        'xp_value',
        'is_active',
        'daily_limit',
        'weekly_limit',
        'cooldown_minutes',
        'sort_order',
        'category',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'json',
    ];

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category) {
        return $query->where('category', $category);
    }

    public function transactions() {
        return $this->hasMany(XpTransaction::class, 'event_type', 'key');
    }
}
