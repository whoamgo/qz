<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Central, reusable analytics engine. All tracking logic lives here so
 * controllers and middleware stay thin. Nothing here ever throws into the
 * request lifecycle — tracking must never break the site.
 *
 * Status resolution order for every event:
 *   1. bot            → recorded as 'bot'          (excluded from totals)
 *   2. duplicate      → recorded as 'duplicate'    (excluded from totals)
 *   3. rate limited   → recorded as 'rate_limited' (excluded from totals)
 *   4. otherwise      → recorded as 'valid'        (counted)
 */
class AnalyticsTrackingService {
    /* ============================================================ page views */

    /** Records a page view for the given request. Safe to call from terminate(). */
    public function trackPageView(Request $request, ?string $visitorId = null): ?AnalyticsEvent {
        if (!config('analytics.enabled', true)) {
            return null;
        }

        try {
            $ip  = $request->ip();
            $ua  = (string) $request->userAgent();
            $vid = $visitorId ?: $this->resolveVisitorId($request);

            // Bots and IP floods are recorded (for debug) but never counted.
            $status = AnalyticsEvent::STATUS_VALID;
            if ($this->isBot($ua)) {
                $status = AnalyticsEvent::STATUS_BOT;
            } elseif ($this->isRateLimited($ip)) {
                $status = AnalyticsEvent::STATUS_RATE_LIMITED;
            }

            [$countryName, $countryCode] = $this->getVisitorCountry($ip);
            $device = $this->parseUserAgent($ua);

            return $this->record([
                'event_type'   => AnalyticsEvent::TYPE_PAGE_VIEW,
                'status'       => $status,
                'user_id'      => $request->user()?->id,
                'visitor_id'   => $vid,
                'session_id'   => $request->hasSession() ? $request->session()->getId() : null,
                'page_path'    => $this->cleanPath($request->path()),
                'page_url'     => Str::limit($request->fullUrl(), 2000, ''),
                'page_title'   => null, // server side has no reliable title; JS events can carry it
                'referer'      => Str::limit((string) $request->headers->get('referer'), 2000, ''),
                'ip_address'   => $ip,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'device_type'  => $device['device'],
                'browser'      => $device['browser'],
                'operating_system' => $device['os'],
                'user_agent'   => Str::limit($ua, 500, ''),
            ], $status);
        } catch (\Throwable $e) {
            Log::warning('trackPageView failed: ' . $e->getMessage());
            return null;
        }
    }

    /* ================================================= clicks / business events */

    /**
     * Records a click or business event coming from the tracking endpoint.
     * $data holds the client-supplied (already validated) fields; the request
     * is the authoritative source for user, ip, session and timestamp.
     */
    public function trackEvent(string $eventType, array $data, Request $request): array {
        if (!config('analytics.enabled', true)) {
            return ['status' => 'disabled'];
        }

        try {
            $ip  = $request->ip();
            $ua  = (string) $request->userAgent();
            $vid = $this->resolveVisitorId($request);
            $sessionId = $request->hasSession() ? $request->session()->getId() : null;
            $userId    = $request->user()?->id;

            $pagePath = $this->cleanPath($data['page_path'] ?? $request->path());
            $elementName = isset($data['element_name']) ? Str::limit((string) $data['element_name'], 190, '') : null;
            $elementId   = isset($data['element_id']) ? Str::limit((string) $data['element_id'], 190, '') : null;

            // Fingerprint the interaction: same person + page + element.
            $dedupeHash = $this->dedupeHash([
                $ip, $vid, $sessionId, $userId, $eventType, $pagePath,
                $elementId ?: $elementName ?: ($data['element_type'] ?? ''),
            ]);

            // Resolve status in strict priority order.
            if ($this->isBot($ua)) {
                $status = AnalyticsEvent::STATUS_BOT;
            } elseif ($this->isDuplicate($dedupeHash)) {
                $status = AnalyticsEvent::STATUS_DUPLICATE;
            } elseif ($this->isRateLimited($ip)) {
                $status = AnalyticsEvent::STATUS_RATE_LIMITED;
            } else {
                $status = AnalyticsEvent::STATUS_VALID;
            }

            [$countryName, $countryCode] = $this->getVisitorCountry($ip);
            $device = $this->parseUserAgent($ua);

            $this->record([
                'event_type'   => $eventType,
                'status'       => $status,
                'user_id'      => $userId,
                'visitor_id'   => $vid,
                'session_id'   => $sessionId,
                'page_path'    => $pagePath,
                'page_url'     => isset($data['page_url']) ? Str::limit((string) $data['page_url'], 2000, '') : $request->fullUrl(),
                'page_title'   => isset($data['page_title']) ? Str::limit((string) $data['page_title'], 255, '') : null,
                'referer'      => Str::limit((string) $request->headers->get('referer'), 2000, ''),
                'element_name' => $elementName,
                'element_category' => isset($data['element_category']) ? Str::limit((string) $data['element_category'], 100, '') : null,
                'element_id'   => $elementId,
                'element_type' => isset($data['element_type']) ? Str::limit((string) $data['element_type'], 50, '') : null,
                'ip_address'   => $ip,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'device_type'  => $device['device'],
                'browser'      => $device['browser'],
                'operating_system' => $device['os'],
                'user_agent'   => Str::limit($ua, 500, ''),
                'dedupe_hash'  => $dedupeHash,
            ], $status);

            return ['status' => $status];
        } catch (\Throwable $e) {
            Log::warning('trackEvent failed: ' . $e->getMessage());
            return ['status' => 'error'];
        }
    }

    /* ===================================================== protection helpers */

    /** True when the user agent looks like a bot/crawler/headless client. */
    public function isBot(?string $userAgent): bool {
        if (!$userAgent) {
            return true; // no UA at all is almost always automated
        }
        $ua = strtolower($userAgent);
        foreach ((array) config('analytics.bot_signatures', []) as $needle) {
            if ($needle !== '' && str_contains($ua, strtolower($needle))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Duplicate-click gate. The FIRST call for a fingerprint reserves the
     * cooldown window and returns false (not a duplicate); every later call
     * inside the window returns true. Cache::add is atomic, so concurrent
     * requests cannot both win. After the TTL expires the next call counts.
     */
    public function isDuplicate(string $dedupeHash): bool {
        $seconds = max(1, (int) config('analytics.dedupe_seconds', 60));
        // add() returns true only when the key did NOT already exist.
        $firstSeen = Cache::add('analytics:dedupe:' . $dedupeHash, 1, $seconds);
        return !$firstSeen;
    }

    /**
     * IP-level analytics rate limit (never blocks the website itself). Returns
     * true once an IP exceeds the configured valid events per minute.
     */
    public function isRateLimited(?string $ip): bool {
        $max = (int) config('analytics.max_events_per_ip_per_minute', 30);
        if ($max <= 0 || !$ip) {
            return false;
        }
        $key = 'analytics:rl:' . sha1($ip);
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return true;
        }
        RateLimiter::hit($key, 60);
        return false;
    }

    /* ========================================================= country lookup */

    /**
     * [country_name, country_code] for an IP. Cached per IP so the same address
     * never triggers more than one lookup per TTL. Private/loopback IPs and any
     * failure resolve to ['Unknown', null] without ever interrupting tracking.
     */
    public function getVisitorCountry(?string $ip): array {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['Unknown', null]; // empty, private or reserved (e.g. localhost)
        }

        $ttl = now()->addDays((int) config('analytics.geo.ttl_days', 30));

        return Cache::remember('analytics:geo:' . $ip, $ttl, function () use ($ip) {
            if (config('analytics.geo.driver') !== 'ip-api') {
                return ['Unknown', null];
            }
            try {
                $res = Http::timeout((float) config('analytics.geo.timeout', 1.5))
                    ->get(rtrim(config('analytics.geo.endpoint'), '/') . '/' . $ip, [
                        'fields' => 'status,country,countryCode',
                    ]);
                if ($res->ok() && ($res->json('status') === 'success')) {
                    return [$res->json('country') ?: 'Unknown', $res->json('countryCode') ?: null];
                }
            } catch (\Throwable $e) {
                Log::info('geo lookup failed for ' . $ip . ': ' . $e->getMessage());
            }
            return ['Unknown', null];
        });
    }

    /* ================================================================ utility */

    /** Persists the event unless it is a non-valid event we are told to drop. */
    protected function record(array $attributes, string $status): ?AnalyticsEvent {
        if ($status !== AnalyticsEvent::STATUS_VALID && !config('analytics.store_invalid', true)) {
            return null;
        }
        $attributes['created_at'] = now();
        return AnalyticsEvent::create($attributes);
    }

    /**
     * Stable anonymous visitor id from the first-party cookie, falling back to
     * the session id. The middleware is responsible for issuing the cookie.
     */
    public function resolveVisitorId(Request $request): ?string {
        $cookie = config('analytics.visitor_cookie', 'qz_vid');
        $vid = $request->cookie($cookie);
        if ($vid && is_string($vid) && strlen($vid) <= 40) {
            return $vid;
        }
        return $request->hasSession() ? substr($request->session()->getId(), 0, 40) : null;
    }

    /** Generates a fresh opaque visitor id (used by the middleware on first hit). */
    public function newVisitorId(): string {
        return (string) Str::uuid();
    }

    protected function dedupeHash(array $parts): string {
        return hash('sha256', implode('|', array_map(fn($p) => (string) $p, $parts)));
    }

    /** Normalises a path: leading slash, no query string, length-capped. */
    public function cleanPath(?string $path): string {
        $path = '/' . ltrim(parse_url((string) $path, PHP_URL_PATH) ?? (string) $path, '/');
        return Str::limit(rtrim($path, '/') ?: '/', 190, '');
    }

    /** Lightweight device / browser / OS extraction — no external dependency. */
    public function parseUserAgent(?string $ua): array {
        $ua = (string) $ua;
        $l  = strtolower($ua);

        $device = 'desktop';
        if (preg_match('/ipad|tablet|playbook|silk|(android(?!.*mobile))/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/mobi|iphone|ipod|android.*mobile|windows phone|blackberry|opera mini/i', $ua)) {
            $device = 'mobile';
        }

        $browser = 'Other';
        foreach ([
            'Edge' => 'edg', 'Opera' => 'opr', 'Samsung' => 'samsungbrowser',
            'Chrome' => 'chrome', 'Firefox' => 'firefox', 'Safari' => 'safari', 'Internet Explorer' => 'msie',
        ] as $name => $needle) {
            if (str_contains($l, $needle)) { $browser = $name; break; }
        }
        // Safari also contains "safari" in Chrome UAs — the ordering above puts
        // Chrome first, so Safari only wins when Chrome/Edge/Opera are absent.

        $os = 'Other';
        // [name, needle] pairs (not a map) so iPhone and iPad can both map to iOS.
        foreach ([
            ['Android', 'android'], ['iOS', 'iphone'], ['iOS', 'ipad'], ['iOS', 'ipod'],
            ['Windows', 'windows'], ['macOS', 'mac os'], ['Linux', 'linux'],
        ] as [$name, $needle]) {
            if (str_contains($l, $needle)) { $os = $name; break; }
        }

        return ['device' => $device, 'browser' => $browser, 'os' => $os];
    }
}
