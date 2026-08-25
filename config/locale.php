<?php

/*
|--------------------------------------------------------------------------
| Multilingual content configuration
|--------------------------------------------------------------------------
| Drives the (upcoming) HasTranslations accessor and the /hi URL layer.
| Creating this file changes NO behaviour on its own — it is only read by the
| translation trait / routing which are implemented in later, separately
| approved steps.
|
| Model:
|   - English is the base language and lives in the existing columns (no suffix).
|   - Each additional translatable locale stores its content in "<field>_<code>"
|     columns (e.g. title_hi) and is served under a "/<code>" URL prefix.
*/

return [

    // Base language: stored in the existing (un-suffixed) columns and served at
    // the site root. Never given a URL prefix.
    'default' => 'en',

    // Locales that have "_<code>" content columns in the database.
    'translatable' => ['hi'],

    // Public URL locales. 'en' = root (/...), others = "/<code>/..." prefix.
    'supported' => ['en', 'hi'],

    // When the requested locale's content column is empty, fall back to the
    // English (base) column so a page is never blank. Central + configurable.
    'content_fallback' => true,

    // Column suffix per locale. '' means the base column (English).
    'suffix' => [
        'en' => '',
        'hi' => '_hi',
    ],

    // URL path prefix used for translated (non-default) locales.
    'prefix' => 'hi',

    /*
    | Locale-resolution model for LanguageMiddleware (deterministic, URL-first):
    |
    |   1. Path under the "/hi" prefix          -> Hindi   (and remembered in session)
    |   2. Path matched by "session_paths"       -> whatever the session holds
    |                                                (admin/user flows + the non-indexed
    |                                                 quiz-play journey follow the language
    |                                                 the visitor last chose)
    |   3. Everything else (public content GET)  -> English (and remembered in session)
    |
    | So a PUBLIC CONTENT URL always identifies its language (root = English,
    | /hi = Hindi) — the same URL can never render two languages. The user-flow
    | pages (quiz attempt/result, rooms, profile, admin) instead inherit the last
    | chosen language, so a Hindi visitor keeps Hindi while playing a quiz even
    | though those routes have no /hi mirror.
    */
    'session_paths' => [
        'admin', 'admin/*',
        'user', 'user/*',
        'ipn', 'ipn/*',
        'api', 'api/*',
        // Public, non-indexed quiz-play + account journey (no /hi mirror).
        'quiz/attempt/*', 'quiz/result/*', 'quiz/review/*', 'quiz/*/start',
        'rooms', 'rooms/*',
        'profile', 'profile/*',
        'bookmark/*',
    ],
];
