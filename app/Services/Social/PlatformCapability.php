<?php

namespace App\Services\Social;

use App\Constants\SocialStatus;

/**
 * What each platform can actually accept.
 *
 * The UI reads this to disable options a platform does not support, and the
 * publisher reads it to validate media *before* burning an API call. Limits are
 * the documented public limits at the time of writing; they are deliberately
 * conservative, because rejecting a 300MB upload locally is much cheaper than
 * discovering the platform rejects it after a long upload.
 *
 * These are compatibility hints, not a promise that a given account has the
 * permission scopes required - that is decided per account at publish time.
 */
class PlatformCapability {

    /**
     * Feature flags per platform.
     *
     * text_only     - can publish with no media at all
     * image / video - accepts that media kind
     * requires_media- must have media (publishing text alone is impossible)
     * multi_media   - carousel / multiple attachments in one post
     * thread        - can chain several posts together
     * first_comment - a comment can be posted immediately after publishing
     * title         - has a distinct title field separate from the body
     * link          - a URL is rendered as a real link (vs. plain text)
     * native_schedule - the platform itself can hold a future publish time
     */
    const MATRIX = [
        SocialStatus::YOUTUBE => [
            'features' => [
                'text_only'       => false,
                'image'           => false,
                'video'           => true,
                'requires_media'  => true,
                'multi_media'     => false,
                'thread'          => false,
                'first_comment'   => true,
                'title'           => true,
                'link'            => true,
                'native_schedule' => true,
                'shorts'          => true,
                'playlist'        => true,
                'visibility'      => true,
                'thumbnail'       => true,
                'tags'            => true,
                'analytics'       => true,
                'inbox'           => true,
            ],
            'limits' => [
                'title_max'        => 100,
                'caption_max'      => 5000,
                'description_max'  => 5000,
                'hashtag_max'      => 15,
                'tags_chars_max'   => 500,
                'image_max_mb'     => 2,      // thumbnail only
                'video_max_mb'     => 2048,
                'video_min_seconds'=> 1,
                'video_max_seconds'=> 43200,
                'short_max_seconds'=> 180,
                'media_max_count'  => 1,
            ],
            'formats' => [
                'image' => ['jpg', 'jpeg', 'png'],
                'video' => ['mp4', 'mov', 'avi', 'mpeg', 'webm'],
            ],
        ],

        SocialStatus::INSTAGRAM => [
            'features' => [
                'text_only'       => false,
                'image'           => true,
                'video'           => true,
                'requires_media'  => true,
                'multi_media'     => true,
                'thread'          => false,
                'first_comment'   => true,
                'title'           => false,
                'link'            => false,   // captions do not linkify
                'native_schedule' => false,
                'reels'           => true,
                'cover_image'     => true,
                'location'        => true,
                'analytics'       => true,
                'inbox'           => true,
            ],
            'limits' => [
                'caption_max'       => 2200,
                'hashtag_max'       => 30,
                'image_max_mb'      => 8,
                'video_max_mb'      => 1024,
                'video_min_seconds' => 3,
                'video_max_seconds' => 900,
                'reel_max_seconds'  => 900,
                'media_max_count'   => 10,
                'aspect_min'        => 0.5625,  // 9:16
                'aspect_max'        => 1.91,
            ],
            'formats' => [
                // The Graph API's image container accepts JPEG only - PNG and
                // WEBP are rejected at container creation, so we block them here
                // rather than letting the publish attempt fail.
                'image' => ['jpg', 'jpeg'],
                'video' => ['mp4', 'mov'],
            ],
        ],

        SocialStatus::FACEBOOK => [
            'features' => [
                'text_only'       => true,
                'image'           => true,
                'video'           => true,
                'requires_media'  => false,
                'multi_media'     => true,
                'thread'          => false,
                'first_comment'   => true,
                'title'           => false,
                'link'            => true,
                'native_schedule' => true,
                'reels'           => true,
                'page_select'     => true,
                'analytics'       => true,
                'inbox'           => true,
            ],
            'limits' => [
                'caption_max'       => 63206,
                'hashtag_max'       => 30,
                'image_max_mb'      => 10,
                'video_max_mb'      => 1024,
                'video_min_seconds' => 1,
                'video_max_seconds' => 14400,
                'reel_max_seconds'  => 90,
                'media_max_count'   => 10,
            ],
            'formats' => [
                'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'video' => ['mp4', 'mov'],
            ],
        ],

        SocialStatus::X => [
            'features' => [
                'text_only'       => true,
                'image'           => true,
                'video'           => true,
                'requires_media'  => false,
                'multi_media'     => true,
                'thread'          => true,
                'first_comment'   => false,
                'title'           => false,
                'link'            => true,
                'native_schedule' => false,
                'analytics'       => true,
                'inbox'           => true,
            ],
            'limits' => [
                // 280 is the limit for standard (non-Premium) accounts. Longer
                // copy is split across a thread instead of being truncated.
                'caption_max'       => 280,
                'hashtag_max'       => 10,
                'image_max_mb'      => 5,
                'video_max_mb'      => 512,
                'video_min_seconds' => 1,
                'video_max_seconds' => 140,
                'media_max_count'   => 4,
                'thread_max_parts'  => 25,
            ],
            'formats' => [
                'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                'video' => ['mp4', 'mov'],
            ],
        ],

        SocialStatus::LINKEDIN => [
            'features' => [
                'text_only'       => true,
                'image'           => true,
                'video'           => true,
                'requires_media'  => false,
                'multi_media'     => true,
                'thread'          => false,
                'first_comment'   => true,
                'title'           => true,
                'link'            => true,
                'native_schedule' => false,
                'analytics'       => true,
                'inbox'           => false,
            ],
            'limits' => [
                'title_max'         => 200,
                'caption_max'       => 3000,
                'hashtag_max'       => 10,
                'image_max_mb'      => 10,
                'video_max_mb'      => 200,
                'video_min_seconds' => 3,
                'video_max_seconds' => 1800,
                'media_max_count'   => 9,
            ],
            'formats' => [
                'image' => ['jpg', 'jpeg', 'png', 'gif'],
                'video' => ['mp4', 'mov'],
            ],
        ],

        SocialStatus::TELEGRAM => [
            'features' => [
                'text_only'       => true,
                'image'           => true,
                'video'           => true,
                'requires_media'  => false,
                'multi_media'     => true,
                'thread'          => false,
                'first_comment'   => false,
                'title'           => false,
                'link'            => true,
                'native_schedule' => false,
                'buttons'         => true,
                'analytics'       => false,  // Bot API exposes no post metrics
                'inbox'           => false,
            ],
            'limits' => [
                // 4096 for a plain message, but only 1024 when the text rides
                // along as a media caption.
                'caption_max'         => 4096,
                'media_caption_max'   => 1024,
                'hashtag_max'         => 20,
                // Bot API hard limit for uploads is 50MB.
                'image_max_mb'        => 10,
                'video_max_mb'        => 50,
                'video_min_seconds'   => 1,
                'video_max_seconds'   => 3600,
                'media_max_count'     => 10,
            ],
            'formats' => [
                'image' => ['jpg', 'jpeg', 'png', 'webp'],
                'video' => ['mp4', 'mov'],
            ],
        ],

        SocialStatus::WHATSAPP => [
            'features' => [
                'text_only'       => true,
                'image'           => true,
                'video'           => true,
                'requires_media'  => false,
                'multi_media'     => false,
                'thread'          => false,
                'first_comment'   => false,
                'title'           => false,
                'link'            => true,
                'native_schedule' => false,
                // WhatsApp is messaging, not a feed. Business-initiated sends
                // outside a 24h customer window must use an approved template.
                'requires_template' => true,
                'analytics'         => false,
                'inbox'             => true,
            ],
            'limits' => [
                'caption_max'       => 1024,
                'hashtag_max'       => 0,
                'image_max_mb'      => 5,
                'video_max_mb'      => 16,
                'video_min_seconds' => 1,
                'video_max_seconds' => 600,
                'media_max_count'   => 1,
            ],
            'formats' => [
                'image' => ['jpg', 'jpeg', 'png'],
                'video' => ['mp4', '3gp'],
            ],
        ],

        SocialStatus::THREADS => [
            'features' => [
                'text_only'       => true,
                'image'           => true,
                'video'           => true,
                'requires_media'  => false,
                'multi_media'     => true,
                'thread'          => true,
                'first_comment'   => false,
                'title'           => false,
                'link'            => true,
                'native_schedule' => false,
                'analytics'       => true,
                'inbox'           => false,
            ],
            'limits' => [
                'caption_max'       => 500,
                'hashtag_max'       => 10,
                'image_max_mb'      => 8,
                'video_max_mb'      => 1024,
                'video_min_seconds' => 1,
                'video_max_seconds' => 300,
                'media_max_count'   => 20,
                'thread_max_parts'  => 10,
            ],
            'formats' => [
                'image' => ['jpg', 'jpeg', 'png'],
                'video' => ['mp4', 'mov'],
            ],
        ],
    ];

    public static function all(): array {
        return self::MATRIX;
    }

    public static function for(string $platform): array {
        return self::MATRIX[$platform] ?? ['features' => [], 'limits' => [], 'formats' => []];
    }

    public static function supports(string $platform, string $feature): bool {
        return (bool) (self::MATRIX[$platform]['features'][$feature] ?? false);
    }

    public static function limit(string $platform, string $key, $default = null) {
        return self::MATRIX[$platform]['limits'][$key] ?? $default;
    }

    public static function formats(string $platform, string $kind): array {
        return self::MATRIX[$platform]['formats'][$kind] ?? [];
    }

    /** Platforms that can carry the given content type at all. */
    public static function platformsFor(string $contentType): array {
        return array_keys(array_filter(self::MATRIX, function ($caps) use ($contentType) {
            return match ($contentType) {
                SocialStatus::CONTENT_TEXT  => (bool) ($caps['features']['text_only'] ?? false),
                SocialStatus::CONTENT_IMAGE => (bool) ($caps['features']['image'] ?? false),
                SocialStatus::CONTENT_VIDEO => (bool) ($caps['features']['video'] ?? false),
                SocialStatus::CONTENT_REEL  => (bool) ($caps['features']['reels'] ?? $caps['features']['shorts'] ?? false),
                SocialStatus::CONTENT_SHORT => (bool) ($caps['features']['shorts'] ?? $caps['features']['reels'] ?? false),
                SocialStatus::CONTENT_LINK  => (bool) ($caps['features']['link'] ?? false),
                default                     => true,
            };
        }));
    }

    /**
     * The matrix in a form the browser can consume to enable/disable controls.
     * Never trust it server-side - it is a convenience mirror, and every rule
     * here is re-checked by MediaValidator and the adapters.
     */
    public static function toJs(): array {
        $out = [];
        foreach (self::MATRIX as $platform => $caps) {
            $out[$platform] = [
                'name'     => SocialStatus::platformName($platform),
                'icon'     => SocialStatus::platformIcon($platform),
                'color'    => SocialStatus::platformColor($platform),
                'features' => $caps['features'],
                'limits'   => $caps['limits'],
                'formats'  => $caps['formats'],
            ];
        }
        return $out;
    }
}
