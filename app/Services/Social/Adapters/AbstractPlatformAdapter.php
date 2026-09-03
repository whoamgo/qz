<?php

namespace App\Services\Social\Adapters;

use App\Models\Social\SocialAccount;
use App\Models\Social\SocialPostPlatform;
use App\Services\Social\Contracts\PlatformAdapter;
use App\Services\Social\Support\ConnectionResult;
use App\Services\Social\Support\PlatformException;
use App\Services\Social\Support\SocialHttpClient;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * Shared plumbing for platform adapters.
 *
 * Subclasses implement only what their platform can actually do; everything
 * else falls through to an honest "not supported" here rather than silently
 * returning empty success.
 */
abstract class AbstractPlatformAdapter implements PlatformAdapter {

    protected SocialHttpClient $http;

    public function __construct(?SocialHttpClient $http = null) {
        $this->http = $http ?: SocialHttpClient::make();
    }

    /* ------------------------------------------------------------- Config IO */

    protected function config(?string $key = null, $default = null) {
        $config = config('social.platforms.' . $this->key(), []);
        if ($key === null) {
            return $config;
        }
        return data_get($config, $key, $default);
    }

    /** Substitutes {version} in the Graph-style URLs from config. */
    protected function url(string $template): string {
        return str_replace('{version}', (string) $this->config('graph_version', 'v21.0'), $template);
    }

    public function isConfigured(): bool {
        return (bool) ($this->config('client_id') && $this->config('client_secret'));
    }

    public function redirectUri(): string {
        return rtrim((string) config('social.redirect_base'), '/')
            . '/admin/social/accounts/callback/' . $this->key();
    }

    /* -------------------------------------------------------- Response helper */

    /**
     * Returns the decoded body of a successful response, or throws a classified
     * PlatformException. Every adapter call funnels through here so error
     * handling and redaction are uniform.
     */
    protected function result(Response $response, ?string $fallback = null): array {
        if ($response->successful()) {
            return $response->json() ?? [];
        }
        throw SocialHttpClient::classify($this->key(), $response, $fallback);
    }

    /** The stored token, refusing to proceed when the account is unusable. */
    protected function token(SocialAccount $account): string {
        if (!$account->isConnected()) {
            throw PlatformException::notConnected($this->key());
        }
        if ($account->tokenExpired()) {
            throw PlatformException::tokenExpired($this->key());
        }
        return (string) $account->accessToken();
    }

    /**
     * A publicly reachable URL for a stored asset.
     *
     * Several platforms (Instagram, Threads) ingest media by URL rather than by
     * upload, so the file has to be served from a public host. A localhost URL
     * would fail deep inside the platform with an opaque error, so it is caught
     * here with a message that says what to fix.
     */
    protected function publicMediaUrl(\App\Models\Social\SocialMedia $media): string {
        $url = $media->url;

        if (!preg_match('#^https?://#i', $url)) {
            $url = rtrim((string) config('app.url'), '/') . '/' . ltrim($url, '/');
        }

        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            throw PlatformException::invalidMedia(
                ucfirst($this->key()) . ' downloads media from a public URL, but this site is configured with a local address (' . $host . '). '
                . 'Set APP_URL to the public site address before publishing to ' . ucfirst($this->key()) . '.'
            );
        }

        if (!str_starts_with(strtolower($url), 'https://')) {
            throw PlatformException::invalidMedia(
                ucfirst($this->key()) . ' requires media to be served over HTTPS. Serve the site over HTTPS before publishing.'
            );
        }

        return $url;
    }

    /* --------------------------------------------------- Unsupported defaults */

    public function authorizationUrl(string $state): array {
        throw PlatformException::unsupported($this->key(), 'OAuth authorisation');
    }

    public function exchangeCallback(Request $request, array $session): array {
        throw PlatformException::unsupported($this->key(), 'OAuth callbacks');
    }

    public function refreshToken(SocialAccount $account): bool {
        return false;
    }

    public function fetchPostMetrics(SocialAccount $account, SocialPostPlatform $target): ?array {
        return null;
    }

    public function fetchAccountMetrics(SocialAccount $account, string $date): ?array {
        return null;
    }

    public function fetchComments(SocialAccount $account, int $limit = 50): array {
        return [];
    }

    public function deletePost(SocialAccount $account, SocialPostPlatform $target): bool {
        return false;
    }

    public function testConnection(SocialAccount $account): ConnectionResult {
        try {
            $profile = $this->fetchProfile($account);
            return ConnectionResult::ok('Connection successful.', $profile);
        } catch (PlatformException $e) {
            return ConnectionResult::fail($e->getMessage());
        } catch (\Throwable $e) {
            return ConnectionResult::fail(SocialHttpClient::classifyThrowable($this->key(), $e)->getMessage());
        }
    }

    /* -------------------------------------------------------------- Utilities */

    /**
     * Trims text to a platform's hard limit on a word boundary.
     * Used only where truncating is better than a rejected publish; callers
     * that must not truncate validate the length up front instead.
     */
    protected function clamp(?string $text, int $limit): string {
        $text = trim((string) $text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit - 1);
        $sp  = mb_strrpos($cut, ' ');
        return rtrim($sp && $sp > $limit * 0.6 ? mb_substr($cut, 0, $sp) : $cut) . '…';
    }

    /** Caption plus hashtags, without exceeding the platform's limit. */
    protected function composeBody(?string $caption, array $hashtags, int $limit): string {
        $caption = trim((string) $caption);
        $tags    = trim(implode(' ', $hashtags));

        if (!$tags) {
            return $this->clamp($caption, $limit);
        }

        // Reserve room for the hashtags, then trim the caption - dropping the
        // tags entirely would quietly lose reach the admin asked for.
        $room = $limit - mb_strlen($tags) - 2;
        if ($room < 20) {
            return $this->clamp($caption ?: $tags, $limit);
        }

        return trim($this->clamp($caption, $room) . "\n\n" . $tags);
    }
}
