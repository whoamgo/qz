<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Category extends Model {
    use GlobalStatus;

    /** Cache keys on the public site that are derived from categories. */
    const WEBSITE_CACHE_KEYS = [
        'website.nav.categories',
        'website.footer.categories',
        'website.footer.exams',
    ];

    /**
     * The public header, footer and category grids are cached. Without this,
     * an icon or name edited in the admin panel would not appear on the site
     * until the cache expired an hour later.
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

    protected $fillable = ['name', 'slug', 'status', 'image', 'icon', 'parent_id'];

    public function exams() {
        return $this->hasMany(Exam::class);
    }

    public function parent() {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children() {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function allChildren() {
        return $this->children()->with('allChildren');
    }

    public function scopeOnlyParent($query) {
        return $query->whereNull('parent_id');
    }

    public function scopeOnlyChild($query) {
        return $query->whereNotNull('parent_id');
    }

    public function getParentNameAttribute() {
        return $this->parent ? $this->parent->name : '-';
    }

    public function getLevelAttribute() {
        $level = 0;
        $parent = $this->parent;
        while ($parent) {
            $level++;
            $parent = $parent->parent;
        }
        return $level;
    }
}
