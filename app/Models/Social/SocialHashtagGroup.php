<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SocialHashtagGroup extends Model {

    protected $table = 'social_hashtag_groups';

    protected $fillable = [
        'name', 'slug', 'description', 'hashtags', 'hashtag_count',
        'platform', 'is_active', 'usage_count', 'created_by',
    ];

    protected $casts = [
        'hashtags'  => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void {
        static::saving(function (self $group) {
            if (!$group->slug) {
                $base = Str::slug($group->name) ?: 'hashtags';
                $slug = $base;
                $i    = 1;
                while (static::where('slug', $slug)->where('id', '!=', $group->id ?? 0)->exists()) {
                    $slug = $base . '-' . (++$i);
                }
                $group->slug = $slug;
            }
            $group->hashtags      = static::normalise($group->hashtags);
            $group->hashtag_count = count($group->hashtags);
        });
    }

    /**
     * Accepts whatever the admin pasted (spaces, commas, newlines, with or
     * without #) and returns a clean, de-duplicated, lowercase list.
     */
    public static function normalise($input): array {
        if (is_string($input)) {
            $input = preg_split('/[\s,]+/', $input) ?: [];
        }

        return collect($input)
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->map(fn ($tag) => '#' . preg_replace('/[^\p{L}\p{N}_]/u', '', ltrim($tag, '#')))
            ->filter(fn ($tag) => mb_strlen($tag) > 1)
            ->map(fn ($tag) => mb_strtolower($tag))
            ->unique()
            ->values()
            ->all();
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function asString(): string {
        return implode(' ', $this->hashtags ?? []);
    }
}
