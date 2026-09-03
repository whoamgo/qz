<?php

namespace App\Services\Social\Adapters;

use App\Constants\SocialStatus;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialMedia;
use App\Models\Social\SocialPostPlatform;
use App\Services\Social\PlatformCapability;
use App\Services\Social\Support\PlatformException;
use App\Services\Social\Support\PublishContext;
use App\Services\Social\Support\PublishResult;
use App\Services\Social\Support\SocialHttpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * X (Twitter) API v2, authorised with OAuth 2.0 + PKCE.
 *
 * PKCE matters here beyond ticking a box: X issues refresh tokens to
 * confidential clients, and the code_verifier is what stops an intercepted
 * authorisation code from being redeemed by anyone but this server. The verifier
 * is generated per connect attempt and held in the admin's session only.
 *
 * Copy longer than the character limit is split into a thread rather than
 * truncated - losing the end of a post is worse than sending two.
 */
class XAdapter extends AbstractPlatformAdapter {

    const MEDIA_UPLOAD_URL = 'https://api.x.com/2/media/upload';
    const CHUNK_BYTES      = 4194304; // 4MB - comfortably under the per-append cap

    public function key(): string {
        return SocialStatus::X;
    }

    protected function apiBase(): string {
        return rtrim((string) $this->config('api_base', 'https://api.twitter.com/2'), '/');
    }

    /* ------------------------------------------------------------------ OAuth */

    public function authorizationUrl(string $state): array {
        if (!$this->isConfigured()) {
            throw PlatformException::notConfigured($this->key());
        }

        $verifier  = Str::random(96);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $url = $this->config('authorize_url') . '?' . http_build_query([
            'response_type'         => 'code',
            'client_id'             => $this->config('client_id'),
            'redirect_uri'          => $this->redirectUri(),
            'scope'                 => implode(' ', (array) $this->config('scopes', [])),
            'state'                 => $state,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        // The verifier never leaves the server: it goes into the session and is
        // read back in exchangeCallback().
        return ['url' => $url, 'session' => ['code_verifier' => $verifier]];
    }

    public function exchangeCallback(Request $request, array $session): array {
        if ($error = $request->query('error_description') ?: $request->query('error')) {
            throw new PlatformException('X authorisation was declined: ' . SocialHttpClient::redactString((string) $error), 'oauth_declined');
        }

        $code     = (string) $request->query('code');
        $verifier = $session['code_verifier'] ?? null;

        if (!$code || !$verifier) {
            throw new PlatformException('The X authorisation could not be completed. Start the connection again.', 'oauth_invalid');
        }

        $response = $this->http->request()
            ->withBasicAuth((string) $this->config('client_id'), (string) $this->config('client_secret'))
            ->asForm()
            ->post((string) $this->config('token_url'), [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->redirectUri(),
                'code_verifier' => $verifier,
            ]);

        $token = $this->result($response, 'X refused to issue an access token.');

        return [
            'credentials' => [
                'access_token'  => $token['access_token'] ?? null,
                'refresh_token' => $token['refresh_token'] ?? null,
            ],
            'expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in'])->toDateTimeString() : null,
            'scopes'     => $token['scope'] ?? implode(' ', (array) $this->config('scopes', [])),
            'profile'    => $this->profileFromToken((string) $token['access_token']),
        ];
    }

    /**
     * X access tokens last about two hours, so refreshing is the normal path
     * rather than an edge case - the token refresh job runs well ahead of expiry.
     */
    public function refreshToken(SocialAccount $account): bool {
        $refresh = $account->credential('refresh_token');
        if (!$refresh || !$this->isConfigured()) {
            return false;
        }

        $response = $this->http->request()
            ->withBasicAuth((string) $this->config('client_id'), (string) $this->config('client_secret'))
            ->asForm()
            ->post((string) $this->config('token_url'), [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh,
            ]);

        if (!$response->successful()) {
            return false;
        }

        $token = $response->json();

        $account->setCredentials([
            'access_token'  => $token['access_token'] ?? $account->accessToken(),
            // X rotates refresh tokens: keeping the old one would break the
            // next refresh, so the new value replaces it.
            'refresh_token' => $token['refresh_token'] ?? $refresh,
        ]);
        $account->token_expires_at   = isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null;
        $account->token_refreshed_at = now();
        $account->status             = SocialStatus::ACCOUNT_CONNECTED;
        $account->save();

        return true;
    }

    protected function profileFromToken(string $token): array {
        $me = $this->result($this->http->withToken($token)->get($this->apiBase() . '/users/me', [
            'user.fields' => 'profile_image_url,public_metrics,username,name',
        ]));

        $user = $me['data'] ?? [];

        return [
            'external_id' => $user['id'] ?? null,
            'name'        => $user['name'] ?? null,
            'username'    => isset($user['username']) ? '@' . $user['username'] : null,
            'avatar_url'  => $user['profile_image_url'] ?? null,
            'profile_url' => isset($user['username']) ? 'https://x.com/' . $user['username'] : null,
            'followers'   => (int) data_get($user, 'public_metrics.followers_count', 0),
            'following'   => (int) data_get($user, 'public_metrics.following_count', 0),
            'media_count' => (int) data_get($user, 'public_metrics.tweet_count', 0),
        ];
    }

    public function fetchProfile(SocialAccount $account): array {
        return $this->profileFromToken($this->token($account));
    }

    /* ---------------------------------------------------------------- Publish */

    public function publish(PublishContext $context): PublishResult {
        $token = $this->token($context->account);
        $limit = PlatformCapability::limit($this->key(), 'caption_max', 280);

        $mediaIds = $this->uploadAllMedia($token, $context);

        $parts = $this->buildThread($context, $limit);
        if (!$parts) {
            throw new PlatformException('There is nothing to post to X - the caption is empty.', 'empty_content');
        }

        $firstId = null;
        $lastId  = null;
        $allIds  = [];

        foreach ($parts as $index => $text) {
            $payload = ['text' => $text];

            // Media rides on the first post of a thread only.
            if ($index === 0 && $mediaIds) {
                $payload['media'] = ['media_ids' => $mediaIds];
            }
            if ($lastId) {
                $payload['reply'] = ['in_reply_to_tweet_id' => $lastId];
            }

            $response = $this->result(
                $this->http->withToken($token)->asJson()->post($this->apiBase() . '/tweets', $payload),
                'X rejected the post.'
            );

            $id = data_get($response, 'data.id');
            if (!$id) {
                throw new PlatformException('X accepted the request but returned no post id.', 'rejected');
            }

            $firstId = $firstId ?: $id;
            $lastId  = $id;
            $allIds[] = $id;
        }

        $handle = ltrim((string) $context->account->username, '@') ?: 'i';

        return PublishResult::make(
            (string) $firstId,
            "https://x.com/{$handle}/status/{$firstId}",
            ['thread_ids' => $allIds, 'parts' => count($allIds)]
        );
    }

    /**
     * Splits the copy into thread-sized parts.
     *
     * An explicitly authored thread (payload.thread) wins; otherwise long copy
     * is split on paragraph then sentence boundaries so parts read naturally,
     * and numbered (1/3) so readers know more is coming.
     */
    protected function buildThread(PublishContext $context, int $limit): array {
        $authored = (array) $context->value('thread', []);
        if ($authored) {
            return array_values(array_filter(array_map(fn ($p) => $this->clamp($p, $limit), $authored)));
        }

        $body = trim($context->caption());
        if ($cta = $context->value('cta')) {
            $body .= "\n\n" . $cta;
        }
        if ($link = $context->primaryLink()) {
            $body .= "\n" . $link;
        }
        if ($tags = (array) $context->value('hashtags', [])) {
            $body .= "\n" . implode(' ', $tags);
        }

        $body = trim($body);
        if ($body === '') {
            return [];
        }

        if (mb_strlen($body) <= $limit) {
            return [$body];
        }

        $maxParts = PlatformCapability::limit($this->key(), 'thread_max_parts', 25);
        // Numbering costs characters, so the split budget accounts for it.
        $chunks   = $this->splitForThread($body, $limit - 8, $maxParts);
        $total    = count($chunks);

        return array_map(
            fn ($chunk, $i) => $total > 1 ? $chunk . "\n\n" . ($i + 1) . '/' . $total : $chunk,
            $chunks,
            array_keys($chunks)
        );
    }

    /** Greedy split on paragraph → sentence → word boundaries. */
    protected function splitForThread(string $text, int $limit, int $maxParts): array {
        $units  = preg_split('/(?<=[.!?])\s+|\n{2,}/u', $text) ?: [$text];
        $parts  = [];
        $buffer = '';

        foreach ($units as $unit) {
            $unit = trim($unit);
            if ($unit === '') {
                continue;
            }

            if (mb_strlen($unit) > $limit) {
                // A single sentence longer than the limit still has to be cut,
                // but on a word boundary rather than mid-word.
                foreach (explode(' ', $unit) as $word) {
                    if (mb_strlen($buffer . ' ' . $word) > $limit) {
                        $parts[] = trim($buffer);
                        $buffer  = $word;
                    } else {
                        $buffer = trim($buffer . ' ' . $word);
                    }
                }
                continue;
            }

            if (mb_strlen($buffer . ' ' . $unit) > $limit) {
                $parts[] = trim($buffer);
                $buffer  = $unit;
            } else {
                $buffer = trim($buffer . ' ' . $unit);
            }
        }

        if ($buffer !== '') {
            $parts[] = trim($buffer);
        }

        $parts = array_values(array_filter($parts));

        return count($parts) > $maxParts ? array_slice($parts, 0, $maxParts) : $parts;
    }

    protected function uploadAllMedia(string $token, PublishContext $context): array {
        $ids   = [];
        $video = $context->video();

        if ($video) {
            return [$this->uploadVideo($token, $video)];
        }

        $max = PlatformCapability::limit($this->key(), 'media_max_count', 4);
        foreach ($context->images()->take($max) as $image) {
            $ids[] = $this->uploadImage($token, $image);
        }

        return array_values(array_filter($ids));
    }

    protected function uploadImage(string $token, SocialMedia $image): ?string {
        $path = $image->absolutePath();
        if (!$path || !is_readable($path)) {
            throw PlatformException::invalidMedia('The image file could not be read from storage.');
        }

        $response = $this->http->withToken($token)
            ->attach('media', fopen($path, 'r'), $image->filename)
            ->post(self::MEDIA_UPLOAD_URL, ['media_category' => 'tweet_image']);

        $body = $this->result($response, 'X rejected the image upload.');

        return (string) (data_get($body, 'data.id') ?? $body['media_id_string'] ?? $body['id'] ?? '') ?: null;
    }

    /**
     * Chunked video upload: INIT reserves an id, APPEND streams the bytes in
     * segments, FINALIZE closes it, then processing is polled until it is
     * usable. Attaching a still-processing media id produces a confusing
     * failure on the post itself, so the wait happens here.
     */
    protected function uploadVideo(string $token, SocialMedia $video): string {
        $path = $video->absolutePath();
        if (!$path || !is_readable($path)) {
            throw PlatformException::invalidMedia('The video file could not be read from storage.');
        }

        $size = filesize($path);

        $init = $this->result(
            $this->http->withToken($token)->asForm()->post(self::MEDIA_UPLOAD_URL, [
                'command'        => 'INIT',
                'total_bytes'    => $size,
                'media_type'     => $video->mime,
                'media_category' => 'tweet_video',
            ]),
            'X rejected the video upload session.'
        );

        $mediaId = (string) (data_get($init, 'data.id') ?? $init['media_id_string'] ?? '');
        if (!$mediaId) {
            throw new PlatformException('X did not return a media upload id.', 'rejected');
        }

        $handle  = fopen($path, 'rb');
        $segment = 0;

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, self::CHUNK_BYTES);
                if ($chunk === false || $chunk === '') {
                    break;
                }

                $response = $this->http->withToken($token)
                    ->attach('media', $chunk, 'chunk')
                    ->post(self::MEDIA_UPLOAD_URL, [
                        'command'       => 'APPEND',
                        'media_id'      => $mediaId,
                        'segment_index' => $segment,
                    ]);

                if (!$response->successful()) {
                    throw SocialHttpClient::classify($this->key(), $response, 'X rejected part of the video upload.');
                }

                $segment++;
            }
        } finally {
            fclose($handle);
        }

        $finalize = $this->result(
            $this->http->withToken($token)->asForm()->post(self::MEDIA_UPLOAD_URL, [
                'command'  => 'FINALIZE',
                'media_id' => $mediaId,
            ]),
            'X rejected the completed video upload.'
        );

        $this->awaitMediaProcessing($token, $mediaId, $finalize);

        return $mediaId;
    }

    protected function awaitMediaProcessing(string $token, string $mediaId, array $finalize): void {
        $state = data_get($finalize, 'data.processing_info.state') ?? data_get($finalize, 'processing_info.state');
        $wait  = (int) (data_get($finalize, 'data.processing_info.check_after_secs')
            ?? data_get($finalize, 'processing_info.check_after_secs') ?? 5);

        $attempts = 0;
        while ($state && !in_array($state, ['succeeded', 'failed'], true) && $attempts < 40) {
            sleep(max(1, min($wait, 15)));
            $attempts++;

            $status = $this->http->withToken($token)->get(self::MEDIA_UPLOAD_URL, [
                'command'  => 'STATUS',
                'media_id' => $mediaId,
            ]);

            if (!$status->successful()) {
                break;
            }

            $info  = $status->json();
            $state = data_get($info, 'data.processing_info.state') ?? data_get($info, 'processing_info.state');
            $wait  = (int) (data_get($info, 'data.processing_info.check_after_secs')
                ?? data_get($info, 'processing_info.check_after_secs') ?? 5);
        }

        if ($state === 'failed') {
            throw new PlatformException(
                'X could not process the video. Check that it meets the duration, size and codec requirements.',
                'media_processing_failed'
            );
        }
    }

    /* --------------------------------------------------------------- Metrics */

    public function fetchPostMetrics(SocialAccount $account, SocialPostPlatform $target): ?array {
        if (!$target->platform_post_id) {
            return null;
        }

        try {
            $body = $this->result($this->http->withToken($this->token($account))
                ->get($this->apiBase() . '/tweets/' . $target->platform_post_id, [
                    'tweet.fields' => 'public_metrics',
                ]));

            $m = data_get($body, 'data.public_metrics', []);

            return [
                'likes'    => (int) ($m['like_count'] ?? 0),
                'comments' => (int) ($m['reply_count'] ?? 0),
                'shares'   => (int) ($m['retweet_count'] ?? 0) + (int) ($m['quote_count'] ?? 0),
                'views'    => (int) ($m['impression_count'] ?? 0),
                'reach'    => (int) ($m['impression_count'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function fetchAccountMetrics(SocialAccount $account, string $date): ?array {
        try {
            $profile = $this->fetchProfile($account);
            return ['followers' => $profile['followers'] ?? null];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function fetchComments(SocialAccount $account, int $limit = 50): array {
        $handle = ltrim((string) $account->username, '@');
        if (!$handle) {
            return [];
        }

        try {
            $body = $this->result($this->http->withToken($this->token($account))
                ->get($this->apiBase() . '/tweets/search/recent', [
                    'query'        => '@' . $handle . ' -is:retweet',
                    'max_results'  => min(100, max(10, $limit)),
                    'tweet.fields' => 'created_at,author_id,conversation_id',
                    'expansions'   => 'author_id',
                    'user.fields'  => 'username,name,profile_image_url',
                ]));
        } catch (\Throwable $e) {
            // Mention search needs an elevated access tier; an empty inbox is
            // the honest result when the app's plan does not include it.
            return [];
        }

        $users = collect(data_get($body, 'includes.users', []))->keyBy('id');

        return collect(data_get($body, 'data', []))->map(function ($tweet) use ($users) {
            $author = $users->get($tweet['author_id'] ?? '');
            return [
                'external_id'      => $tweet['id'] ?? null,
                'platform_post_id' => $tweet['conversation_id'] ?? null,
                'kind'             => 'mention',
                'message'          => $tweet['text'] ?? null,
                'author_name'      => $author['name'] ?? null,
                'author_handle'    => isset($author['username']) ? '@' . $author['username'] : null,
                'author_avatar'    => $author['profile_image_url'] ?? null,
                'permalink'        => isset($author['username'], $tweet['id'])
                    ? 'https://x.com/' . $author['username'] . '/status/' . $tweet['id'] : null,
                'posted_at'        => isset($tweet['created_at']) ? date('Y-m-d H:i:s', strtotime($tweet['created_at'])) : null,
            ];
        })->all();
    }

    public function deletePost(SocialAccount $account, SocialPostPlatform $target): bool {
        if (!$target->platform_post_id) {
            return false;
        }

        $response = $this->http->withToken($this->token($account))
            ->delete($this->apiBase() . '/tweets/' . $target->platform_post_id);

        return $response->successful();
    }
}
