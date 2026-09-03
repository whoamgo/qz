<?php

namespace App\Services\Social\Contracts;

use App\Services\Social\Support\PublishContext;
use App\Services\Social\Support\PublishResult;
use DateTimeInterface;

/**
 * Implemented by adapters that can answer "did my last attempt actually land?"
 *
 * This is the defence against the worst failure mode in publishing: the request
 * reached the platform, the post was created, and the response was lost to a
 * timeout. Retrying blindly would publish the same content twice. Before any
 * retry of an attempt whose outcome is unknown, the publisher asks the adapter
 * to look for the post - and adopts it instead of re-posting if it is there.
 *
 * Adapters that cannot search their own recent posts simply do not implement
 * this, and the publisher falls back to not retrying unknown-outcome failures.
 */
interface VerifiesPublication {

    /**
     * Looks for a post matching this context published since $since.
     *
     * @return PublishResult|null the existing post, or null if there is none.
     */
    public function findRecentPublication(PublishContext $context, DateTimeInterface $since): ?PublishResult;
}
