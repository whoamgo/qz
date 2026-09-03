<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SocialCampaign extends Model {

    protected $table = 'social_campaigns';

    protected $fillable = [
        'name', 'slug', 'description', 'start_date', 'end_date', 'platforms',
        'hashtags', 'utm_campaign', 'status', 'created_by',
    ];

    protected $casts = [
        'platforms'  => 'array',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    const STATUSES = [
        'draft'     => 'Draft',
        'active'    => 'Active',
        'paused'    => 'Paused',
        'completed' => 'Completed',
        'archived'  => 'Archived',
    ];

    protected static function booted(): void {
        static::saving(function (self $campaign) {
            if (!$campaign->slug) {
                $campaign->slug = static::uniqueSlug($campaign->name);
            }
            // Default the UTM value to the slug so links are trackable without
            // the admin having to think about it.
            if (!$campaign->utm_campaign) {
                $campaign->utm_campaign = Str::of($campaign->slug)->replace('-', '_')->limit(100, '');
            }
        });
    }

    public static function uniqueSlug(string $name): string {
        $base = Str::slug($name) ?: 'campaign';
        $slug = $base;
        $i    = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    public function posts() {
        return $this->hasMany(SocialPost::class, 'social_campaign_id');
    }

    public function scopeActive($query) {
        return $query->where('status', 'active');
    }

    public function getStatusClassAttribute(): string {
        return match ($this->status) {
            'active'    => 'success',
            'paused'    => 'warning',
            'completed' => 'info',
            'archived'  => 'dark',
            default     => 'secondary',
        };
    }

    public function isRunning(): bool {
        if ($this->status !== 'active') {
            return false;
        }
        $today = now()->toDateString();
        return (!$this->start_date || $this->start_date->toDateString() <= $today)
            && (!$this->end_date || $this->end_date->toDateString() >= $today);
    }
}
