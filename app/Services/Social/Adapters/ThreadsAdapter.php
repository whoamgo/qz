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
 * Threads, via the official Threads API.
 *
 * The publish flow mirrors Instagram's - create a container, then publish it -
 * but Threads runs on its own host and its own token exchange, so it does not
 * inherit Facebook's. Like Instagram it ingests media from a public URL.
 */
class ThreadsAdapter extends GraphApiAdapter {

    public function key(): string {
        return SocialStatus::THREADS;
    }

    protected function apiBase(): string {
        return rtrim((string) $this->config('api_base', 'https://graph.threads.net/v1.0'), '/');
    }

    protected function threadsUserId(SocialAccount $account): string {
        $id = $account->credential('threads_user_id') ?: $account->external_id;
        if (!$id) {
            throw PlatformException::notConnected($this->key());
        }
        return (string) $id;
    }

    /** Threads uses space-free comma scopes and its own authorize host. */
    public function authorizationUrl(string $state): array {
        if (!$this->isConfigured()) {
            throw PlatformException::notConfigured($this->key());
        }

        $url = $this->config('authorize_url') . '?' . http_build_query([
            'client_id'     => $this->config('client_id'),
            'redirect_uri'  => $this->redirectUri(),
            'scope'         => implode(',', (array) $this->config('scopes', [])),
            'response_type' => 'code',
            'state'         => $state,
        ]);

        return ['url' => $url, 'session' => []];
    }

    public function exchangeCallback(Request $request, array $session): array {
        if ($error = $request->query('error_description') ?: $request->query('error')) {
            throw new PlatformException('Threads authorisation was declined: ' . SocialHttpClient::redactString((string) $error), 'oauth_declined');
        }

        $code = (string) $request->query('code');
        if (!$code) {
            throw new PlatformException('The Threads callback did not include a code.', 'oauth_invalid');
        }

        $short = $this->result(
            $this->http->request()->asForm()->post((string) $this->config('token_url'), [
                'client_id'     => $this->config('client_id'),
                'client_secret' => $this->config('client_secret'),
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->redirectUri(),
                'code'          => $code,
            ]),
            'Threads refused to issue an access token.'
        );

        $token  = $short['access_token'] ?? null;
        $userId = $short['user_id'] ?? null;

        if (!$token) {
            throw new PlatformException('Threads returned no access token.', 'oauth_invalid');
        }

        $long      = $this->exchangeLongLived($token);
        $token     = $long['access_token'] ?? $token;
        $expiresIn = (int) ($long['expires_in'] ?? 0);

        $profile = $this->profileFromToken($token);
        $profile['external_id'] = $profile['external_id'] ?: $userId;

        return [
            'credentials' => ['access_token' => $token, 'threads_user_id' => $profile['external_id']],
            'expires_at'  => $expiresIn ? now()->addSeconds($expiresIn)->toDateTimeString() : null,
            'scopes'      => implode(',', (array) $this->config('scopes', [])),
            'profile'     => $profile,
        ];
    }

    /** Threads swaps short for long-lived tokens with th_exchange_token. */
    protected function exchangeLongLived(string $shortToken): array {
        $response = $this->http->request()->get('https://graph.threads.net/access_token', [
            'grant_type'    => 'th_exchange_token',
            'client_secret' => $this->config('client_secret'),
            'access_token'  => $shortToken,
        ]);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    public function refreshToken(SocialAccount $account): bool {
        $token = $account->accessToken();
        if (!$token) {
            return false;
        }

        $response = $this->http->request()->get('https://graph.threads.net/refresh_access_token', [
            'grant_type'   => 'th_refresh_token',
            'access_token' => $token,
        ]);

        if (!$response->successful()) {
            return false;
        }

        $body = $response->json();
        $account->setCredentials(['access_token' => $body['access_token'] ?? $token]);
        $account->token_expires_at   = isset($body['expires_in']) ? now()->addSeconds((int) $body['expires_in']) : null;
        $account->token_refreshed_at = now();
        $account->save();

        return true;
    }

    protected function profileFromToken(string $token): array {
        $me = $this->result($this->http->withToken($token)->get($this->apiBase() . '/me', [
            'fields' => 'id,username,name,threads_profile_picture_url,threads_biography',
        ]));

        return [
            'external_id' => $me['id'] ?? null,
            'name'        => $me['name'] ?? $me['username'] ?? 'Threads',
            'username'    => isset($me['username']) ? '@' . $me['username'] : null,
            'avatar_url'  => $me['threads_profile_picture_url'] ?? null,
            'profile_url' => isset($me['username']) ? 'https://www.threads.net/@' . $me['username'] : null,
        ];
    }

    public function fetchProfile(SocialAccount $account): array {
        return $this->profileFromToken($this->token($account));
    }

    /* ---------------------------------------------------------------- Publish */

    public function publish(PublishContext $context): PublishResult {
        $token  = $this->token($context->account);
        $userId = $this->threadsUserId($context->account);

        $text = $this->composeBody(
            trim($context->caption() . ($context->value('cta') ? "\n\n" . $context->value('cta') : '')),
            (array) $context->value('hashtags', []),
            PlatformCapability::limit($this->key(), 'caption_max', 500)
        );

        if ($link = $context->primaryLink()) {
            if (!str_contains($text, $link)) {
                $text = $this->clamp($text, PlatformCapability::limit($this->key(), 'caption_max', 500) - mb_strlen($link) - 2)
                    . "\n" . $link;
            }
        }

        $params = ['text' => $text];
        $video  = $context->video();
        $images = $context->images();

        if ($video) {
            $params['media_type'] = 'VIDEO';
            $params['video_url']  = $this->publicMediaUrl($video);
        } elseif ($images->isNotEmpty()) {
            $params['media_type'] = 'IMAGE';
            $params['image_url']  = $this->publicMediaUrl($images->first());
        } else {
            $params['media_type'] = 'TEXT';
        }

        $container = $this->graphPost($token, "$userId/threads", $params);
        $creationId = $container['id'] ?? null;
        if (!$creationId) {
            throw new PlatformException('Threads did not return a media container id.', 'rejected');
        }

        // Video containers are processed asynchronously; publishing too early
        // fails, so give the platform a moment before the publish call.
        if ($params['media_type'] === 'VIDEO') {
            $this->awaitContainer($token, (string) $creationId);
        }

        $published = $this->graphPost($token, "$userId/threads_publish", ['creation_id' => $creationId]);
        $postId    = (string) ($published['id'] ?? '');

        if (!$postId) {
            throw new PlatformException('Threads accepted the container but returned no post id.', 'rejected');
        }

        $permalink = null;
        try {
            $permalink = $this->graphGet($token, $postId, ['fields' => 'permalink'])['permalink'] ?? null;
        } catch (\Throwable $e) {
            // Permalink is cosmetic.
        }

        return PublishResult::make($postId, $permalink, ['container_id' => $creationId]);
    }

    protected function awaitContainer(string $token, string $containerId): void {
        for ($i = 0; $i < 20; $i++) {
            $status = $this->graphGet($token, $containerId, ['fields' => 'status,error_message']);
            $state  = $status['status'] ?? 'IN_PROGRESS';

            if ($state === 'FINISHED') {
                return;
            }
            if (in_array($state, ['ERROR', 'EXPIRED'], true)) {
                throw new PlatformException(
                    'Threads could not process the video: ' . ($status['error_message'] ?? $state),
                    'media_processing_failed'
                );
            }

            sleep(4);
        }

        throw new PlatformException(
            'Threads is still processing the video. The publish will be retried automatically.',
            'media_processing_timeout',
            true
        );
    }

    public function fetchPostMetrics(SocialAccount $account, SocialPostPlatform $target): ?array {
        if (!$target->platform_post_id) {
            return null;
        }

        try {
            $body = $this->graphGet($this->token($account), $target->platform_post_id . '/insights', [
                'metric' => 'views,likes,replies,reposts,quotes',
            ]);

            $metrics = [];
            foreach ($body['data'] ?? [] as $row) {
                $value = $row['value'] ?? data_get($row, 'values.0.value', 0);
                match ($row['name'] ?? '') {
                    'views'   => $metrics['views']    = (int) $value,
                    'likes'   => $metrics['likes']    = (int) $value,
                    'replies' => $metrics['comments'] = (int) $value,
                    'reposts', 'quotes' => $metrics['shares'] = ($metrics['shares'] ?? 0) + (int) $value,
                    default   => null,
                };
            }

            return $metrics ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
