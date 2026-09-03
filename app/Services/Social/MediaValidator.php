<?php

namespace App\Services\Social;

use App\Models\Social\SocialMedia;
use App\Models\Social\SocialPost;
use Illuminate\Support\Collection;

/**
 * Pre-flight checks against each platform's stated requirements.
 *
 * Two severities, and the difference matters:
 *
 *  - "error"   the platform will certainly reject this. Publishing is blocked.
 *  - "warning" it may work but probably will not look right (a landscape video
 *              posted as a Reel), or we could not verify it because ffprobe is
 *              not installed. The admin decides.
 *
 * Nothing here replaces the platform's own validation - it just moves the
 * common failures forward to where they are cheap to fix.
 */
class MediaValidator {

    public function __construct(protected SocialMediaService $mediaService) {}

    /**
     * @return array<int,array{severity:string,platform:string,message:string}>
     */
    public function validate(SocialPost $post, array $platforms, ?Collection $media = null): array {
        $media  = $media ?: $post->media;
        $issues = [];

        foreach ($platforms as $platform) {
            $issues = array_merge($issues, $this->validatePlatform($post, $platform, $media));
        }

        return $issues;
    }

    public function validatePlatform(SocialPost $post, string $platform, Collection $media): array {
        $caps   = PlatformCapability::for($platform);
        $issues = [];

        $images = $media->where('type', 'image')->filter(fn ($m) => ($m->pivot->role ?? 'primary') !== 'thumbnail');
        $video  = $media->firstWhere('type', 'video');

        $add = function (string $severity, string $message) use (&$issues, $platform) {
            $issues[] = ['severity' => $severity, 'platform' => $platform, 'message' => $message];
        };

        $name = \App\Constants\SocialStatus::platformName($platform);

        /* ----------------------------------------------------- Content shape */

        if (!$video && $images->isEmpty()) {
            if (!($caps['features']['text_only'] ?? false)) {
                $add('error', "$name cannot publish text-only posts. Attach an image or a video.");
            }
        }

        if ($video && !($caps['features']['video'] ?? false)) {
            $add('error', "$name does not accept video.");
        }

        if ($images->isNotEmpty() && !($caps['features']['image'] ?? false)) {
            $add('error', "$name does not accept image posts.");
        }

        if ($images->count() > 1 && !($caps['features']['multi_media'] ?? false)) {
            $add('warning', "$name accepts only one image per post - the first will be used.");
        }

        if (($max = $caps['limits']['media_max_count'] ?? null) && $images->count() > $max) {
            $add('warning', "$name accepts at most $max images - the rest will be dropped.");
        }

        /* ----------------------------------------------------- Copy lengths */

        $caption = trim((string) $post->caption);
        if (($limit = $caps['limits']['caption_max'] ?? null) && mb_strlen($caption) > $limit) {
            $severity = ($caps['features']['thread'] ?? false) ? 'warning' : 'warning';
            $add($severity, ($caps['features']['thread'] ?? false)
                ? "The caption is " . mb_strlen($caption) . " characters; $name will split it into a thread."
                : "The caption is " . mb_strlen($caption) . " characters and will be shortened to the $name limit of $limit.");
        }

        if ($platform === \App\Constants\SocialStatus::YOUTUBE) {
            $titleLimit = $caps['limits']['title_max'] ?? 100;
            if (mb_strlen((string) $post->title) > $titleLimit) {
                $add('warning', "The title is longer than YouTube's $titleLimit-character limit and will be shortened.");
            }
            if (!trim((string) $post->title)) {
                $add('error', 'YouTube requires a video title.');
            }
        }

        $hashtags = count($post->hashtagList());
        if (($max = $caps['limits']['hashtag_max'] ?? null) !== null && $max > 0 && $hashtags > $max) {
            $add('warning', "$name allows $max hashtags; the extras will be dropped.");
        }

        /* --------------------------------------------------------- The files */

        foreach ($images as $image) {
            $issues = array_merge($issues, $this->validateFile($image, $platform, 'image', $caps));
        }

        if ($video) {
            $issues = array_merge($issues, $this->validateFile($video, $platform, 'video', $caps));
            $issues = array_merge($issues, $this->validateVideoShape($video, $platform, $post, $caps));
        }

        return $issues;
    }

    protected function validateFile(SocialMedia $media, string $platform, string $kind, array $caps): array {
        $issues = [];
        $name   = \App\Constants\SocialStatus::platformName($platform);

        $formats = $caps['formats'][$kind] ?? [];
        if ($formats && !in_array(strtolower($media->extension), $formats, true)) {
            $issues[] = [
                'severity' => 'error',
                'platform' => $platform,
                'message'  => "$name does not accept .{$media->extension} {$kind}s. Allowed: " . implode(', ', $formats) . '.',
            ];
        }

        $maxMb = $caps['limits'][$kind . '_max_mb'] ?? null;
        if ($maxMb && $media->size > $maxMb * 1024 * 1024) {
            $issues[] = [
                'severity' => 'error',
                'platform' => $platform,
                'message'  => "The {$kind} is {$media->size_for_humans}, over the {$maxMb} MB limit for $name.",
            ];
        }

        return $issues;
    }

    /** Duration and aspect-ratio checks, which only apply to video. */
    protected function validateVideoShape(SocialMedia $video, string $platform, SocialPost $post, array $caps): array {
        $issues = [];
        $name   = \App\Constants\SocialStatus::platformName($platform);
        $short  = in_array($post->content_type, ['reel', 'short'], true);

        if (!$video->duration) {
            if (!$this->mediaService->canProbeVideo()) {
                $issues[] = [
                    'severity' => 'warning',
                    'platform' => $platform,
                    'message'  => "Video duration and dimensions could not be verified because ffprobe is not installed on this server, so $name's duration and aspect-ratio limits were not checked.",
                ];
            }
            return $issues;
        }

        $min = $caps['limits']['video_min_seconds'] ?? null;
        $max = $short
            ? ($caps['limits']['short_max_seconds'] ?? $caps['limits']['reel_max_seconds'] ?? $caps['limits']['video_max_seconds'] ?? null)
            : ($caps['limits']['video_max_seconds'] ?? null);

        if ($min && $video->duration < $min) {
            $issues[] = [
                'severity' => 'error',
                'platform' => $platform,
                'message'  => "The video is {$video->duration}s; $name requires at least {$min}s.",
            ];
        }

        if ($max && $video->duration > $max) {
            $issues[] = [
                'severity' => 'error',
                'platform' => $platform,
                'message'  => "The video is {$video->duration}s; $name allows at most {$max}s" . ($short ? ' for short-form video' : '') . '.',
            ];
        }

        // Vertical formats are a strong convention rather than a hard rule, so
        // a landscape file is a warning: it will publish, but badly cropped.
        if ($short && ($ratio = $video->ratio())) {
            if ($ratio > 1.0) {
                $issues[] = [
                    'severity' => 'warning',
                    'platform' => $platform,
                    'message'  => "The video is landscape ({$video->aspect_ratio}). Short-form video on $name is vertical (9:16) and this will be cropped or letterboxed.",
                ];
            }
        }

        if (!$short && ($ratio = $video->ratio())) {
            $aspectMin = $caps['limits']['aspect_min'] ?? null;
            $aspectMax = $caps['limits']['aspect_max'] ?? null;

            if ($aspectMin && $ratio < $aspectMin) {
                $issues[] = [
                    'severity' => 'error',
                    'platform' => $platform,
                    'message'  => "The video is too tall for $name ({$video->aspect_ratio}).",
                ];
            }
            if ($aspectMax && $ratio > $aspectMax) {
                $issues[] = [
                    'severity' => 'error',
                    'platform' => $platform,
                    'message'  => "The video is too wide for $name ({$video->aspect_ratio}).",
                ];
            }
        }

        return $issues;
    }

    /** True when nothing blocks publishing (warnings are allowed through). */
    public static function blocking(array $issues): array {
        return array_values(array_filter($issues, fn ($i) => $i['severity'] === 'error'));
    }
}
