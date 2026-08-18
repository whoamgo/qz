<?php

/*
|--------------------------------------------------------------------------
| SEO configuration
|--------------------------------------------------------------------------
|
| Central, environment-aware knobs for the public site's SEO surface.
| Per-page metadata (title/description/canonical/schema) is still assembled
| in BaseWebsiteController::seo() and rendered by website/layouts/app.blade.php
| — this file only holds site-wide defaults that would otherwise be scattered.
|
*/

return [

    // How long (seconds) the generated XML sitemap is cached. Quizzes and
    // categories change infrequently, so a few hours keeps it cheap without
    // going stale. Cleared automatically on the TTL, or via `php artisan cache:clear`.
    'sitemap_cache_ttl' => (int) env('SEO_SITEMAP_TTL', 21600), // 6 hours

    // How many representative questions (with answers + explanations) are
    // server-rendered on each public quiz detail page so Google can crawl the
    // real quiz content without login. Kept in the 10-15 range Google treats as
    // a useful sample rather than the whole (often 100+) question bank.
    'sample_questions' => (int) env('SEO_SAMPLE_QUESTIONS', 12),

    // How long (seconds) the deterministic per-quiz sample selection is cached.
    // Cleared automatically on the TTL, or via `php artisan cache:clear`.
    'sample_cache_ttl' => (int) env('SEO_SAMPLE_TTL', 21600), // 6 hours

    // Optional Twitter/X handle for twitter:site (e.g. "@quizmitra"). Left
    // empty by default so nothing fake is emitted.
    'twitter_site' => env('SEO_TWITTER_SITE', ''),

];
