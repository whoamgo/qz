<?php

namespace App\Services\Social\Adapters;

use App\Constants\SocialStatus;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialPostPlatform;
use App\Services\Social\PlatformCapability;
use App\Services\Social\Support\PlatformException;
use App\Services\Social\Support\PublishContext;
use App\Services\Social\Support\PublishResult;
use App\Services\Social\Support\SocialHttpClient;
use Illuminate\Http\Request;

/**
 * YouTube Data API v3.
 *
 * Uploads use Google's resumable protocol: a session is opened with the
 * metadata, the bytes are streamed to the returned session URL, and only then
 * does the video exist. That ordering is what lets a failed byte transfer be
 * retried without creating a second video.
 *
 * "Shorts" is not an API flag - YouTube classifies a video as a Short from its
 * aspect ratio and duration. The adapter validates those locally and adds the
 * conventional #Shorts marker rather than pretending to set a switch.
 */
class YouTubeAdapter extends AbstractPlatformAdapter {

    public function key(): string {
        return SocialStatus::YOUTUBE;
    }

    protected function apiBase(): string {
        return rtrim((string) $this->config('api_base'), '/');
    }

    protected function uploadBase(): string {
        return rtrim((string) $this->config('upload_base'), '/');
    }

    /* ------------------------------------------------------------------ OAuth */

    public function authorizationUrl(string $state): array {
        if (!$this->isConfigured()) {
            throw PlatformException::notConfigured($this->key());
        }

        $url = $this->config('authorize_url') . '?' . http_build_query([
            'client_id'     => $this->config('client_id'),
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => implode(' ', (array) $this->config('scopes', [])),
            'state'         => $state,
            // Offline access plus a forced consent screen is the only reliable
            // way to be issued a refresh token; without one, scheduled uploads
            // would stop working an hour after connecting.
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'include_granted_scopes' => 'true',
        ]);

        return ['url' => $url, 'session' => []];
    }

    public function exchangeCallback(Request $request, array $session): array {
        if ($error = $request->query('error')) {
            throw new PlatformException('YouTube authorisation was declined: ' . SocialHttpClient::redactString((string) $error), 'oauth_declined');
        }

        $code = (string) $request->query('code');
        if (!$code) {
            throw new PlatformException('The YouTube callback did not include a code.', 'oauth_invalid');
        }

        $token = $this->result(
            $this->http->request()->asForm()->post((string) $this->config('token_url'), [
                'code'          => $code,
                'client_id'     => $this->config('client_id'),
                'client_secret' => $this->config('client_secret'),
                'redirect_uri'  => $this->redirectUri(),
                'grant_type'    => 'authorization_code',
            ]),
            'Google refused to issue an access token.'
        );

        $accessToken = $token['access_token'] ?? null;
        if (!$accessToken) {
            throw new PlatformException('Google returned no access token.', 'oauth_invalid');
        }

        if (empty($token['refresh_token'])) {
            throw new PlatformException(
                'Google did not return a refresh token, which means scheduled uploads would stop working within the hour. '
                . 'Remove this app from your Google account permissions and connect again so the consent screen is shown.',
                'no_refresh_token'
            );
        }

        return [
            'credentials' => [
                'access_token'  => $accessToken,
                'refresh_token' => $token['refresh_token'],
            ],
            'expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in'])->toDateTimeString() : null,
            'scopes'     => $token['scope'] ?? implode(' ', (array) $this->config('scopes', [])),
            'profile'    => $this->profileFromToken($accessToken),
        ];
    }

    public function refreshToken(SocialAccount $account): bool {
        $refresh = $account->credential('refresh_token');
        if (!$refresh || !$this->isConfigured()) {
            return false;
        }

        $response = $this->http->request()->asForm()->post((string) $this->config('token_url'), [
            'client_id'     => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'refresh_token' => $refresh,
            'grant_type'    => 'refresh_token',
        ]);

        if (!$response->successful()) {
            return false;
        }

        $token = $response->json();

        // Google does not reissue the refresh token, so the stored one is kept.
        $account->setCredentials(['access_token' => $token['access_token'] ?? $account->accessToken()]);
        $account->token_expires_at   = isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null;
        $account->token_refreshed_at = now();
        $account->status             = SocialStatus::ACCOUNT_CONNECTED;
        $account->save();

        return true;
    }

    protected function profileFromToken(string $token): array {
        $body = $this->result(
            $this->http->withToken($token)->get($this->apiBase() . '/channels', [
                'part' => 'snippet,statistics,contentDetails',
                'mine' => 'true',
            ]),
            'YouTube did not return a channel for this Google account.'
        );

        $channel = data_get($body, 'items.0');
        if (!$channel) {
            throw new PlatformException(
                'This Google account has no YouTube channel. Create a channel first, then connect again.',
                'no_channel'
            );
        }

        return [
            'external_id' => $channel['id'] ?? null,
            'name'        => data_get($channel, 'snippet.title'),
            'username'    => data_get($channel, 'snippet.customUrl'),
            'avatar_url'  => data_get($channel, 'snippet.thumbnails.default.url'),
            'profile_url' => 'https://www.youtube.com/channel/' . ($channel['id'] ?? ''),
            'followers'   => (int) data_get($channel, 'statistics.subscriberCount', 0),
            'media_count' => (int) data_get($channel, 'statistics.videoCount', 0),
            'meta'        => ['uploads_playlist' => data_get($channel, 'contentDetails.relatedPlaylists.uploads')],
        ];
    }

    public function fetchProfile(SocialAccount $account): array {
        return $this->profileFromToken($this->token($account));
    }

    /* ---------------------------------------------------------------- Publish */

    public function publish(PublishContext $context): PublishResult {
        $token = $this->token($context->account);
        $video = $context->video();

        if (!$video) {
            throw PlatformException::unsupported($this->key(), 'posts without a video - YouTube only accepts video uploads');
        }

        $path = $video->absolutePath();
        if (!$path || !is_readable($path)) {
            throw PlatformException::invalidMedia('The video file could not be read from storage.');
        }

        $isShort     = (bool) $context->value('as_short');
        $title       = $this->clamp($context->title() ?: 'Untitled', PlatformCapability::limit($this->key(), 'title_max', 100));
        $description = $this->buildDescription($context, $isShort);

        $snippet = [
            'title'       => $title,
            'description' => $description,
            'tags'        => $this->buildTags($context),
        ];

        if ($categoryId = $context->value('category_id')) {
            $snippet['categoryId'] = (string) $categoryId;
        }
        if ($language = $context->value('default_language')) {
            $snippet['defaultLanguage'] = $language;
        }

        $status = [
            'privacyStatus'           => $context->value('visibility', 'public'),
            'selfDeclaredMadeForKids' => (bool) $context->value('made_for_kids', false),
            'embeddable'              => true,
        ];

        // A future publishAt only takes effect on a private video; setting one
        // on a public video is silently ignored by YouTube.
        if ($publishAt = $context->value('publish_at')) {
            $status['privacyStatus'] = 'private';
            $status['publishAt']     = \Carbon\Carbon::parse($publishAt)->toIso8601ZuluString();
        }

        $sessionUrl = $this->openUploadSession($token, ['snippet' => $snippet, 'status' => $status], $path, $video->mime);
        $uploaded   = $this->streamUpload($token, $sessionUrl, $path, $video->mime);

        $videoId = $uploaded['id'] ?? null;
        if (!$videoId) {
            throw new PlatformException('YouTube accepted the upload but returned no video id.', 'rejected');
        }

        // Post-upload extras are best-effort: the video is already live, and
        // failing the whole publish over a thumbnail would be wrong.
        if ($thumb = $context->thumbnail()) {
            $this->trySetThumbnail($token, $videoId, $thumb);
        }
        if ($playlistId = $context->value('playlist_id')) {
            $this->tryAddToPlaylist($token, $videoId, (string) $playlistId);
        }
        if ($firstComment = $context->value('first_comment')) {
            $this->tryComment($token, $videoId, $firstComment);
        }

        return PublishResult::make(
            $videoId,
            ($isShort ? 'https://www.youtube.com/shorts/' : 'https://www.youtube.com/watch?v=') . $videoId,
            ['short' => $isShort]
        );
    }

    protected function buildDescription(PublishContext $context, bool $isShort): string {
        $parts = array_filter([
            $context->description() ?: $context->caption(),
            $context->value('cta'),
            $context->primaryLink(),
        ]);

        $description = implode("\n\n", $parts);

        if ($tags = (array) $context->value('hashtags', [])) {
            $description .= "\n\n" . implode(' ', array_slice($tags, 0, PlatformCapability::limit($this->key(), 'hashtag_max', 15)));
        }

        // YouTube reads #Shorts as a strong hint alongside the aspect ratio.
        if ($isShort && !str_contains(strtolower($description), '#shorts')) {
            $description .= ' #Shorts';
        }

        return $this->clamp($description, PlatformCapability::limit($this->key(), 'description_max', 5000));
    }

    /** Tags are capped by total character count, not by count of tags. */
    protected function buildTags(PublishContext $context): array {
        $tags  = array_map(fn ($t) => ltrim((string) $t, '#'), (array) $context->value('tags', $context->value('hashtags', [])));
        $out   = [];
        $chars = 0;
        $max   = PlatformCapability::limit($this->key(), 'tags_chars_max', 500);

        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }
            if ($chars + mb_strlen($tag) + 1 > $max) {
                break;
            }
            $out[]  = $tag;
            $chars += mb_strlen($tag) + 1;
        }

        return $out;
    }

    /** Opens a resumable session and returns the upload URL from the headers. */
    protected function openUploadSession(string $token, array $metadata, string $path, string $mime): string {
        $response = $this->http->withToken($token)
            ->withHeaders([
                'X-Upload-Content-Length' => (string) filesize($path),
                'X-Upload-Content-Type'   => $mime,
            ])
            ->asJson()
            ->post($this->uploadBase() . '/videos?' . http_build_query([
                'uploadType' => 'resumable',
                'part'       => 'snippet,status',
            ]), $metadata);

        if (!$response->successful()) {
            throw SocialHttpClient::classify($this->key(), $response, 'YouTube refused to start the upload.');
        }

        $location = $response->header('Location');
        if (!$location) {
            throw new PlatformException('YouTube did not return an upload session URL.', 'rejected');
        }

        return $location;
    }

    protected function streamUpload(string $token, string $sessionUrl, string $path, string $mime): array {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw PlatformException::invalidMedia('The video file could not be opened for upload.');
        }

        try {
            $response = $this->http->withToken($token)
                ->withHeaders(['Content-Type' => $mime])
                ->withBody($handle, $mime)
                ->put($sessionUrl);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (!$response->successful()) {
            throw SocialHttpClient::classify($this->key(), $response, 'The video upload to YouTube failed.');
        }

        return $response->json() ?? [];
    }

    protected function trySetThumbnail(string $token, string $videoId, $thumbnail): void {
        try {
            $path = $thumbnail->absolutePath();
            if (!$path || !is_readable($path)) {
                return;
            }

            $this->http->withToken($token)
                ->withBody(file_get_contents($path), $thumbnail->mime)
                ->post($this->uploadBase() . '/thumbnails/set?videoId=' . urlencode($videoId));
        } catch (\Throwable $e) {
            SocialHttpClient::log($this->key(), 'thumbnail upload failed', ['video' => $videoId]);
        }
    }

    protected function tryAddToPlaylist(string $token, string $videoId, string $playlistId): void {
        try {
            $this->http->withToken($token)->asJson()->post($this->apiBase() . '/playlistItems?part=snippet', [
                'snippet' => [
                    'playlistId' => $playlistId,
                    'resourceId' => ['kind' => 'youtube#video', 'videoId' => $videoId],
                ],
            ]);
        } catch (\Throwable $e) {
            SocialHttpClient::log($this->key(), 'playlist insert failed', ['video' => $videoId]);
        }
    }

    protected function tryComment(string $token, string $videoId, string $text): void {
        try {
            $this->http->withToken($token)->asJson()->post($this->apiBase() . '/commentThreads?part=snippet', [
                'snippet' => [
                    'videoId'         => $videoId,
                    'topLevelComment' => ['snippet' => ['textOriginal' => $text]],
                ],
            ]);
        } catch (\Throwable $e) {
            SocialHttpClient::log($this->key(), 'first comment failed', ['video' => $videoId]);
        }
    }

    /** Playlists the connected channel owns, for the publisher's dropdown. */
    public function fetchPlaylists(SocialAccount $account): array {
        try {
            $body = $this->result($this->http->withToken($this->token($account))->get($this->apiBase() . '/playlists', [
                'part'       => 'snippet',
                'mine'       => 'true',
                'maxResults' => 50,
            ]));

            return collect($body['items'] ?? [])->map(fn ($p) => [
                'id'    => $p['id'] ?? null,
                'title' => data_get($p, 'snippet.title'),
            ])->filter(fn ($p) => $p['id'])->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /* --------------------------------------------------------------- Metrics */

    public function fetchPostMetrics(SocialAccount $account, SocialPostPlatform $target): ?array {
        if (!$target->platform_post_id) {
            return null;
        }

        try {
            $body = $this->result($this->http->withToken($this->token($account))->get($this->apiBase() . '/videos', [
                'part' => 'statistics',
                'id'   => $target->platform_post_id,
            ]));

            $stats = data_get($body, 'items.0.statistics');
            if (!$stats) {
                return null;
            }

            return [
                'views'    => (int) ($stats['viewCount'] ?? 0),
                'likes'    => (int) ($stats['likeCount'] ?? 0),
                'comments' => (int) ($stats['commentCount'] ?? 0),
                'saves'    => (int) ($stats['favoriteCount'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function fetchAccountMetrics(SocialAccount $account, string $date): ?array {
        try {
            $profile = $this->fetchProfile($account);
            return [
                'followers' => $profile['followers'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function fetchComments(SocialAccount $account, int $limit = 50): array {
        try {
            $body = $this->result($this->http->withToken($this->token($account))->get($this->apiBase() . '/commentThreads', [
                'part'                        => 'snippet',
                'allThreadsRelatedToChannelId'=> $account->external_id,
                'maxResults'                  => min(100, $limit),
                'order'                       => 'time',
            ]));

            return collect($body['items'] ?? [])->map(function ($thread) {
                $c = data_get($thread, 'snippet.topLevelComment.snippet', []);
                return [
                    'external_id'      => data_get($thread, 'snippet.topLevelComment.id'),
                    'platform_post_id' => $c['videoId'] ?? null,
                    'kind'             => 'comment',
                    'message'          => $c['textOriginal'] ?? $c['textDisplay'] ?? null,
                    'author_name'      => $c['authorDisplayName'] ?? null,
                    'author_handle'    => $c['authorChannelUrl'] ?? null,
                    'author_avatar'    => $c['authorProfileImageUrl'] ?? null,
                    'permalink'        => isset($c['videoId']) ? 'https://www.youtube.com/watch?v=' . $c['videoId'] : null,
                    'posted_at'        => isset($c['publishedAt']) ? date('Y-m-d H:i:s', strtotime($c['publishedAt'])) : null,
                ];
            })->filter(fn ($c) => $c['external_id'])->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function deletePost(SocialAccount $account, SocialPostPlatform $target): bool {
        if (!$target->platform_post_id) {
            return false;
        }

        $response = $this->http->withToken($this->token($account))
            ->delete($this->apiBase() . '/videos', ['id' => $target->platform_post_id]);

        return $response->successful();
    }
}
