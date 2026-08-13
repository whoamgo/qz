<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Frontend extends Model
{

    /** Public-site caches derived from frontend content. */
    const WEBSITE_CACHE_KEYS = [
        'website.home.hero.slides',
        'website.home.testimonials',
    ];

    /**
     * Banner and testimonial blocks on the homepage are cached. Without this,
     * content edited in the Frontend Manager would not appear on the site
     * until the cache expired half an hour later.
     */
    protected static function booted() {
        $flush = function () {
            foreach (self::WEBSITE_CACHE_KEYS as $key) {
                \Illuminate\Support\Facades\Cache::forget($key);
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }

    protected $casts = [
        'data_values' => 'object',
        'seo_content'=>'object'
    ];

    public static function scopeGetContent($data_keys)
    {
        return Frontend::where('data_keys', $data_keys);
    }
}
