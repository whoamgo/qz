<?php

namespace App\Services\Social\Adapters;

use App\Constants\SocialStatus;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialMedia;
use App\Models\Social\SocialPostPlatform;
use App\Services\Social\PlatformCapability;
use App\Services\Social\Support\ConnectionResult;
use App\Services\Social\Support\PlatformException;
use App\Services\Social\Support\PublishContext;
use App\Services\Social\Support\PublishResult;
use App\Services\Social\Support\SocialHttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;

/**
 * LinkedIn, via the versioned REST API (/rest) with OpenID Connect for identity.
 *
 * Posts can be authored either as the connecting member or as a company page;
 * the author URN chosen at connect time decides which, and it is stored with the
 * account so publishing never has to guess.
 */
class LinkedInAdapter extends AbstractPlatformAdapter {

    public function key(): string {
        return SocialStatus::LINKEDIN;
    }

    protected function apiBase(): string {
        return rtrim((string) $this->config('api_base', 'https://api.linkedin.com/rest'), '/');
    }

    /** LinkedIn requires an explicit API version header on every /rest call. */
    protected function client(string $token): PendingRequest {
        return $this->http->withToken($token)->withHeaders([
            'LinkedIn-Version'          => (string) $this->config('api_version', '202409'),
            'X-Restli-Protocol-Version' => '2.0.0',
        ]);
    }

    protected function authorUrn(SocialAccount $account): string {
        $urn = $account->credential('author_urn');
        if (!$urn) {
            throw new PlatformException(
                'This LinkedIn account has no author selected. Reconnect it and choose whether to post as yourself or as a company page.',
                'not_configured'
            );
        }
        return (string) $urn;
    }

    /* ------------------------------------------------------------------ OAuth */

    public function authorizationUrl(string $state): array {
        if (!$this->isConfigured()) {
            throw PlatformException::notConfigured($this->key());
        }

        $url = $this->config('authorize_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->config('client_id'),
            'redirect_uri'  => $this->redirectUri(),
            'state'         => $state,
            'scope'         => implode(' ', (array) $this->config('scopes', [])),
        ]);

        return ['url' => $url, 'session' => []];
    }

    public function exchangeCallback(Request $request, array $session): array {
        if ($error = $request->query('error_description') ?: $request->query('error')) {
            throw new PlatformException('LinkedIn authorisation was declined: ' . SocialHttpClient::redactString((string) $error), 'oauth_declined');
        }

        $code = (string) $request->query('code');
        if (!$code) {
            throw new PlatformException('The LinkedIn callback did not include a code.', 'oauth_invalid');
        }

        $token = $this->result(
            $this->http->request()->asForm()->post((string) $this->config('token_url'), [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->redirectUri(),
                'client_id'     => $this->config('client_id'),
                'client_secret' => $this->config('client_secret'),
            ]),
            'LinkedIn refused to issue an access token.'
        );

        $accessToken = $token['access_token'] ?? null;
        if (!$accessToken) {
            throw new PlatformException('LinkedIn returned no access token.', 'oauth_invalid');
        }

        return [
            'credentials' => [
                'access_token'  => $accessToken,
                'refresh_token' => $token['refresh_token'] ?? null,
            ],
            'expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in'])->toDateTimeString() : null,
            'scopes'     => $token['scope'] ?? implode(' ', (array) $this->config('scopes', [])),
            'profile'    => $this->profileFromToken($accessToken),
        ];
    }

    /**
     * Refresh tokens are only issued to apps approved for them; when absent the
     * account simply has to be reconnected before the 60-day token lapses.
     */
    public function refreshToken(SocialAccount $account): bool {
        $refresh = $account->credential('refresh_token');
        if (!$refresh || !$this->isConfigured()) {
            return false;
        }

        $response = $this->http->request()->asForm()->post((string) $this->config('token_url'), [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh,
            'client_id'     => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
        ]);

        if (!$response->successful()) {
            return false;
        }

        $token = $response->json();
        $account->setCredentials([
            'access_token'  => $token['access_token'] ?? $account->accessToken(),
            'refresh_token' => $token['refresh_token'] ?? $refresh,
        ]);
        $account->token_expires_at   = isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null;
        $account->token_refreshed_at = now();
        $account->save();

        return true;
    }

    protected function profileFromToken(string $token): array {
        $me = $this->result(
            $this->http->withToken($token)->get('https://api.linkedin.com/v2/userinfo'),
            'LinkedIn did not return the connected profile.'
        );

        $personUrn = isset($me['sub']) ? 'urn:li:person:' . $me['sub'] : null;

        $options = array_values(array_filter([
            $personUrn ? [
                'id'          => $personUrn,
                'name'        => trim(($me['name'] ?? '') ?: 'My LinkedIn profile'),
                'username'    => $me['email'] ?? null,
                'avatar'      => $me['picture'] ?? null,
                'can_publish' => true,
                'kind'        => 'person',
            ] : null,
        ]));

        // Company pages are optional and depend on organisation scopes being
        // approved for the app, so a failure here is not fatal.
        foreach ($this->fetchOrganizations($token) as $org) {
            $options[] = $org;
        }

        return [
            'external_id' => $me['sub'] ?? null,
            'name'        => $me['name'] ?? 'LinkedIn',
            'avatar_url'  => $me['picture'] ?? null,
            'pages'       => $options,
        ];
    }

    /** Company pages the connecting member administers. */
    protected function fetchOrganizations(string $token): array {
        try {
            $acls = $this->client($token)->get($this->apiBase() . '/organizationAcls', [
                'q'     => 'roleAssignee',
                'role'  => 'ADMINISTRATOR',
                'state' => 'APPROVED',
            ]);

            if (!$acls->successful()) {
                return [];
            }

            $out = [];
            foreach ($acls->json('elements') ?? [] as $element) {
                $urn = $element['organization'] ?? null;
                if (!$urn) {
                    continue;
                }

                $id      = last(explode(':', $urn));
                $details = $this->client($token)->get($this->apiBase() . '/organizations/' . $id);
                $name    = $details->successful() ? ($details->json('localizedName') ?? $urn) : $urn;

                $out[] = [
                    'id'          => $urn,
                    'name'        => $name,
                    'can_publish' => true,
                    'kind'        => 'organization',
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function fetchProfile(SocialAccount $account): array {
        $token   = $this->token($account);
        $profile = $this->profileFromToken($token);

        // Follower counts are only exposed for organisations.
        $urn = $account->credential('author_urn');
        if ($urn && str_contains($urn, ':organization:')) {
            try {
                $id    = last(explode(':', $urn));
                $stats = $this->client($token)->get($this->apiBase() . '/networkSizes/' . $urn, [
                    'edgeType' => 'COMPANY_FOLLOWED_BY_MEMBER',
                ]);
                if ($stats->successful()) {
                    $profile['followers'] = (int) ($stats->json('firstDegreeSize') ?? 0);
                }
            } catch (\Throwable $e) {
                // Optional enrichment only.
            }
        }

        return $profile;
    }

    public function testConnection(SocialAccount $account): ConnectionResult {
        try {
            $profile = $this->fetchProfile($account);
            $urn     = $this->authorUrn($account);

            return ConnectionResult::ok(
                'Connected. Posts will be published as ' . (str_contains($urn, ':organization:') ? 'a company page' : 'the connected member') . '.',
                $profile
            );
        } catch (PlatformException $e) {
            return ConnectionResult::fail($e->getMessage());
        }
    }

    /* ---------------------------------------------------------------- Publish */

    public function publish(PublishContext $context): PublishResult {
        $token  = $this->token($context->account);
        $author = $this->authorUrn($context->account);

        $commentary = $this->composeBody(
            trim($context->caption() . ($context->value('cta') ? "\n\n" . $context->value('cta') : '')),
            (array) $context->value('hashtags', []),
            PlatformCapability::limit($this->key(), 'caption_max', 3000)
        );

        if ($link = $context->primaryLink()) {
            if (!str_contains($commentary, $link)) {
                $commentary = trim($commentary . "\n\n" . $link);
            }
        }

        $payload = [
            'author'                    => $author,
            'commentary'                => $commentary,
            'visibility'                => 'PUBLIC',
            'distribution'              => [
                'feedDistribution'               => 'MAIN_FEED',
                'targetEntities'                 => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState'            => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        if ($video = $context->video()) {
            $payload['content'] = ['media' => [
                'id'    => $this->uploadVideo($token, $author, $video),
                'title' => $this->clamp($context->title() ?: 'Video', 200),
            ]];
        } elseif ($context->images()->isNotEmpty()) {
            $images = $context->images();
            if ($images->count() === 1) {
                $payload['content'] = ['media' => [
                    'id'    => $this->uploadImage($token, $author, $images->first()),
                    'title' => $this->clamp($context->title() ?: 'Image', 200),
                ]];
            } else {
                $payload['content'] = ['multiImage' => [
                    'images' => $images->take(PlatformCapability::limit($this->key(), 'media_max_count', 9))
                        ->map(fn ($img) => ['id' => $this->uploadImage($token, $author, $img)])
                        ->values()->all(),
                ]];
            }
        }

        $response = $this->client($token)->asJson()->post($this->apiBase() . '/posts', $payload);

        if (!$response->successful()) {
            throw SocialHttpClient::classify($this->key(), $response, 'LinkedIn rejected the post.');
        }

        // The new post's URN comes back in a header, not the (empty) body.
        $postUrn = $response->header('x-restli-id') ?: ($response->json('id') ?? '');
        if (!$postUrn) {
            throw new PlatformException('LinkedIn accepted the post but returned no identifier.', 'rejected');
        }

        return PublishResult::make(
            $postUrn,
            'https://www.linkedin.com/feed/update/' . $postUrn
        );
    }

    /** Two-step image upload: initialise, then PUT the bytes. */
    protected function uploadImage(string $token, string $owner, SocialMedia $image): string {
        $init = $this->result(
            $this->client($token)->asJson()->post($this->apiBase() . '/images?action=initializeUpload', [
                'initializeUploadRequest' => ['owner' => $owner],
            ]),
            'LinkedIn refused to start the image upload.'
        );

        $uploadUrl = data_get($init, 'value.uploadUrl');
        $imageUrn  = data_get($init, 'value.image');

        if (!$uploadUrl || !$imageUrn) {
            throw new PlatformException('LinkedIn did not return an image upload target.', 'rejected');
        }

        $path = $image->absolutePath();
        if (!$path || !is_readable($path)) {
            throw PlatformException::invalidMedia('The image file could not be read from storage.');
        }

        $upload = $this->http->withToken($token)
            ->withBody(file_get_contents($path), $image->mime)
            ->put($uploadUrl);

        if (!$upload->successful()) {
            throw SocialHttpClient::classify($this->key(), $upload, 'LinkedIn rejected the image upload.');
        }

        return $imageUrn;
    }

    /**
     * Video upload: LinkedIn returns one or more upload instructions with byte
     * ranges; each part is PUT separately and its ETag collected for finalize.
     */
    protected function uploadVideo(string $token, string $owner, SocialMedia $video): string {
        $path = $video->absolutePath();
        if (!$path || !is_readable($path)) {
            throw PlatformException::invalidMedia('The video file could not be read from storage.');
        }

        $size = filesize($path);

        $init = $this->result(
            $this->client($token)->asJson()->post($this->apiBase() . '/videos?action=initializeUpload', [
                'initializeUploadRequest' => [
                    'owner'         => $owner,
                    'fileSizeBytes' => $size,
                    'uploadCaptions'=> false,
                    'uploadThumbnail'=> false,
                ],
            ]),
            'LinkedIn refused to start the video upload.'
        );

        $videoUrn     = data_get($init, 'value.video');
        $instructions = data_get($init, 'value.uploadInstructions', []);
        $token2       = data_get($init, 'value.uploadToken', '');

        if (!$videoUrn || !$instructions) {
            throw new PlatformException('LinkedIn did not return a video upload target.', 'rejected');
        }

        $etags  = [];
        $handle = fopen($path, 'rb');

        try {
            foreach ($instructions as $instruction) {
                $first = (int) ($instruction['firstByte'] ?? 0);
                $last  = (int) ($instruction['lastByte'] ?? ($size - 1));
                $len   = $last - $first + 1;

                fseek($handle, $first);
                $chunk = fread($handle, $len);

                $put = $this->http->withToken($token)
                    ->withBody($chunk, 'application/octet-stream')
                    ->put($instruction['uploadUrl']);

                if (!$put->successful()) {
                    throw SocialHttpClient::classify($this->key(), $put, 'LinkedIn rejected part of the video upload.');
                }

                $etags[] = $put->header('etag') ?: $put->header('ETag');
            }
        } finally {
            fclose($handle);
        }

        $finalize = $this->client($token)->asJson()->post($this->apiBase() . '/videos?action=finalizeUpload', [
            'finalizeUploadRequest' => [
                'video'          => $videoUrn,
                'uploadToken'    => $token2,
                'uploadedPartIds'=> array_values(array_filter($etags)),
            ],
        ]);

        if (!$finalize->successful()) {
            throw SocialHttpClient::classify($this->key(), $finalize, 'LinkedIn rejected the completed video upload.');
        }

        return $videoUrn;
    }

    /* --------------------------------------------------------------- Metrics */

    public function fetchPostMetrics(SocialAccount $account, SocialPostPlatform $target): ?array {
        if (!$target->platform_post_id) {
            return null;
        }

        try {
            $urn      = rawurlencode($target->platform_post_id);
            $response = $this->client($this->token($account))->get($this->apiBase() . '/socialActions/' . $urn);

            if (!$response->successful()) {
                return null;
            }

            $body = $response->json();

            return [
                'likes'    => (int) data_get($body, 'likesSummary.totalLikes', 0),
                'comments' => (int) data_get($body, 'commentsSummary.totalFirstLevelComments', 0),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function fetchAccountMetrics(SocialAccount $account, string $date): ?array {
        try {
            $profile = $this->fetchProfile($account);
            return isset($profile['followers']) ? ['followers' => $profile['followers']] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function deletePost(SocialAccount $account, SocialPostPlatform $target): bool {
        if (!$target->platform_post_id) {
            return false;
        }

        $response = $this->client($this->token($account))
            ->delete($this->apiBase() . '/posts/' . rawurlencode($target->platform_post_id));

        return $response->successful();
    }
}
