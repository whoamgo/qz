<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/**
 * The single settings row for the Social Media Center.
 *
 * Platform app credentials (client ids/secrets) intentionally do NOT live here -
 * they come from config/social.php, which reads environment variables, so
 * secrets stay out of the database and out of Git.
 */
class SocialSetting extends Model {

    protected $table = 'social_settings';

    protected $fillable = [
        'enabled', 'require_approval', 'utm_enabled', 'utm_medium', 'utm_source_map',
        'auto_draft_from_quiz', 'ai_enabled', 'ai_auto_publish',
        'max_attempts', 'retry_base_seconds', 'request_timeout', 'job_lease_seconds',
        'schedule_grace_minutes', 'max_image_mb', 'max_video_mb', 'default_timezone', 'meta',
    ];

    protected $casts = [
        'enabled'              => 'boolean',
        'require_approval'     => 'boolean',
        'utm_enabled'          => 'boolean',
        'auto_draft_from_quiz' => 'boolean',
        'ai_enabled'           => 'boolean',
        'ai_auto_publish'      => 'boolean',
        'meta'                 => 'array',
    ];

    protected static ?self $cached = null;

    /** The settings row, created with defaults on first access. */
    public static function config(): self {
        if (static::$cached) {
            return static::$cached;
        }
        return static::$cached = static::firstOrCreate([], [
            'enabled'     => true,
            'utm_enabled' => true,
            'utm_medium'  => 'social',
        ]);
    }

    /** Forget the process-local cache - used after saving in the settings UI. */
    public static function flush(): void {
        static::$cached = null;
    }

    public function timezone(): string {
        return $this->default_timezone ?: config('app.timezone', 'UTC');
    }

    /** utm_source for a platform, honouring the admin's override map. */
    public function utmSource(string $platform): string {
        $map = collect(explode(',', (string) $this->utm_source_map))
            ->mapWithKeys(function ($pair) {
                $parts = explode(':', $pair, 2);
                return count($parts) === 2 ? [trim($parts[0]) => trim($parts[1])] : [];
            });

        return $map->get($platform, $platform);
    }
}
