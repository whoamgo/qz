<?php

namespace App\Services\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialPost;

/**
 * Turns one piece of master content into a sensible starting draft for each
 * platform.
 *
 * This is deterministic and rule-based, not AI: it always runs, it costs
 * nothing, and it produces the same result twice. The AI generator (optional,
 * separate) improves on these drafts when it is enabled.
 *
 * Everything produced here is a *starting point*. The wizard renders each
 * platform's version in an editable form, and whatever the admin saves is what
 * gets published - this never overwrites an edited payload.
 */
class ContentComposer {

    /** Builds default payloads for every requested platform. */
    public function composeAll(SocialPost $post, array $platforms): array {
        $out = [];
        foreach ($platforms as $platform) {
            $out[$platform] = $this->compose($post, $platform);
        }
        return $out;
    }

    public function compose(SocialPost $post, string $platform): array {
        $hashtags = $this->hashtagsFor($post, $platform);
        $links    = UtmBuilder::forPost($post, $platform);
        $link     = $links['quiz_url'] ?? $links['website_url'] ?? null;

        $base = [
            'caption'  => trim((string) ($post->caption ?: $post->title)),
            'hashtags' => $hashtags,
            'cta'      => $post->cta,
            'link'     => $link,
        ];

        return match ($platform) {
            S::YOUTUBE   => $this->youtube($post, $base, $link),
            S::INSTAGRAM => $this->instagram($post, $base),
            S::FACEBOOK  => $this->facebook($post, $base),
            S::X         => $this->x($post, $base),
            S::LINKEDIN  => $this->linkedin($post, $base),
            S::TELEGRAM  => $this->telegram($post, $base, $link),
            S::WHATSAPP  => $this->whatsapp($post, $base),
            S::THREADS   => $this->threads($post, $base),
            default      => $base,
        };
    }

    /* --------------------------------------------------------- Per platform */

    protected function youtube(SocialPost $post, array $base, ?string $link): array {
        $isShort = in_array($post->content_type, [S::CONTENT_SHORT, S::CONTENT_REEL], true);

        return $base + [
            'title'         => $this->truncate($post->title ?: $post->caption, PlatformCapability::limit(S::YOUTUBE, 'title_max', 100)),
            'description'   => $this->youtubeDescription($post, $link),
            // YouTube tags are keywords, not hashtags, so the leading # goes.
            'tags'          => array_map(fn ($t) => ltrim($t, '#'), $base['hashtags']),
            'visibility'    => 'public',
            'category_id'   => null,
            'playlist_id'   => null,
            'as_short'      => $isShort,
            'made_for_kids' => false,
            'first_comment' => null,
        ];
    }

    protected function youtubeDescription(SocialPost $post, ?string $link): string {
        $parts = array_filter([
            $post->description ?: $post->caption,
            $post->cta,
            $link ? "\n" . $link : null,
        ]);

        return $this->truncate(implode("\n\n", $parts), PlatformCapability::limit(S::YOUTUBE, 'description_max', 5000));
    }

    protected function instagram(SocialPost $post, array $base): array {
        // Instagram captions do not linkify, so a bare URL is noise. The link
        // is moved to the first comment where it is at least copyable.
        $caption = $this->truncate(
            trim(($post->caption ?: $post->title) . ($post->cta ? "\n\n" . $post->cta : '')),
            PlatformCapability::limit(S::INSTAGRAM, 'caption_max', 2200)
        );

        return $base + [
            'caption'       => $caption,
            'first_comment' => $base['link'] ? 'Link: ' . $base['link'] : null,
            'share_to_feed' => true,
            'location_id'   => null,
            'cover_image'   => null,
        ];
    }

    protected function facebook(SocialPost $post, array $base): array {
        return $base + [
            'caption'       => $this->truncate(
                trim(($post->caption ?: $post->title) . ($post->cta ? "\n\n" . $post->cta : '')),
                PlatformCapability::limit(S::FACEBOOK, 'caption_max', 63206)
            ),
            'as_reel'       => in_array($post->content_type, [S::CONTENT_REEL, S::CONTENT_SHORT], true),
            'first_comment' => null,
        ];
    }

    protected function x(SocialPost $post, array $base): array {
        $limit = PlatformCapability::limit(S::X, 'caption_max', 280);

        // Reserve room for the link and a couple of hashtags rather than
        // letting them push the copy over the limit.
        $reserved = ($base['link'] ? 24 : 0) + 20;
        $caption  = $this->truncate($post->caption ?: $post->title, max(60, $limit - $reserved));

        return $base + [
            'caption'  => $caption,
            // X hashtags are best kept to a handful; more reads as spam.
            'hashtags' => array_slice($base['hashtags'], 0, 3),
            'thread'   => [],
        ];
    }

    protected function linkedin(SocialPost $post, array $base): array {
        $caption = $post->caption ?: $post->title;

        // LinkedIn rewards a lead line followed by context, so the title is
        // promoted to a headline when the body does not already open with it.
        if ($post->title && !str_starts_with(trim($caption), trim($post->title))) {
            $caption = $post->title . "\n\n" . $caption;
        }
        if ($post->description) {
            $caption .= "\n\n" . $post->description;
        }
        if ($post->cta) {
            $caption .= "\n\n" . $post->cta;
        }

        return $base + [
            'caption'  => $this->truncate($caption, PlatformCapability::limit(S::LINKEDIN, 'caption_max', 3000)),
            'hashtags' => array_slice($base['hashtags'], 0, PlatformCapability::limit(S::LINKEDIN, 'hashtag_max', 10)),
            'title'    => $post->title,
        ];
    }

    protected function telegram(SocialPost $post, array $base, ?string $link): array {
        return $base + [
            'title'       => $post->title,
            'caption'     => $post->caption ?: $post->title,
            'button_text' => $post->cta ?: ($link ? 'Open' : null),
            'button_url'  => $link,
        ];
    }

    protected function whatsapp(SocialPost $post, array $base): array {
        return $base + [
            'caption'           => $this->truncate(
                trim(($post->caption ?: $post->title) . ($post->cta ? "\n\n" . $post->cta : '')),
                PlatformCapability::limit(S::WHATSAPP, 'caption_max', 1024)
            ),
            // WhatsApp strips hashtags of their meaning - they are just text.
            'hashtags'          => [],
            'template_name'     => null,
            'template_language' => 'en_US',
            'allow_freeform'    => false,
            'recipients'        => null,
        ];
    }

    protected function threads(SocialPost $post, array $base): array {
        return $base + [
            'caption'  => $this->truncate($post->caption ?: $post->title, PlatformCapability::limit(S::THREADS, 'caption_max', 500)),
            'hashtags' => array_slice($base['hashtags'], 0, 3),
        ];
    }

    /* -------------------------------------------------------------- Helpers */

    /** Post hashtags, capped to what the platform tolerates. */
    protected function hashtagsFor(SocialPost $post, string $platform): array {
        $max = PlatformCapability::limit($platform, 'hashtag_max', 10);
        if ($max === 0) {
            return [];
        }

        return array_slice($post->hashtagList(), 0, $max);
    }

    protected function truncate(?string $text, int $limit): string {
        $text = trim((string) $text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut   = mb_substr($text, 0, $limit - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space && $space > $limit * 0.6 ? mb_substr($cut, 0, $space) : $cut) . '…';
    }
}
