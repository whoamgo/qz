<?php

/*
|--------------------------------------------------------------------------
| First-party Website Analytics
|--------------------------------------------------------------------------
| Central configuration for the built-in page-view / click / event tracking
| system (App\Services\AnalyticsTrackingService). Every knob is environment
| driven so behaviour can be tuned per environment without code changes.
*/

return [

    // Master switch. When false, nothing is tracked (endpoint returns 204,
    // middleware is a no-op). Useful for staging or emergencies.
    'enabled' => (bool) env('ANALYTICS_ENABLED', true),

    /*
    | Duplicate-click protection
    |------------------------------------------------------------------
    | For the same (IP + session/visitor + user + page + element) the first
    | click inside this window counts; every repeat within the window is
    | marked "duplicate" and excluded from valid totals. After the window
    | expires the next click counts again.
    */
    'dedupe_seconds' => (int) env('CLICK_DEDUPLICATION_SECONDS', 60),

    /*
    | IP abuse protection (analytics-only, never blocks the website)
    |------------------------------------------------------------------
    | Max VALID analytics events accepted per IP per minute. Events beyond
    | this are stored with status "rate_limited" and excluded from totals.
    */
    'max_events_per_ip_per_minute' => (int) env('ANALYTICS_MAX_CLICKS_PER_IP_PER_MINUTE', 30),

    // Hard request throttle for the public tracking endpoint (requests/min/IP).
    // A structural cap so a flood can never reach application logic at all.
    'endpoint_throttle_per_minute' => (int) env('ANALYTICS_ENDPOINT_THROTTLE', 90),

    // Persist non-valid events (duplicate / rate_limited / bot) so the raw
    // "Events" debug screen can show them. Valid events are always stored.
    // Set false to store ONLY valid events (smallest footprint).
    'store_invalid' => (bool) env('ANALYTICS_STORE_INVALID', true),

    // Days of raw event data to keep. The analytics:prune command deletes
    // anything older. 0 disables pruning.
    'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 90),

    /*
    | Allowed event types
    |------------------------------------------------------------------
    | The public endpoint only accepts these. "page_view" is reserved for the
    | server-side middleware. Add new business events here — nothing else
    | needs to change.
    */
    'event_types' => [
        'click',
        'quiz_started',
        'quiz_completed',
        'quiz_shared',
        'room_created',
        'room_joined',
        'leaderboard_viewed',
        'login_clicked',
        'register_clicked',
    ],

    /*
    | Path prefixes never tracked as page views. Admin, auth, api, the tracking
    | endpoint itself and asset-like paths. Matched against the request path.
    */
    'exclude_paths' => [
        'admin', 'admin/*',
        'api/*',
        'user/login', 'user/register', 'user/password*', 'password*',
        'login', 'register',
        'track/*',
        'sitemap.xml', 'robots.txt', 'llms.txt',
        'cron', 'clear',
        'og/*', 'placeholder-image/*',
    ],

    /*
    | First-party visitor cookie (anonymous, opaque id — no PII). Used for the
    | "Unique Visitors" metric alongside the authenticated user id.
    */
    'visitor_cookie'      => env('ANALYTICS_VISITOR_COOKIE', 'qz_vid'),
    'visitor_cookie_days' => (int) env('ANALYTICS_VISITOR_COOKIE_DAYS', 400),

    /*
    | Country detection
    |------------------------------------------------------------------
    | driver: "none" disables lookups (country = Unknown). "ip-api" uses the
    | free ip-api.com service. Results are CACHED per IP for `ttl_days`, so the
    | same IP never triggers more than one lookup per period. Private/loopback
    | IPs are resolved locally to Unknown and never hit the network. Failures
    | degrade gracefully to Unknown — tracking is never interrupted.
    | For higher volume, switch to a local MaxMind GeoLite2 DB later.
    */
    'geo' => [
        'driver'   => env('ANALYTICS_GEO_DRIVER', 'ip-api'),
        'ttl_days' => (int) env('ANALYTICS_GEO_TTL_DAYS', 30),
        'timeout'  => (float) env('ANALYTICS_GEO_TIMEOUT', 1.5),
        'endpoint' => env('ANALYTICS_GEO_ENDPOINT', 'http://ip-api.com/json/'),
    ],

    /*
    | Bot / crawler detection. A user agent matching any of these (case
    | insensitive substring) is recorded with status "bot" and excluded from
    | valid totals.
    */
    'bot_signatures' => [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit',
        'embedly', 'quora link preview', 'showyoubot', 'outbrain', 'pinterest',
        'developers.google.com', 'google favicon', 'headlesschrome', 'phantomjs',
        'python-requests', 'go-http-client', 'curl', 'wget', 'axios', 'okhttp',
        'ahrefs', 'semrush', 'mj12bot', 'dotbot', 'bingpreview', 'yandex',
        'duckduckbot', 'baiduspider', 'applebot', 'lighthouse', 'gtmetrix',
    ],
];
