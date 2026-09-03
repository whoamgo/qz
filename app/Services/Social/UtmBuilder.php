<?php

namespace App\Services\Social;

use App\Models\Social\SocialPost;
use App\Models\Social\SocialSetting;

/**
 * Adds UTM parameters to Quiz Mitra links so social traffic is attributable.
 *
 * Only first-party links are rewritten. Tagging someone else's URL would be
 * both useless and a small privacy leak (it tells the destination which of our
 * campaigns sent the visitor), so external hosts are returned untouched.
 */
class UtmBuilder {

    /**
     * @return string the URL with utm_* appended, or the original when tagging
     *         is disabled or the URL is not ours.
     */
    public static function apply(?string $url, string $platform, ?SocialPost $post = null, ?string $campaign = null): ?string {
        if (!$url) {
            return $url;
        }

        $settings = SocialSetting::config();

        if (!$settings->utm_enabled || ($post && !$post->utm_enabled)) {
            return $url;
        }

        if (!self::isFirstParty($url)) {
            return $url;
        }

        $params = array_filter([
            'utm_source'   => $settings->utmSource($platform),
            'utm_medium'   => $settings->utm_medium ?: 'social',
            'utm_campaign' => $campaign ?: self::campaignFor($post),
            'utm_content'  => $post ? 'post_' . $post->id : null,
        ]);

        return self::appendQuery($url, $params);
    }

    /** Applies UTM to every link a post carries, keyed for the adapters. */
    public static function forPost(SocialPost $post, string $platform): array {
        $campaign = self::campaignFor($post);

        return array_filter([
            'quiz_url'    => self::apply($post->quiz_url, $platform, $post, $campaign),
            'website_url' => self::apply($post->website_url, $platform, $post, $campaign),
        ]);
    }

    protected static function campaignFor(?SocialPost $post): ?string {
        if (!$post) {
            return null;
        }

        if ($post->relationLoaded('campaign') ? $post->campaign : $post->campaign()->first()) {
            $campaign = $post->campaign;
            if ($campaign && $campaign->utm_campaign) {
                return $campaign->utm_campaign;
            }
        }

        return $post->source_type ? strtolower($post->source_type) : 'social';
    }

    /** True when the URL points at this installation. */
    public static function isFirstParty(string $url): bool {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!$host) {
            // A relative URL is by definition our own.
            return !str_starts_with($url, '//');
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if (!$appHost) {
            return false;
        }

        $host    = preg_replace('/^www\./', '', $host);
        $appHost = preg_replace('/^www\./', '', $appHost);

        return $host === $appHost;
    }

    /**
     * Appends parameters without clobbering existing ones - a link the admin
     * already tagged by hand keeps their values.
     */
    public static function appendQuery(string $url, array $params): string {
        if (!$params) {
            return $url;
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $existing);

        $merged = array_merge($params, $existing);

        $rebuilt = ($parts['scheme'] ?? '') ? $parts['scheme'] . '://' : '';
        $rebuilt .= $parts['host'] ?? '';
        $rebuilt .= isset($parts['port']) ? ':' . $parts['port'] : '';
        $rebuilt .= $parts['path'] ?? '';
        $rebuilt .= $merged ? '?' . http_build_query($merged) : '';
        $rebuilt .= isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $rebuilt ?: $url;
    }
}
