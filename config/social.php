<?php

/*
|--------------------------------------------------------------------------
| Social Media Center - platform credentials
|--------------------------------------------------------------------------
|
| Every platform app credential is read from the environment. Nothing here is
| ever written to the database or rendered into a page: the admin UI only shows
| whether a credential is present ("Configured" / "Not configured"), never the
| value.
|
| A platform whose credentials are missing stays visible in the panel but is
| shown as "Not configured" with the connect button disabled - the UI never
| pretends a platform is available when it is not.
|
*/

return [

    // Where OAuth callbacks land. Must match the redirect URI registered with
    // each platform's developer console exactly.
    'redirect_base' => rtrim(env('SOCIAL_REDIRECT_BASE', env('APP_URL')), '/'),

    /*
    | How long a pending OAuth `state` value stays valid. The state is stored
    | server-side in the session and compared on callback, which is what stops a
    | forged callback from attaching an attacker's account.
    */
    'oauth_state_ttl' => 600,

    /*
    | Publishing engine defaults. The admin-facing values in social_settings
    | override these; these are the fallbacks used before that row exists.
    */
    'http_timeout'        => (int) env('SOCIAL_HTTP_TIMEOUT', 120),
    'http_connect_timeout'=> (int) env('SOCIAL_HTTP_CONNECT_TIMEOUT', 15),
    'max_attempts'        => (int) env('SOCIAL_MAX_ATTEMPTS', 3),
    'retry_base_seconds'  => (int) env('SOCIAL_RETRY_BASE', 60),

    /*
    | Secret used to verify inbound webhooks that do not carry a platform
    | signature of their own (Telegram's secret_token, for example).
    */
    'webhook_secret' => env('SOCIAL_WEBHOOK_SECRET'),

    'platforms' => [

        'youtube' => [
            'label'         => 'YouTube',
            'auth'          => 'oauth2',
            'client_id'     => env('YOUTUBE_CLIENT_ID'),
            'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
            'scopes'        => [
                'https://www.googleapis.com/auth/youtube.upload',
                'https://www.googleapis.com/auth/youtube.readonly',
                'https://www.googleapis.com/auth/youtube.force-ssl',
                'https://www.googleapis.com/auth/yt-analytics.readonly',
            ],
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'     => 'https://oauth2.googleapis.com/token',
            'api_base'      => 'https://www.googleapis.com/youtube/v3',
            'upload_base'   => 'https://www.googleapis.com/upload/youtube/v3',
        ],

        'facebook' => [
            'label'         => 'Facebook',
            'auth'          => 'oauth2',
            'client_id'     => env('FACEBOOK_APP_ID'),
            'client_secret' => env('FACEBOOK_APP_SECRET'),
            'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),
            'scopes'        => [
                'pages_show_list',
                'pages_manage_posts',
                'pages_read_engagement',
                'pages_manage_engagement',
                'read_insights',
                'business_management',
            ],
            'authorize_url' => 'https://www.facebook.com/{version}/dialog/oauth',
            'token_url'     => 'https://graph.facebook.com/{version}/oauth/access_token',
            'api_base'      => 'https://graph.facebook.com/{version}',
        ],

        // Instagram publishing goes through the Facebook Graph API against an
        // Instagram Business account linked to a Page, so it reuses the
        // Facebook app credentials unless separate ones are provided.
        'instagram' => [
            'label'         => 'Instagram',
            'auth'          => 'oauth2',
            'client_id'     => env('INSTAGRAM_CLIENT_ID', env('FACEBOOK_APP_ID')),
            'client_secret' => env('INSTAGRAM_CLIENT_SECRET', env('FACEBOOK_APP_SECRET')),
            'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),
            'scopes'        => [
                'instagram_basic',
                'instagram_content_publish',
                'instagram_manage_comments',
                'instagram_manage_insights',
                'pages_show_list',
                'pages_read_engagement',
                'business_management',
            ],
            'authorize_url' => 'https://www.facebook.com/{version}/dialog/oauth',
            'token_url'     => 'https://graph.facebook.com/{version}/oauth/access_token',
            'api_base'      => 'https://graph.facebook.com/{version}',
        ],

        'threads' => [
            'label'         => 'Threads',
            'auth'          => 'oauth2',
            'client_id'     => env('THREADS_CLIENT_ID'),
            'client_secret' => env('THREADS_CLIENT_SECRET'),
            'scopes'        => [
                'threads_basic',
                'threads_content_publish',
                'threads_manage_insights',
            ],
            'authorize_url' => 'https://threads.net/oauth/authorize',
            'token_url'     => 'https://graph.threads.net/oauth/access_token',
            'api_base'      => 'https://graph.threads.net/v1.0',
        ],

        'x' => [
            'label'         => 'X (Twitter)',
            'auth'          => 'oauth2_pkce',
            'client_id'     => env('X_CLIENT_ID'),
            'client_secret' => env('X_CLIENT_SECRET'),
            'scopes'        => [
                'tweet.read', 'tweet.write', 'users.read', 'offline.access', 'media.write',
            ],
            'authorize_url' => 'https://twitter.com/i/oauth2/authorize',
            'token_url'     => 'https://api.twitter.com/2/oauth2/token',
            'api_base'      => 'https://api.twitter.com/2',
            'upload_url'    => 'https://upload.twitter.com/1.1/media/upload.json',
        ],

        'linkedin' => [
            'label'         => 'LinkedIn',
            'auth'          => 'oauth2',
            'client_id'     => env('LINKEDIN_CLIENT_ID'),
            'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
            'scopes'        => [
                'openid', 'profile', 'email', 'w_member_social',
                // Organisation scopes only apply when posting as a company page.
                'w_organization_social', 'r_organization_social', 'rw_organization_admin',
            ],
            'authorize_url' => 'https://www.linkedin.com/oauth/v2/authorization',
            'token_url'     => 'https://www.linkedin.com/oauth/v2/accessToken',
            'api_base'      => 'https://api.linkedin.com/rest',
            'api_version'   => env('LINKEDIN_API_VERSION', '202409'),
        ],

        // Telegram bots use a static bot token rather than OAuth - the admin
        // pastes it once and it is stored encrypted like any other credential.
        'telegram' => [
            'label'    => 'Telegram',
            'auth'     => 'token',
            'bot_token'=> env('TELEGRAM_BOT_TOKEN'),
            'chat_id'  => env('TELEGRAM_CHAT_ID'),
            'api_base' => 'https://api.telegram.org',
        ],

        'whatsapp' => [
            'label'          => 'WhatsApp Business',
            'auth'           => 'token',
            'access_token'   => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id'=> env('WHATSAPP_PHONE_NUMBER_ID'),
            'business_id'    => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
            'graph_version'  => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),
            'api_base'       => 'https://graph.facebook.com/{version}',
        ],
    ],
];
