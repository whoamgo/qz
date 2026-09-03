<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A file in the social media library.
 *
 * Files are stored on the `public` disk under social/, with a random filename -
 * the uploaded name is kept only as a label. `checksum` is the sha256 of the
 * stored bytes and is unique, so re-uploading the same asset returns the
 * existing row instead of duplicating it.
 */
class SocialMedia extends Model {

    use SoftDeletes;

    protected $table = 'social_media';

    protected $fillable = [
        'disk', 'path', 'filename', 'original_name', 'type', 'mime', 'extension',
        'size', 'width', 'height', 'duration', 'aspect_ratio', 'thumbnail_path',
        'checksum', 'title', 'alt_text', 'tags', 'folder', 'usage_count', 'meta', 'uploaded_by',
    ];

    protected $casts = [
        'meta'     => 'array',
        'size'     => 'integer',
        'duration' => 'float',
    ];

    protected $appends = ['url', 'thumbnail_url'];

    public function posts() {
        return $this->belongsToMany(SocialPost::class, 'social_post_media', 'social_media_id', 'social_post_id')
            ->withPivot(['role', 'order', 'platform']);
    }

    public function scopeImages($query) {
        return $query->where('type', 'image');
    }

    public function scopeVideos($query) {
        return $query->where('type', 'video');
    }

    public function getUrlAttribute(): string {
        return Storage::disk($this->disk ?: 'public')->url($this->path);
    }

    public function getThumbnailUrlAttribute(): ?string {
        if (!$this->thumbnail_path) {
            return $this->type === 'image' ? $this->url : null;
        }
        return Storage::disk($this->disk ?: 'public')->url($this->thumbnail_path);
    }

    /** Absolute filesystem path, needed by adapters that stream the file. */
    public function absolutePath(): ?string {
        $disk = Storage::disk($this->disk ?: 'public');
        return method_exists($disk, 'path') ? $disk->path($this->path) : null;
    }

    public function exists(): bool {
        return Storage::disk($this->disk ?: 'public')->exists($this->path);
    }

    public function isVideo(): bool {
        return $this->type === 'video';
    }

    public function getSizeForHumansAttribute(): string {
        return convertToReadableSize($this->size);
    }

    public function getDimensionsAttribute(): ?string {
        return $this->width && $this->height ? $this->width . ' x ' . $this->height : null;
    }

    /** Width / height, or null when the dimensions were never determined. */
    public function ratio(): ?float {
        if (!$this->width || !$this->height) {
            return null;
        }
        return round($this->width / $this->height, 4);
    }

    /** Removes the stored bytes as well as the row. */
    public function purge(): void {
        $disk = Storage::disk($this->disk ?: 'public');
        if ($this->path && $disk->exists($this->path)) {
            $disk->delete($this->path);
        }
        if ($this->thumbnail_path && $disk->exists($this->thumbnail_path)) {
            $disk->delete($this->thumbnail_path);
        }
        $this->forceDelete();
    }
}
