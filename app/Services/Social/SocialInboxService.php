<?php

namespace App\Services\Social;

use App\Models\Social\SocialAccount;
use App\Models\Social\SocialComment;
use App\Models\Social\SocialPostPlatform;

/**
 * Pulls comments and mentions into a single inbox.
 *
 * Coverage is exactly what the official APIs expose, and no more: Facebook,
 * Instagram and YouTube return comments; X returns mentions only, and only on
 * an access tier that includes search. Telegram, LinkedIn and Threads expose
 * nothing usable here, so their adapters return an empty list and the UI says
 * so rather than showing a permanently empty tab with no explanation.
 */
class SocialInboxService {

    public function __construct(protected PlatformRegistry $registry) {}

    /** @return array{synced:int,accounts:int,errors:int} */
    public function syncAll(): array {
        $stats = ['synced' => 0, 'accounts' => 0, 'errors' => 0];

        foreach (SocialAccount::connected()->get() as $account) {
            if (!PlatformCapability::supports($account->platform, 'inbox')) {
                continue;
            }

            try {
                $stats['synced'] += $this->syncAccount($account);
                $stats['accounts']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /** @return int the number of new items stored. */
    public function syncAccount(?SocialAccount $account, int $limit = 50): int {
        if (!$account || !$account->isPublishable()) {
            return 0;
        }

        $items = $this->registry->make($account->platform)->fetchComments($account, $limit);
        $new   = 0;

        foreach ($items as $item) {
            if (empty($item['external_id'])) {
                continue;
            }

            // Match the comment back to our own post where we published it, so
            // the inbox can link through to the post record.
            $targetId = null;
            if (!empty($item['platform_post_id'])) {
                $targetId = SocialPostPlatform::where('platform', $account->platform)
                    ->where('platform_post_id', $item['platform_post_id'])
                    ->value('id');
            }

            $comment = SocialComment::firstOrNew([
                'platform'    => $account->platform,
                'external_id' => $item['external_id'],
            ]);

            $isNew = !$comment->exists;

            $comment->fill([
                'social_account_id'       => $account->id,
                'social_post_platform_id' => $targetId,
                'kind'                    => $item['kind'] ?? 'comment',
                'platform_post_id'        => $item['platform_post_id'] ?? null,
                'author_name'             => $item['author_name'] ?? null,
                'author_handle'           => $item['author_handle'] ?? null,
                'author_avatar'           => $item['author_avatar'] ?? null,
                'message'                 => $item['message'] ?? null,
                'permalink'               => $item['permalink'] ?? null,
                'posted_at'               => $item['posted_at'] ?? null,
            ]);

            $comment->save();

            if ($isNew) {
                $new++;
            }
        }

        $account->last_sync_at = now();
        $account->save();

        return $new;
    }

    /** Platforms whose inbox this install can actually populate. */
    public function supportedPlatforms(): array {
        return array_values(array_filter(
            array_keys(\App\Constants\SocialStatus::PLATFORMS),
            fn ($p) => PlatformCapability::supports($p, 'inbox')
        ));
    }

    public function unreadCount(): int {
        return SocialComment::unread()->count();
    }
}
