<?php

namespace App\Services\Social\Support;

use App\Models\Social\SocialAccount;
use App\Models\Social\SocialMedia;
use App\Models\Social\SocialPost;
use App\Models\Social\SocialPostPlatform;
use Illuminate\Support\Collection;

/**
 * Everything an adapter needs for one publish, resolved once by the publisher.
 *
 * Adapters receive this rather than reaching back into the database, which
 * keeps them thin and makes them straightforward to exercise in tests.
 */
class PublishContext {

    public function __construct(
        public SocialPost $post,
        public SocialPostPlatform $target,
        public SocialAccount $account,
        /** @var Collection<int,SocialMedia> media applicable to this platform */
        public Collection $media,
        /** Links already rewritten with UTM parameters where enabled. */
        public array $links = [],
        /** The idempotency key to hand to platforms that accept one. */
        public string $idempotencyKey = '',
    ) {}

    public function value(string $key, $default = null) {
        return data_get($this->target->payload, $key, $default);
    }

    public function caption(): string {
        return (string) $this->value('caption', $this->post->caption);
    }

    public function title(): ?string {
        return $this->value('title', $this->post->title);
    }

    public function description(): ?string {
        return $this->value('description', $this->post->description);
    }

    /** Media with the given pivot role, in author-defined order. */
    public function mediaOfRole(string $role): Collection {
        return $this->media->filter(fn (SocialMedia $m) => ($m->pivot->role ?? 'primary') === $role)->values();
    }

    public function images(): Collection {
        return $this->mediaOfRole('primary')->filter(fn ($m) => $m->type === 'image')->values();
    }

    public function video(): ?SocialMedia {
        return $this->mediaOfRole('primary')->firstWhere('type', 'video');
    }

    public function thumbnail(): ?SocialMedia {
        return $this->mediaOfRole('thumbnail')->first();
    }

    public function primaryLink(): ?string {
        return $this->links['quiz_url'] ?? $this->links['website_url'] ?? null;
    }

    public function hasMedia(): bool {
        return $this->media->isNotEmpty();
    }
}
