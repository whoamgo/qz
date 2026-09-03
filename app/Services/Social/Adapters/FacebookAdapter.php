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

/**
 * Facebook Pages, via the official Graph API.
 *
 * Publishing always happens as a Page, never as the authorising person: the
 * user token obtained at connect time is only used to list Pages and to mint a
 * Page access token, which is what gets stored and used from then on.
 */
class FacebookAdapter extends GraphApiAdapter {

    public function key(): string {
        return SocialStatus::FACEBOOK;
    }

    /** Video bytes go to the dedicated upload host. */
    protected function videoBase(): string {
        return 'https://graph-video.facebook.com/' . $this->config('graph_version', 'v21.0');
    }

    protected function pageId(SocialAccount $account): string {
        $id = $account->credential('page_id');
        if (!$id) {
            throw new PlatformException(
                'This Facebook account has no Page selected. Reconnect it and choose the Page to publish to.',
                'not_configured'
            );
        }
        return (string) $id;
    }

    /** Page tokens are what the publishing endpoints require. */
    protected function pageToken(SocialAccount $account): string {
        $token = $account->credential('page_access_token');
        if (!$token) {
            throw PlatformException::notConnected($this->key());
        }
        if ($account->tokenExpired()) {
            throw PlatformException::tokenExpired($this->key());
        }
        return (string) $token;
    }

    /* ------------------------------------------------------------------ OAuth */

    /**
     * After the user token is obtained, the Pages they administer are listed so
     * the connect flow can offer a choice. Each Page carries its own token.
     */
    protected function profileFromToken(string $token): array {
        $me = $this->graphGet($token, 'me', ['fields' => 'id,name']);

        $pages = $this->graphGet($token, 'me/accounts', [
            'fields' => 'id,name,username,access_token,picture{url},fan_count,link,tasks',
            'limit'  => 100,
        ]);

        $list = collect($pages['data'] ?? [])->map(fn ($page) => [
            'id'           => $page['id'] ?? null,
            'name'         => $page['name'] ?? null,
            'username'     => $page['username'] ?? null,
            'access_token' => $page['access_token'] ?? null,
            'avatar'       => data_get($page, 'picture.data.url'),
            'followers'    => (int) ($page['fan_count'] ?? 0),
            'link'         => $page['link'] ?? null,
            // A Page the admin cannot post to would fail later with a confusing
            // permission error, so it is flagged here instead.
            'can_publish'  => in_array('CREATE_CONTENT', (array) ($page['tasks'] ?? []), true),
        ])->filter(fn ($p) => $p['id'] && $p['access_token'])->values()->all();

        return [
            'external_id' => $me['id'] ?? null,
            'name'        => $me['name'] ?? 'Facebook',
            'pages'       => $list,
        ];
    }

    public function fetchProfile(SocialAccount $account): array {
        $token = $this->pageToken($account);
        $page  = $this->graphGet($token, $this->pageId($account), [
            'fields' => 'id,name,username,fan_count,followers_count,link,picture{url}',
        ]);

        return [
            'external_id' => $page['id'] ?? null,
            'name'        => $page['name'] ?? null,
            'username'    => isset($page['username']) ? '@' . $page['username'] : null,
            'avatar_url'  => data_get($page, 'picture.data.url'),
            'profile_url' => $page['link'] ?? null,
            'followers'   => (int) ($page['followers_count'] ?? $page['fan_count'] ?? 0),
        ];
    }

    public function testConnection(SocialAccount $account): ConnectionResult {
        try {
            $profile = $this->fetchProfile($account);

            // Reading the Page proves the token works; posting needs
            // pages_manage_posts, which only shows up in the permission list.
            $perms = $this->graphGet($this->pageToken($account), 'me/permissions');
            $granted = collect($perms['data'] ?? [])
                ->where('status', 'granted')
                ->pluck('permission')
                ->all();

            $missing = array_diff(['pages_manage_posts'], $granted);
            if ($granted && $missing) {
                return ConnectionResult::fail(
                    'Connected, but the "pages_manage_posts" permission was not granted, so publishing will fail. Reconnect and accept all requested permissions.',
                    $profile
                );
            }

            return ConnectionResult::ok('Connected to Page "' . ($profile['name'] ?? '') . '".', $profile);
        } catch (PlatformException $e) {
            return ConnectionResult::fail($e->getMessage());
        }
    }

    /* ---------------------------------------------------------------- Publish */

    public function publish(PublishContext $context): PublishResult {
        $account = $context->account;
        $token   = $this->pageToken($account);
        $pageId  = $this->pageId($account);

        $message = $this->composeBody(
            trim($context->caption() . ($context->value('cta') ? "\n\n" . $context->value('cta') : '')),
            (array) $context->value('hashtags', []),
            PlatformCapability::limit($this->key(), 'caption_max', 63206)
        );

        $video  = $context->video();
        $images = $context->images();

        if ($video) {
            $result = $context->value('as_reel')
                ? $this->publishReel($token, $pageId, $video, $message)
                : $this->publishVideo($token, $pageId, $video, $message, $context->title());
        } elseif ($images->count() === 1) {
            $result = $this->publishPhoto($token, $pageId, $images->first(), $message);
        } elseif ($images->count() > 1) {
            $result = $this->publishPhotoAlbum($token, $pageId, $images, $message);
        } else {
            $result = $this->publishText($token, $pageId, $message, $context->primaryLink());
        }

        // A first comment is a separate call: failing it must not undo a
        // successful post, so it is attempted on a best-effort basis.
        if ($firstComment = $context->value('first_comment')) {
            $this->tryFirstComment($token, $result->platformPostId, $firstComment);
        }

        return $result;
    }

    protected function publishText(string $token, string $pageId, string $message, ?string $link): PublishResult {
        $params = ['message' => $message];
        if ($link) {
            $params['link'] = $link;
        }

        $response = $this->graphPost($token, "$pageId/feed", $params);
        $id       = (string) ($response['id'] ?? '');

        return PublishResult::make($id, $this->permalink($id));
    }

    protected function publishPhoto(string $token, string $pageId, SocialMedia $image, string $message): PublishResult {
        $response = $this->uploadBinary($token, $this->apiBase() . "/$pageId/photos", $image, [
            'caption' => $message,
        ]);

        $postId = (string) ($response['post_id'] ?? $response['id'] ?? '');
        return PublishResult::make($postId, $this->permalink($postId), ['photo_id' => $response['id'] ?? null]);
    }

    /**
     * Album: each photo is uploaded unpublished, then a single feed story
     * attaches them all. Uploading them published would produce N separate posts.
     */
    protected function publishPhotoAlbum(string $token, string $pageId, $images, string $message): PublishResult {
        $attached = [];

        foreach ($images as $image) {
            $uploaded = $this->uploadBinary($token, $this->apiBase() . "/$pageId/photos", $image, [
                'published'      => 'false',
                'temporary'      => 'true',
            ]);
            if (!empty($uploaded['id'])) {
                $attached[] = ['media_fbid' => $uploaded['id']];
            }
        }

        if (!$attached) {
            throw PlatformException::invalidMedia('None of the selected images could be uploaded to Facebook.');
        }

        $params = ['message' => $message];
        foreach ($attached as $i => $entry) {
            $params["attached_media[$i]"] = json_encode($entry);
        }

        $response = $this->graphPost($token, "$pageId/feed", $params);
        $id       = (string) ($response['id'] ?? '');

        return PublishResult::make($id, $this->permalink($id), ['photos' => count($attached)]);
    }

    protected function publishVideo(string $token, string $pageId, SocialMedia $video, string $message, ?string $title): PublishResult {
        $params = ['description' => $message];
        if ($title) {
            $params['title'] = $this->clamp($title, 255);
        }

        $response = $this->uploadBinary($token, $this->videoBase() . "/$pageId/videos", $video, $params);
        $id       = (string) ($response['id'] ?? '');

        return PublishResult::make($id, $id ? "https://www.facebook.com/{$id}" : null);
    }

    /**
     * Reels use a three-phase protocol: reserve a video id, upload the bytes to
     * the returned rupload host, then finish with the caption.
     */
    protected function publishReel(string $token, string $pageId, SocialMedia $video, string $message): PublishResult {
        $start = $this->graphPost($token, "$pageId/video_reels", ['upload_phase' => 'start']);

        $videoId   = $start['video_id'] ?? null;
        $uploadUrl = $start['upload_url'] ?? null;
        if (!$videoId || !$uploadUrl) {
            throw new PlatformException('Facebook did not return a reel upload session.', 'rejected');
        }

        $path = $video->absolutePath();
        if (!$path || !is_readable($path)) {
            throw PlatformException::invalidMedia('The video file could not be read from storage.');
        }

        $upload = $this->http->request()
            ->withHeaders([
                'Authorization'  => 'OAuth ' . $token,
                'offset'         => '0',
                'file_size'      => (string) filesize($path),
                'Content-Type'   => 'application/octet-stream',
            ])
            ->withBody(fopen($path, 'r'), 'application/octet-stream')
            ->post($uploadUrl);

        if (!$upload->successful()) {
            throw \App\Services\Social\Support\SocialHttpClient::classify($this->key(), $upload, 'The reel upload failed.');
        }

        $this->graphPost($token, "$pageId/video_reels", [
            'upload_phase'  => 'finish',
            'video_id'      => $videoId,
            'video_state'   => 'PUBLISHED',
            'description'   => $message,
        ]);

        return PublishResult::make((string) $videoId, "https://www.facebook.com/reel/{$videoId}", ['reel' => true]);
    }

    /** Multipart upload of a stored asset to a Graph endpoint. */
    protected function uploadBinary(string $token, string $url, SocialMedia $media, array $params): array {
        $path = $media->absolutePath();
        if (!$path || !is_readable($path)) {
            throw PlatformException::invalidMedia('The media file could not be read from storage.');
        }

        if ($proof = $this->appSecretProof($token)) {
            $params['appsecret_proof'] = $proof;
        }

        $response = $this->http->withToken($token)
            ->attach('source', fopen($path, 'r'), $media->filename)
            ->post($url, $params);

        return $this->result($response);
    }

    protected function tryFirstComment(string $token, string $postId, string $comment): void {
        if (!$postId) {
            return;
        }
        try {
            $this->graphPost($token, "$postId/comments", ['message' => $comment]);
        } catch (\Throwable $e) {
            \App\Services\Social\Support\SocialHttpClient::log($this->key(), 'first comment failed', ['post' => $postId]);
        }
    }

    protected function permalink(string $postId): ?string {
        return $postId ? 'https://www.facebook.com/' . $postId : null;
    }

    /* --------------------------------------------------------------- Metrics */

    public function fetchPostMetrics(SocialAccount $account, SocialPostPlatform $target): ?array {
        if (!$target->platform_post_id) {
            return null;
        }

        $token = $this->pageToken($account);

        $counts = $this->graphGet($token, $target->platform_post_id, [
            'fields' => 'likes.summary(true).limit(0),comments.summary(true).limit(0),shares',
        ]);

        $metrics = [
            'likes'    => (int) data_get($counts, 'likes.summary.total_count', 0),
            'comments' => (int) data_get($counts, 'comments.summary.total_count', 0),
            'shares'   => (int) data_get($counts, 'shares.count', 0),
        ];

        // Insights are permission-gated and unavailable on some post kinds, so
        // a failure here leaves the engagement counts above intact.
        try {
            $insights = $this->graphGet($token, $target->platform_post_id . '/insights', [
                'metric' => 'post_impressions,post_impressions_unique,post_clicks,post_video_views',
            ]);

            foreach ($insights['data'] ?? [] as $row) {
                $value = data_get($row, 'values.0.value', 0);
                match ($row['name'] ?? '') {
                    'post_impressions'        => $metrics['impressions'] = (int) $value,
                    'post_impressions_unique' => $metrics['reach']       = (int) $value,
                    'post_clicks'             => $metrics['clicks']      = (int) $value,
                    'post_video_views'        => $metrics['views']       = (int) $value,
                    default                   => null,
                };
            }
        } catch (\Throwable $e) {
            // Counts without insights is still useful data.
        }

        return $metrics;
    }

    public function fetchAccountMetrics(SocialAccount $account, string $date): ?array {
        try {
            $profile = $this->fetchProfile($account);
            $metrics = ['followers' => $profile['followers'] ?? null];

            $insights = $this->graphGet($this->pageToken($account), $this->pageId($account) . '/insights', [
                'metric' => 'page_impressions,page_impressions_unique,page_post_engagements',
                'period' => 'day',
                'since'  => $date,
                'until'  => date('Y-m-d', strtotime($date . ' +1 day')),
            ]);

            foreach ($insights['data'] ?? [] as $row) {
                $value = data_get($row, 'values.0.value', 0);
                match ($row['name'] ?? '') {
                    'page_impressions'        => $metrics['impressions'] = (int) $value,
                    'page_impressions_unique' => $metrics['reach']       = (int) $value,
                    'page_post_engagements'   => $metrics['likes']       = (int) $value,
                    default                   => null,
                };
            }

            return $metrics;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function fetchComments(SocialAccount $account, int $limit = 50): array {
        $token = $this->pageToken($account);

        $feed = $this->graphGet($token, $this->pageId($account) . '/feed', [
            'fields' => 'id,permalink_url,comments.limit(25){id,message,created_time,from{id,name,picture},permalink_url}',
            'limit'  => 25,
        ]);

        $out = [];
        foreach ($feed['data'] ?? [] as $post) {
            foreach (data_get($post, 'comments.data', []) as $comment) {
                $out[] = [
                    'external_id'      => $comment['id'] ?? null,
                    'platform_post_id' => $post['id'] ?? null,
                    'kind'             => 'comment',
                    'message'          => $comment['message'] ?? null,
                    'author_name'      => data_get($comment, 'from.name'),
                    'author_handle'    => data_get($comment, 'from.id'),
                    'author_avatar'    => data_get($comment, 'from.picture.data.url'),
                    'permalink'        => $comment['permalink_url'] ?? ($post['permalink_url'] ?? null),
                    'posted_at'        => isset($comment['created_time']) ? date('Y-m-d H:i:s', strtotime($comment['created_time'])) : null,
                ];
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    public function deletePost(SocialAccount $account, SocialPostPlatform $target): bool {
        if (!$target->platform_post_id) {
            return false;
        }
        return $this->graphDelete($this->pageToken($account), $target->platform_post_id);
    }
}
