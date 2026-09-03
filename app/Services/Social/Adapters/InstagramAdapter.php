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

/**
 * Instagram publishing through the official Content Publishing API.
 *
 * Instagram does not accept uploaded bytes: it fetches the media from a public
 * HTTPS URL we supply, then publishes the resulting container. That makes a
 * publicly reachable APP_URL a hard requirement - publicMediaUrl() fails early
 * with a clear message rather than letting the platform time out on a private
 * address.
 *
 * Requires an Instagram *Business or Creator* account linked to a Facebook Page.
 * Personal accounts cannot publish through any official API.
 */
class InstagramAdapter extends GraphApiAdapter {

    /** Video containers are processed asynchronously; these bound the wait. */
    const STATUS_POLL_ATTEMPTS = 30;
    const STATUS_POLL_SECONDS  = 4;

    public function key(): string {
        return SocialStatus::INSTAGRAM;
    }

    protected function igUserId(SocialAccount $account): string {
        $id = $account->credential('ig_user_id');
        if (!$id) {
            throw new PlatformException(
                'This Instagram account is not linked to an Instagram Business account. Reconnect it and select a Page that has an Instagram Business account attached.',
                'not_configured'
            );
        }
        return (string) $id;
    }

    protected function igToken(SocialAccount $account): string {
        $token = $account->credential('page_access_token') ?: $account->accessToken();
        if (!$token) {
            throw PlatformException::notConnected($this->key());
        }
        if ($account->tokenExpired()) {
            throw PlatformException::tokenExpired($this->key());
        }
        return (string) $token;
    }

    /* ------------------------------------------------------------------ OAuth */

    protected function profileFromToken(string $token): array {
        $pages = $this->graphGet($token, 'me/accounts', [
            'fields' => 'id,name,access_token,instagram_business_account{id,username,name,profile_picture_url,followers_count,media_count}',
            'limit'  => 100,
        ]);

        // Only Pages with a linked Instagram Business account can publish, so
        // the rest are filtered out instead of being offered and then failing.
        $accounts = collect($pages['data'] ?? [])
            ->filter(fn ($page) => data_get($page, 'instagram_business_account.id'))
            ->map(fn ($page) => [
                'id'           => data_get($page, 'instagram_business_account.id'),
                'page_id'      => $page['id'] ?? null,
                'access_token' => $page['access_token'] ?? null,
                'name'         => data_get($page, 'instagram_business_account.name') ?: $page['name'] ?? null,
                'username'     => data_get($page, 'instagram_business_account.username'),
                'avatar'       => data_get($page, 'instagram_business_account.profile_picture_url'),
                'followers'    => (int) data_get($page, 'instagram_business_account.followers_count', 0),
                'can_publish'  => true,
            ])->values()->all();

        if (!$accounts) {
            throw new PlatformException(
                'No Instagram Business account was found on the Pages you administer. Convert the Instagram account to Business or Creator and link it to a Facebook Page, then try again.',
                'no_business_account'
            );
        }

        return ['name' => 'Instagram', 'pages' => $accounts];
    }

    public function fetchProfile(SocialAccount $account): array {
        $profile = $this->graphGet($this->igToken($account), $this->igUserId($account), [
            'fields' => 'id,username,name,profile_picture_url,followers_count,follows_count,media_count',
        ]);

        return [
            'external_id' => $profile['id'] ?? null,
            'name'        => $profile['name'] ?? $profile['username'] ?? null,
            'username'    => isset($profile['username']) ? '@' . $profile['username'] : null,
            'avatar_url'  => $profile['profile_picture_url'] ?? null,
            'profile_url' => isset($profile['username']) ? 'https://instagram.com/' . $profile['username'] : null,
            'followers'   => (int) ($profile['followers_count'] ?? 0),
            'following'   => (int) ($profile['follows_count'] ?? 0),
            'media_count' => (int) ($profile['media_count'] ?? 0),
        ];
    }

    /* ---------------------------------------------------------------- Publish */

    public function publish(PublishContext $context): PublishResult {
        $account = $context->account;
        $token   = $this->igToken($account);
        $igId    = $this->igUserId($account);

        $caption = $this->composeBody(
            trim($context->caption() . ($context->value('cta') ? "\n\n" . $context->value('cta') : '')),
            (array) $context->value('hashtags', []),
            PlatformCapability::limit($this->key(), 'caption_max', 2200)
        );

        $video  = $context->video();
        $images = $context->images();

        if (!$video && $images->isEmpty()) {
            throw PlatformException::unsupported($this->key(), 'text-only posts - attach an image or a video');
        }

        if ($video) {
            $containerId = $this->createVideoContainer($token, $igId, $video, $caption, $context);
        } elseif ($images->count() === 1) {
            $containerId = $this->createImageContainer($token, $igId, $images->first(), $caption);
        } else {
            $containerId = $this->createCarouselContainer($token, $igId, $images, $caption);
        }

        $this->awaitContainerReady($token, $containerId);

        $published = $this->graphPost($token, "$igId/media_publish", ['creation_id' => $containerId]);
        $mediaId   = (string) ($published['id'] ?? '');

        if (!$mediaId) {
            throw new PlatformException('Instagram accepted the media but returned no post id.', 'rejected');
        }

        if ($firstComment = $context->value('first_comment')) {
            $this->tryFirstComment($token, $mediaId, $firstComment);
        }

        return PublishResult::make($mediaId, $this->permalinkFor($token, $mediaId), ['container_id' => $containerId]);
    }

    protected function createImageContainer(string $token, string $igId, SocialMedia $image, string $caption): string {
        $this->assertFormat($image, 'image');

        $response = $this->graphPost($token, "$igId/media", [
            'image_url' => $this->publicMediaUrl($image),
            'caption'   => $caption,
        ]);

        return $this->containerId($response);
    }

    protected function createVideoContainer(string $token, string $igId, SocialMedia $video, string $caption, PublishContext $context): string {
        $this->assertFormat($video, 'video');

        $params = [
            'video_url'  => $this->publicMediaUrl($video),
            'caption'    => $caption,
            // Everything published as video goes out as a Reel: Instagram
            // stopped accepting standalone feed video through the API.
            'media_type' => 'REELS',
        ];

        if ($cover = $context->thumbnail()) {
            $params['cover_url'] = $this->publicMediaUrl($cover);
        }
        if ($context->value('share_to_feed', true)) {
            $params['share_to_feed'] = 'true';
        }
        if ($location = $context->value('location_id')) {
            $params['location_id'] = $location;
        }

        return $this->containerId($this->graphPost($token, "$igId/media", $params));
    }

    protected function createCarouselContainer(string $token, string $igId, $images, string $caption): string {
        $children = [];

        foreach ($images->take(PlatformCapability::limit($this->key(), 'media_max_count', 10)) as $image) {
            $this->assertFormat($image, 'image');
            $child = $this->graphPost($token, "$igId/media", [
                'image_url'        => $this->publicMediaUrl($image),
                'is_carousel_item' => 'true',
            ]);
            $children[] = $this->containerId($child);
        }

        if (count($children) < 2) {
            throw PlatformException::invalidMedia('An Instagram carousel needs at least two images.');
        }

        return $this->containerId($this->graphPost($token, "$igId/media", [
            'media_type' => 'CAROUSEL',
            'children'   => implode(',', $children),
            'caption'    => $caption,
        ]));
    }

    protected function containerId(array $response): string {
        $id = $response['id'] ?? null;
        if (!$id) {
            throw new PlatformException('Instagram did not return a media container id.', 'rejected');
        }
        return (string) $id;
    }

    /**
     * Waits for Instagram to finish ingesting the media.
     *
     * Publishing a container that is still IN_PROGRESS fails, and an ERROR
     * container never becomes ready, so both are distinguished here rather than
     * being left to time out.
     */
    protected function awaitContainerReady(string $token, string $containerId): void {
        for ($i = 0; $i < self::STATUS_POLL_ATTEMPTS; $i++) {
            $status = $this->graphGet($token, $containerId, ['fields' => 'status_code,status']);
            $code   = $status['status_code'] ?? 'IN_PROGRESS';

            if ($code === 'FINISHED') {
                return;
            }

            if ($code === 'ERROR' || $code === 'EXPIRED') {
                throw new PlatformException(
                    'Instagram could not process the media: ' . ($status['status'] ?? $code)
                    . '. Check that the file meets Instagram\'s format, duration and aspect-ratio requirements.',
                    'media_processing_failed'
                );
            }

            sleep(self::STATUS_POLL_SECONDS);
        }

        throw new PlatformException(
            'Instagram is still processing the media after ' . (self::STATUS_POLL_ATTEMPTS * self::STATUS_POLL_SECONDS) . ' seconds. The publish will be retried automatically.',
            'media_processing_timeout',
            true
        );
    }

    /** Blocks formats the container endpoint is known to reject. */
    protected function assertFormat(SocialMedia $media, string $kind): void {
        $allowed = PlatformCapability::formats($this->key(), $kind);
        if ($allowed && !in_array(strtolower($media->extension), $allowed, true)) {
            throw PlatformException::invalidMedia(
                'Instagram does not accept .' . $media->extension . ' for ' . $kind . 's. Allowed: ' . implode(', ', $allowed) . '.'
            );
        }
    }

    protected function tryFirstComment(string $token, string $mediaId, string $comment): void {
        try {
            $this->graphPost($token, "$mediaId/comments", ['message' => $comment]);
        } catch (\Throwable $e) {
            // The post is live; a failed first comment is not worth failing it.
        }
    }

    protected function permalinkFor(string $token, string $mediaId): ?string {
        try {
            return $this->graphGet($token, $mediaId, ['fields' => 'permalink'])['permalink'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* --------------------------------------------------------------- Metrics */

    public function fetchPostMetrics(SocialAccount $account, SocialPostPlatform $target): ?array {
        if (!$target->platform_post_id) {
            return null;
        }

        $token = $this->igToken($account);

        $counts = $this->graphGet($token, $target->platform_post_id, [
            'fields' => 'like_count,comments_count,media_product_type',
        ]);

        $metrics = [
            'likes'    => (int) ($counts['like_count'] ?? 0),
            'comments' => (int) ($counts['comments_count'] ?? 0),
        ];

        try {
            $isReel  = ($counts['media_product_type'] ?? '') === 'REELS';
            $wanted  = $isReel
                ? 'plays,reach,likes,comments,shares,saved,total_interactions'
                : 'impressions,reach,saved,shares';

            $insights = $this->graphGet($token, $target->platform_post_id . '/insights', ['metric' => $wanted]);

            foreach ($insights['data'] ?? [] as $row) {
                $value = data_get($row, 'values.0.value', 0);
                match ($row['name'] ?? '') {
                    'impressions', 'plays' => $metrics['views']       = (int) $value,
                    'reach'                => $metrics['reach']       = (int) $value,
                    'saved'                => $metrics['saves']       = (int) $value,
                    'shares'               => $metrics['shares']      = (int) $value,
                    default                => null,
                };
            }
        } catch (\Throwable $e) {
            // Insights expire for older media; the counts above still apply.
        }

        return $metrics;
    }

    public function fetchAccountMetrics(SocialAccount $account, string $date): ?array {
        try {
            $profile = $this->fetchProfile($account);
            $metrics = ['followers' => $profile['followers'] ?? null];

            $insights = $this->graphGet($this->igToken($account), $this->igUserId($account) . '/insights', [
                'metric' => 'impressions,reach,profile_views',
                'period' => 'day',
                'since'  => strtotime($date),
                'until'  => strtotime($date . ' +1 day'),
            ]);

            foreach ($insights['data'] ?? [] as $row) {
                $value = data_get($row, 'values.0.value', 0);
                match ($row['name'] ?? '') {
                    'impressions'   => $metrics['impressions'] = (int) $value,
                    'reach'         => $metrics['reach']       = (int) $value,
                    'profile_views' => $metrics['clicks']      = (int) $value,
                    default         => null,
                };
            }

            return $metrics;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function fetchComments(SocialAccount $account, int $limit = 50): array {
        $token = $this->igToken($account);

        $media = $this->graphGet($token, $this->igUserId($account) . '/media', [
            'fields' => 'id,permalink,comments.limit(25){id,text,timestamp,username,from}',
            'limit'  => 25,
        ]);

        $out = [];
        foreach ($media['data'] ?? [] as $item) {
            foreach (data_get($item, 'comments.data', []) as $comment) {
                $out[] = [
                    'external_id'      => $comment['id'] ?? null,
                    'platform_post_id' => $item['id'] ?? null,
                    'kind'             => 'comment',
                    'message'          => $comment['text'] ?? null,
                    'author_name'      => $comment['username'] ?? data_get($comment, 'from.username'),
                    'author_handle'    => isset($comment['username']) ? '@' . $comment['username'] : null,
                    'permalink'        => $item['permalink'] ?? null,
                    'posted_at'        => isset($comment['timestamp']) ? date('Y-m-d H:i:s', strtotime($comment['timestamp'])) : null,
                ];
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }
}
