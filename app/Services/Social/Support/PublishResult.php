<?php

namespace App\Services\Social\Support;

/** What an adapter returns after a successful publish. */
class PublishResult {

    public function __construct(
        public string $platformPostId,
        public ?string $url = null,
        /** Extra ids worth keeping (thread part ids, container ids, ...). */
        public array $meta = [],
    ) {}

    public static function make(string $id, ?string $url = null, array $meta = []): self {
        return new self($id, $url, $meta);
    }
}
