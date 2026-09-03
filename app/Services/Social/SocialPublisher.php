<?php

namespace App\Services\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialMedia;
use App\Models\Social\SocialPost;
use App\Models\Social\SocialPostPlatform;
use App\Models\Social\SocialPublishAttempt;
use App\Models\Social\SocialPublishJob;
use App\Models\Social\SocialSetting;
use App\Services\Social\Contracts\VerifiesPublication;
use App\Services\Social\Support\PlatformException;
use App\Services\Social\Support\PublishContext;
use App\Services\Social\Support\PublishResult;
use App\Services\Social\Support\SocialHttpClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns everything about getting a post onto a platform.
 *
 * Two halves:
 *
 *  - dispatch()  runs in the web request. It validates, creates the durable
 *                jobs and returns. It never talks to a platform, so a slow
 *                upload can never hold a browser open or time out a page.
 *  - execute()   runs in the background (cron or `social:work`). It performs one
 *                attempt against one platform and records exactly what happened.
 *
 * Duplicate prevention is layered:
 *   1. The post carries an idempotency key, so a double-submitted form creates
 *      one post.
 *   2. Each platform target has a unique key, and its job's key derives from it,
 *      so a double-clicked Publish enqueues one job.
 *   3. execute() re-reads the target and refuses to act on one already
 *      published, so a duplicated job is a no-op.
 *   4. Before retrying an attempt whose outcome is unknown, adapters that can
 *      search their own recent posts are asked whether the post already landed.
 */
class SocialPublisher {

    public function __construct(
        protected PlatformRegistry $registry,
        protected ContentComposer $composer,
        protected MediaValidator $validator,
    ) {}

    /* ==================================================================== */
    /*  Dispatch - fast, synchronous, no platform calls                     */
    /* ==================================================================== */

    /**
     * Queues a post for publishing, now or at its scheduled time.
     *
     * @param  Carbon|null $when null publishes as soon as a runner picks it up.
     * @return array{jobs:int,skipped:array<int,string>}
     */
    public function dispatch(SocialPost $post, ?Carbon $when = null): array {
        $post->loadMissing('targets.account', 'media');

        if (in_array($post->status, [S::PUBLISHING, S::PUBLISHED], true)) {
            throw new \RuntimeException('This post is already ' . S::statusName($post->status) . '.');
        }

        $settings = SocialSetting::config();

        if ($settings->require_approval && $post->approval_status !== S::APPROVAL_APPROVED) {
            throw new \RuntimeException('Approval is required before this post can be published. Submit it for approval first.');
        }

        $targets = $post->targets->reject(fn ($t) => in_array($t->status, [S::PUBLISHED, S::CANCELLED], true));

        if ($targets->isEmpty()) {
            throw new \RuntimeException('There are no platforms left to publish to on this post.');
        }

        $availableAt = $when ?: now();
        $skipped     = [];
        $queued      = 0;

        foreach ($targets as $target) {
            // A target with no usable account would fail on every attempt, so it
            // is reported now rather than after three retries.
            if ($problem = $this->accountProblem($target)) {
                $target->markFailed('not_connected', $problem);
                $skipped[$target->platform] = $problem;
                continue;
            }

            $target->scheduled_at = $availableAt;
            $target->transitionTo($when && $when->isFuture() ? S::SCHEDULED : S::QUEUED);

            $this->enqueue($target, $availableAt);
            $queued++;
        }

        if ($queued === 0) {
            $post->syncStatusFromTargets();
            throw new \RuntimeException('None of the selected platforms could be queued: ' . implode(' ', $skipped));
        }

        $post->scheduled_at = $availableAt;
        $post->status       = $when && $when->isFuture() ? S::SCHEDULED : S::QUEUED;
        $post->save();

        SocialAuditLogger::forModel($when && $when->isFuture() ? 'post.schedule' : 'post.publish', $post, [
            'description' => ($when && $when->isFuture() ? 'Scheduled' : 'Queued') . " $queued platform(s) for \"" . $post->title . '"',
            'context'     => ['platforms' => $targets->pluck('platform')->all(), 'at' => $availableAt->toDateTimeString()],
        ]);

        return ['jobs' => $queued, 'skipped' => $skipped];
    }

    /**
     * Creates the job row for one target.
     *
     * firstOrCreate on a unique key is what makes a double-clicked Publish
     * button harmless: the second call finds the first call's row.
     */
    protected function enqueue(SocialPostPlatform $target, Carbon $availableAt, bool $isRetry = false): SocialPublishJob {
        $settings = SocialSetting::config();

        $key = 'pub:' . $target->idempotency_key;
        if ($isRetry) {
            // A manual retry after final failure is a new cycle, numbered so it
            // stays unique without ever colliding with the original.
            $cycle = SocialPublishJob::where('social_post_platform_id', $target->id)->count();
            $key .= ':r' . $cycle;
        }

        return SocialPublishJob::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'type'                   => S::JOB_PUBLISH,
                'social_post_id'         => $target->social_post_id,
                'social_post_platform_id'=> $target->id,
                'social_account_id'      => $target->social_account_id,
                'platform'               => $target->platform,
                'status'                 => S::JOB_QUEUED,
                'attempts'               => 0,
                'max_attempts'           => max(1, (int) $settings->max_attempts),
                'available_at'           => $availableAt,
                'payload'                => ['target_key' => $target->idempotency_key],
            ]
        );
    }

    /** Re-queues a failed target for another run, from the UI. */
    public function retry(SocialPostPlatform $target): SocialPublishJob {
        if ($target->status === S::PUBLISHED) {
            throw new \RuntimeException('That platform already published successfully.');
        }

        if ($problem = $this->accountProblem($target)) {
            throw new \RuntimeException($problem);
        }

        $target->transitionTo(S::QUEUED, ['error_code' => null, 'error_message' => null, 'next_attempt_at' => null]);
        $job = $this->enqueue($target, now(), true);

        $target->post->syncStatusFromTargets();

        SocialAuditLogger::forModel('post.retry', $target->post, [
            'platform'    => $target->platform,
            'description' => 'Retry requested for ' . $target->platform_name,
        ]);

        return $job;
    }

    /** Cancels a scheduled post and everything queued for it. */
    public function cancel(SocialPost $post): void {
        DB::transaction(function () use ($post) {
            foreach ($post->targets as $target) {
                if (in_array($target->status, [S::PUBLISHED, S::PUBLISHING], true)) {
                    continue;
                }
                $target->update(['status' => S::CANCELLED, 'next_attempt_at' => null]);
            }

            SocialPublishJob::where('social_post_id', $post->id)
                ->whereIn('status', [S::JOB_QUEUED])
                ->update(['status' => S::JOB_CANCELLED, 'completed_at' => now()]);

            $post->syncStatusFromTargets();
        });

        SocialAuditLogger::forModel('post.cancel', $post, ['description' => 'Cancelled "' . $post->title . '"']);
    }

    /** Why this target cannot publish, or null when it is good to go. */
    protected function accountProblem(SocialPostPlatform $target): ?string {
        $adapter = $this->registry->make($target->platform);

        if (!$adapter->isConfigured()) {
            return S::platformName($target->platform) . ' is not configured on this server.';
        }

        $account = $target->account;
        if (!$account) {
            return 'No ' . S::platformName($target->platform) . ' account is connected.';
        }

        if (!$account->isConnected()) {
            return S::platformName($target->platform) . ' account "' . $account->display_name . '" is not connected.';
        }

        return null;
    }

    /* ==================================================================== */
    /*  Execute - background, one attempt against one platform              */
    /* ==================================================================== */

    /**
     * Runs one publish attempt. Never throws: the outcome is always recorded on
     * the job and the target, because a runner that dies mid-loop would leave
     * the queue in an unreadable state.
     *
     * @return bool true when the job is finished (published or permanently failed).
     */
    public function execute(SocialPublishJob $job): bool {
        $target = $job->target;

        if (!$target) {
            $job->fail('The post this job belonged to no longer exists.');
            return true;
        }

        // Re-read under a lock: another runner may have published this in the
        // moment between claiming and starting.
        $target->refresh();

        if ($target->status === S::PUBLISHED) {
            $job->complete();
            return true;
        }

        if ($target->status === S::CANCELLED) {
            $job->status = S::JOB_CANCELLED;
            $job->completed_at = now();
            $job->save();
            return true;
        }

        $settings = SocialSetting::config();
        $attemptNo = $job->attempts + 1;

        $job->attempts = $attemptNo;
        $job->save();

        $target->attempts        = $attemptNo;
        $target->last_attempt_at = now();
        $target->status          = S::PUBLISHING;
        $target->save();
        $target->post->syncStatusFromTargets();

        $startedAt = microtime(true);

        try {
            $context = $this->buildContext($target);
            $adapter = $this->registry->make($target->platform);

            // Unknown-outcome recovery: if a previous attempt may have landed,
            // adopt the existing post rather than creating a second one.
            $existing = $this->findAlreadyPublished($adapter, $context, $job);

            $result = $existing ?: $adapter->publish($context);

            $target->markPublished($result->platformPostId, $result->url);
            $target->metrics = null;
            $target->save();

            $this->recordAttempt($job, $target, $attemptNo, 'success', null, $startedAt, [
                'adopted_existing' => (bool) $existing,
                'meta'             => $result->meta,
            ]);

            $job->complete();
            $target->post->syncStatusFromTargets();

            SocialAuditLogger::forModel('post.publish', $target->post, [
                'platform'    => $target->platform,
                'description' => 'Published to ' . $target->platform_name . ($existing ? ' (recovered an earlier attempt)' : ''),
                'context'     => ['post_id' => $result->platformPostId],
            ]);

            return true;
        } catch (PlatformException $e) {
            return $this->handleFailure($job, $target, $attemptNo, $e, $startedAt, $settings);
        } catch (\Throwable $e) {
            $wrapped = SocialHttpClient::classifyThrowable($target->platform, $e);
            return $this->handleFailure($job, $target, $attemptNo, $wrapped, $startedAt, $settings);
        }
    }

    /**
     * Decides whether to retry, and when.
     *
     * Exponential backoff with jitter: 60s, 120s, 240s from the default base.
     * The jitter matters when a platform outage fails a batch of posts at once -
     * without it they would all retry in the same second and rate-limit
     * themselves.
     */
    protected function handleFailure(
        SocialPublishJob $job,
        SocialPostPlatform $target,
        int $attemptNo,
        PlatformException $e,
        float $startedAt,
        SocialSetting $settings
    ): bool {
        $this->recordAttempt($job, $target, $attemptNo, 'failed', $e, $startedAt);

        // An expired or rejected credential invalidates the account itself, not
        // just this post - flag it so every screen shows the same truth.
        if ($e->requiresReconnect && $target->account) {
            $target->account->markProblem(
                $e->errorCode === 'permission_denied'
                    ? S::ACCOUNT_PERMISSION_ISSUE
                    : S::ACCOUNT_TOKEN_EXPIRED,
                $e->getMessage()
            );
        }

        $canRetry = $e->retryable && $attemptNo < $job->max_attempts;

        if ($canRetry) {
            $base  = max(10, (int) $settings->retry_base_seconds);
            $delay = $base * (2 ** ($attemptNo - 1));
            $delay = (int) min($delay + random_int(0, (int) max(1, $delay * 0.2)), 6 * 3600);

            $nextAt = now()->addSeconds($delay);

            $target->markFailed($e->errorCode, $e->getMessage(), $nextAt);
            $job->release($nextAt, $e->getMessage());
            $target->post->syncStatusFromTargets();

            return false;
        }

        $target->markFailed($e->errorCode, $e->getMessage());
        $job->fail($e->getMessage());
        $target->post->syncStatusFromTargets();

        SocialAuditLogger::record('post.publish', [
            'result'       => 'failed',
            'platform'     => $target->platform,
            'subject_type' => 'SocialPost',
            'subject_id'   => $target->social_post_id,
            'description'  => 'Publishing to ' . $target->platform_name . ' failed: ' . $e->getMessage(),
            'context'      => ['error_code' => $e->errorCode, 'attempts' => $attemptNo],
        ]);

        return true;
    }

    /**
     * Asks the adapter whether an earlier attempt already published.
     *
     * Only worth doing from the second attempt onwards, and only when the
     * previous failure had an unknown outcome (a timeout or 5xx after the
     * request went out) - a 400 never created anything.
     */
    protected function findAlreadyPublished($adapter, PublishContext $context, SocialPublishJob $job): ?PublishResult {
        if ($job->attempts <= 1 || !$adapter instanceof VerifiesPublication) {
            return null;
        }

        $previous = SocialPublishAttempt::where('social_publish_job_id', $job->id)
            ->orderByDesc('id')
            ->first();

        if (!$previous || !in_array($previous->error_code, ['network_error', 'temporary_failure', 'unexpected_error'], true)) {
            return null;
        }

        try {
            return $adapter->findRecentPublication($context, $previous->started_at ?: now()->subHours(6));
        } catch (\Throwable $e) {
            // Verification is a safety net; failing it just means we retry.
            return null;
        }
    }

    protected function recordAttempt(
        SocialPublishJob $job,
        SocialPostPlatform $target,
        int $attemptNo,
        string $status,
        ?PlatformException $e,
        float $startedAt,
        array $extra = []
    ): void {
        try {
            SocialPublishAttempt::create([
                'social_publish_job_id'   => $job->id,
                'social_post_platform_id' => $target->id,
                'platform'                => $target->platform,
                'attempt_no'              => $attemptNo,
                'status'                  => $status,
                'response_code'           => $e?->httpStatus,
                'request_summary'         => SocialHttpClient::redact(array_merge([
                    'platform'    => $target->platform,
                    'account_id'  => $target->social_account_id,
                    'content_type'=> $target->post?->content_type,
                ], $extra)),
                'response_summary'        => $e?->detail ? mb_substr($e->detail, 0, 4000) : null,
                'error_code'              => $e?->errorCode,
                'error_message'           => $e?->getMessage(),
                'retryable'               => (bool) $e?->retryable,
                'started_at'              => Carbon::createFromTimestamp((int) $startedAt),
                'finished_at'             => now(),
                'duration_ms'             => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $ignored) {
            // Never let attempt logging break a publish.
        }
    }

    /* ==================================================================== */
    /*  Context assembly                                                    */
    /* ==================================================================== */

    public function buildContext(SocialPostPlatform $target): PublishContext {
        $post    = $target->post()->with('media', 'campaign')->firstOrFail();
        $account = $target->account;

        if (!$account) {
            throw PlatformException::notConnected($target->platform);
        }

        // Empty payload means the target was created without customisation -
        // fall back to the composed default rather than publishing nothing.
        if (!$target->payload) {
            $target->payload = app(ContentComposer::class)->compose($post, $target->platform);
            $target->save();
        }

        return new PublishContext(
            post: $post,
            target: $target,
            account: $account,
            media: $this->mediaFor($post, $target->platform),
            links: UtmBuilder::forPost($post, $target->platform),
            idempotencyKey: $target->idempotency_key,
        );
    }

    /**
     * Media applicable to one platform: assets pinned to it, plus the shared
     * ones. A platform-specific asset replaces the shared asset of the same
     * role rather than being added alongside it.
     */
    protected function mediaFor(SocialPost $post, string $platform): Collection {
        $all = $post->media;

        $specific = $all->filter(fn (SocialMedia $m) => ($m->pivot->platform ?? null) === $platform);
        $shared   = $all->filter(fn (SocialMedia $m) => ($m->pivot->platform ?? null) === null);

        $overriddenRoles = $specific->map(fn ($m) => $m->pivot->role ?? 'primary')->unique();

        return $specific
            ->merge($shared->reject(fn ($m) => $overriddenRoles->contains($m->pivot->role ?? 'primary')))
            ->sortBy(fn ($m) => $m->pivot->order ?? 0)
            ->values();
    }

    /* ==================================================================== */
    /*  Target management                                                   */
    /* ==================================================================== */

    /**
     * Creates or updates the per-platform rows for a post.
     *
     * @param array<string,array> $customisations platform => payload overrides
     */
    public function syncTargets(SocialPost $post, array $platforms, array $customisations = [], array $accountIds = []): void {
        $composer = app(ContentComposer::class);

        DB::transaction(function () use ($post, $platforms, $customisations, $accountIds, $composer) {
            // Removing a platform must never delete something already live.
            $post->targets()
                ->whereNotIn('platform', $platforms)
                ->whereNotIn('status', [S::PUBLISHED, S::PUBLISHING])
                ->delete();

            foreach ($platforms as $platform) {
                $accountId = $accountIds[$platform] ?? $this->defaultAccountId($platform);

                $target = $post->targets()->where('platform', $platform)->first();

                $payload = array_merge(
                    $composer->compose($post, $platform),
                    $customisations[$platform] ?? []
                );

                if ($target) {
                    if (in_array($target->status, [S::PUBLISHED, S::PUBLISHING], true)) {
                        continue;
                    }
                    $target->update([
                        'payload'           => $payload,
                        'social_account_id' => $accountId,
                    ]);
                    continue;
                }

                $post->targets()->create([
                    'platform'          => $platform,
                    'social_account_id' => $accountId,
                    'payload'           => $payload,
                    'status'            => S::DRAFT,
                    'idempotency_key'   => Str::uuid()->toString(),
                ]);
            }
        });

        $post->load('targets');
    }

    protected function defaultAccountId(string $platform): ?int {
        return SocialAccount::platform($platform)
            ->connected()
            ->orderByDesc('is_default')
            ->value('id');
    }
}
