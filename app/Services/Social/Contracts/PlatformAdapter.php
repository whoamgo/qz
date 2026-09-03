<?php

namespace App\Services\Social\Contracts;

use App\Models\Social\SocialAccount;
use App\Models\Social\SocialPostPlatform;
use App\Services\Social\Support\ConnectionResult;
use App\Services\Social\Support\PublishContext;
use App\Services\Social\Support\PublishResult;
use Illuminate\Http\Request;

/**
 * The contract every platform implements.
 *
 * Adding a platform means writing one class against this interface and
 * registering it - nothing else in the system needs to know it exists.
 * Implementations must never be called from a controller or a view directly;
 * everything routes through SocialPublisher / AccountService.
 */
interface PlatformAdapter {

    /** Platform key, matching App\Constants\SocialStatus. */
    public function key(): string;

    /** True when the server has the app credentials this platform needs. */
    public function isConfigured(): bool;

    /**
     * Where to send the admin to authorise. $state is generated and stored
     * server-side by the caller and must be echoed back on callback.
     *
     * @return array{url:string,session:array} extra values (PKCE verifier) the
     *         caller must keep in the session until the callback returns.
     */
    public function authorizationUrl(string $state): array;

    /**
     * Exchanges the callback for credentials and returns account facts.
     *
     * @return array{credentials:array,profile:array,expires_at:?string,scopes:?string}
     */
    public function exchangeCallback(Request $request, array $session): array;

    /**
     * Refreshes an expiring token in place. Returns false when the platform
     * has no refresh mechanism (the account must be reconnected by hand).
     */
    public function refreshToken(SocialAccount $account): bool;

    /** A live, read-only call proving the stored credentials still work. */
    public function testConnection(SocialAccount $account): ConnectionResult;

    /** Current profile facts: name, handle, avatar, follower counts. */
    public function fetchProfile(SocialAccount $account): array;

    /** Publishes one platform target. Throws PlatformException on failure. */
    public function publish(PublishContext $context): PublishResult;

    /** Metrics for a single published post, or null when unavailable. */
    public function fetchPostMetrics(SocialAccount $account, SocialPostPlatform $target): ?array;

    /** Account-level metrics for a date, or null when unavailable. */
    public function fetchAccountMetrics(SocialAccount $account, string $date): ?array;

    /** Recent comments/mentions for the inbox. Empty when unsupported. */
    public function fetchComments(SocialAccount $account, int $limit = 50): array;

    /** Deletes a published post where the API allows it. */
    public function deletePost(SocialAccount $account, SocialPostPlatform $target): bool;
}
