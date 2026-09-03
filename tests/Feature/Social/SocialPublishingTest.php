<?php

namespace Tests\Feature\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialPublishAttempt;
use App\Models\Social\SocialPublishJob;
use App\Services\Social\PostStateMachine;
use App\Services\Social\SocialQueueRunner;
use App\Services\Social\Support\PlatformException;
use App\Services\Social\Support\PublishResult;

/**
 * The publishing pipeline: dispatch, retry, partial success, duplicate
 * prevention and recovery from a lost response.
 *
 * These are the behaviours where being wrong means posting twice to a live
 * audience, or claiming a post went out when it did not.
 */
class SocialPublishingTest extends SocialTestCase {

    /* ------------------------------------------------------ Happy path */

    public function test_dispatch_creates_one_job_per_platform(): void {
        $post = $this->post([S::TELEGRAM, S::FACEBOOK]);

        $result = $this->publisher()->dispatch($post);

        $this->assertSame(2, $result['jobs']);
        $this->assertSame(2, SocialPublishJob::where('social_post_id', $post->id)->count());
        $this->assertSame(S::QUEUED, $post->fresh()->status);
    }

    public function test_successful_publish_marks_post_published_with_platform_ids(): void {
        $post = $this->post([S::TELEGRAM]);
        $this->telegram->script[] = PublishResult::make('msg-42', 'https://t.me/c/42');

        $this->publisher()->dispatch($post);
        $this->drain();

        $target = $post->fresh('targets')->targets->first();

        $this->assertSame(S::PUBLISHED, $target->status);
        $this->assertSame('msg-42', $target->platform_post_id);
        $this->assertSame('https://t.me/c/42', $target->platform_url);
        $this->assertSame(S::PUBLISHED, $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);
    }

    /* ------------------------------------------- Duplicate prevention */

    public function test_dispatching_twice_does_not_create_duplicate_jobs(): void {
        $post = $this->post([S::TELEGRAM]);

        $this->publisher()->dispatch($post);

        // The admin double-clicks "Publish"; the second call must be absorbed.
        try {
            $this->publisher()->dispatch($post->fresh('targets'));
        } catch (\RuntimeException $e) {
            // Rejecting the second dispatch outright is also a correct outcome.
        }

        $this->assertSame(1, SocialPublishJob::where('social_post_id', $post->id)->count());
    }

    public function test_double_click_publish_results_in_a_single_platform_call(): void {
        $post = $this->post([S::TELEGRAM]);

        $this->publisher()->dispatch($post);
        try {
            $this->publisher()->dispatch($post->fresh('targets'));
        } catch (\RuntimeException $e) {
            // Expected on the second attempt.
        }

        $this->drain();

        $this->assertSame(1, $this->telegram->publishCalls, 'The platform must be called exactly once.');
    }

    public function test_a_job_for_an_already_published_target_is_a_no_op(): void {
        $post = $this->post([S::TELEGRAM]);
        $this->publisher()->dispatch($post);
        $this->drain();

        $callsAfterFirst = $this->telegram->publishCalls;

        // Simulate a duplicated job reaching the runner after the target is live.
        $target = $post->fresh('targets')->targets->first();
        $job    = SocialPublishJob::create([
            'type'                    => S::JOB_PUBLISH,
            'social_post_id'          => $post->id,
            'social_post_platform_id' => $target->id,
            'social_account_id'       => $target->social_account_id,
            'platform'                => $target->platform,
            'status'                  => S::JOB_QUEUED,
            'idempotency_key'         => 'duplicate-job-test',
            'available_at'            => now(),
        ]);

        $this->publisher()->execute($job);

        $this->assertSame($callsAfterFirst, $this->telegram->publishCalls);
        $this->assertSame(S::JOB_COMPLETED, $job->fresh()->status);
    }

    /* --------------------------------------------- Partial publishing */

    public function test_one_platform_failing_never_marks_the_post_published(): void {
        $post = $this->post([S::TELEGRAM, S::FACEBOOK]);

        $this->telegram->script[] = PublishResult::make('tg-1');
        $this->facebook->script[] = new PlatformException(
            'Facebook rejected the request.', 'rejected', retryable: false
        );

        $this->publisher()->dispatch($post);
        $this->drain();

        $post = $post->fresh('targets');

        $this->assertSame(S::PARTIALLY_PUBLISHED, $post->status);
        $this->assertSame(S::PUBLISHED, $post->targets->firstWhere('platform', S::TELEGRAM)->status);
        $this->assertSame(S::FAILED, $post->targets->firstWhere('platform', S::FACEBOOK)->status);
    }

    public function test_failed_platform_records_a_readable_reason(): void {
        $post = $this->post([S::TELEGRAM]);
        $this->telegram->script[] = PlatformException::tokenExpired('telegram');

        $this->publisher()->dispatch($post);
        $this->drain();

        $target = $post->fresh('targets')->targets->first();

        $this->assertSame('token_expired', $target->error_code);
        $this->assertStringContainsString('Reconnect', $target->error_message);
    }

    public function test_credential_failure_flags_the_account_for_reconnection(): void {
        $post    = $this->post([S::TELEGRAM]);
        $account = $post->targets->first()->account;

        $this->telegram->script[] = PlatformException::tokenExpired('telegram');

        $this->publisher()->dispatch($post);
        $this->drain();

        $this->assertSame(S::ACCOUNT_TOKEN_EXPIRED, $account->fresh()->status);
    }

    /* ----------------------------------------------------------- Retry */

    public function test_transient_failure_is_retried_with_growing_backoff(): void {
        $post = $this->post([S::TELEGRAM]);

        $this->telegram->script[] = new PlatformException('Timeout.', 'network_error', retryable: true);
        $this->telegram->script[] = new PlatformException('Timeout.', 'network_error', retryable: true);
        $this->telegram->script[] = PublishResult::make('tg-eventually');

        $this->publisher()->dispatch($post);

        // Attempt 1 fails and schedules a retry.
        $this->drain();
        $target = $post->fresh('targets')->targets->first();
        $this->assertSame(S::RETRYING, $target->status);
        $this->assertNotNull($target->next_attempt_at);

        $firstDelay = now()->diffInSeconds($target->next_attempt_at);

        // Attempt 2 fails; the delay must be longer than after attempt 1.
        $this->forceDue();
        $this->drain();
        $target = $post->fresh('targets')->targets->first();
        $this->assertSame(S::RETRYING, $target->status);

        $secondDelay = now()->diffInSeconds($target->next_attempt_at);
        $this->assertGreaterThan($firstDelay, $secondDelay, 'Backoff must grow between attempts.');

        // Attempt 3 succeeds.
        $this->forceDue();
        $this->drain();

        $this->assertSame(S::PUBLISHED, $post->fresh('targets')->targets->first()->status);
        $this->assertSame(3, $this->telegram->publishCalls);
    }

    public function test_retries_stop_at_the_configured_maximum(): void {
        $post = $this->post([S::TELEGRAM]);

        for ($i = 0; $i < 5; $i++) {
            $this->telegram->script[] = new PlatformException('Timeout.', 'network_error', retryable: true);
        }

        $this->publisher()->dispatch($post);

        for ($i = 0; $i < 5; $i++) {
            $this->forceDue();
            $this->drain();
        }

        $target = $post->fresh('targets')->targets->first();

        $this->assertSame(S::FAILED, $target->status);
        $this->assertSame(3, $this->telegram->publishCalls, 'max_attempts is 3 in this suite.');
        $this->assertSame(S::JOB_FAILED, SocialPublishJob::where('social_post_id', $post->id)->first()->status);
    }

    public function test_permanent_failure_is_not_retried(): void {
        $post = $this->post([S::TELEGRAM]);

        $this->telegram->script[] = new PlatformException('Caption too long.', 'rejected', retryable: false);

        $this->publisher()->dispatch($post);
        $this->forceDue();
        $this->drain();
        $this->forceDue();
        $this->drain();

        $this->assertSame(1, $this->telegram->publishCalls, 'A rejected request must not be retried.');
        $this->assertSame(S::FAILED, $post->fresh('targets')->targets->first()->status);
    }

    public function test_every_attempt_is_recorded(): void {
        $post = $this->post([S::TELEGRAM]);

        $this->telegram->script[] = new PlatformException('Timeout.', 'network_error', retryable: true);
        $this->telegram->script[] = PublishResult::make('tg-2');

        $this->publisher()->dispatch($post);
        $this->drain();
        $this->forceDue();
        $this->drain();

        $target   = $post->fresh('targets')->targets->first();
        $attempts = SocialPublishAttempt::where('social_post_platform_id', $target->id)->orderBy('id')->get();

        $this->assertCount(2, $attempts);
        $this->assertSame('failed', $attempts[0]->status);
        $this->assertSame('success', $attempts[1]->status);
    }

    /* ----------------------------------- Unknown outcome / lost response */

    public function test_a_lost_response_adopts_the_existing_post_instead_of_republishing(): void {
        $post = $this->post([S::TELEGRAM]);

        // The first attempt reached the platform and created the post, but the
        // response never came back to us.
        $this->telegram->script[] = new PlatformException('Timeout.', 'network_error', retryable: true);
        $this->telegram->existingPublication = PublishResult::make('already-live-1', 'https://t.me/c/1');

        $this->publisher()->dispatch($post);
        $this->drain();

        $this->forceDue();
        $this->drain();

        $target = $post->fresh('targets')->targets->first();

        $this->assertSame(S::PUBLISHED, $target->status);
        $this->assertSame('already-live-1', $target->platform_post_id);
        $this->assertSame(1, $this->telegram->publishCalls, 'The second attempt must adopt, not re-post.');
        $this->assertSame(1, $this->telegram->verifyCalls);
    }

    public function test_verification_is_skipped_after_a_permanent_rejection(): void {
        $post = $this->post([S::TELEGRAM]);

        // A 400 never created anything, so there is nothing to look for.
        $this->telegram->script[] = new PlatformException('Bad request.', 'rejected', retryable: false);

        $this->publisher()->dispatch($post);
        $this->drain();

        $this->assertSame(0, $this->telegram->verifyCalls);
    }

    /* ------------------------------------------------------- Scheduling */

    public function test_a_scheduled_post_is_not_published_early(): void {
        $post = $this->post([S::TELEGRAM]);

        $this->publisher()->dispatch($post, now()->addHours(3));
        $this->drain();

        $this->assertSame(0, $this->telegram->publishCalls);
        $this->assertSame(S::SCHEDULED, $post->fresh()->status);
    }

    public function test_a_scheduled_post_publishes_once_its_time_arrives(): void {
        $post = $this->post([S::TELEGRAM]);

        // Schedule it, then close the browser: the runner is the only actor.
        $this->publisher()->dispatch($post, now()->addHours(3));
        $this->forceDue();
        $this->drain();

        $this->assertSame(1, $this->telegram->publishCalls);
        $this->assertSame(S::PUBLISHED, $post->fresh()->status);
    }

    public function test_cancelling_a_scheduled_post_stops_it_publishing(): void {
        $post = $this->post([S::TELEGRAM]);

        $this->publisher()->dispatch($post, now()->addHours(3));
        $this->publisher()->cancel($post->fresh('targets'));

        $this->forceDue();
        $this->drain();

        $this->assertSame(0, $this->telegram->publishCalls);
        $this->assertSame(S::CANCELLED, $post->fresh()->status);
    }

    /* ------------------------------------------------- Worker resilience */

    public function test_a_job_abandoned_by_a_crashed_worker_is_reclaimed(): void {
        $post = $this->post([S::TELEGRAM]);
        $this->publisher()->dispatch($post);

        // A worker claimed the job and then died before finishing.
        $job = SocialPublishJob::where('social_post_id', $post->id)->first();
        $job->update([
            'status'         => S::JOB_PROCESSING,
            'reserved_at'    => now()->subHours(2),
            'reserved_token' => 'dead-worker',
        ]);

        $this->drain();

        $this->assertSame(1, $this->telegram->publishCalls);
        $this->assertSame(S::PUBLISHED, $post->fresh()->status);
    }

    public function test_two_runners_do_not_process_the_same_job_twice(): void {
        $post = $this->post([S::TELEGRAM]);
        $this->publisher()->dispatch($post);

        $first  = SocialPublishJob::claim(SocialPublishJob::newToken(), 5, 600);
        $second = SocialPublishJob::claim(SocialPublishJob::newToken(), 5, 600);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second, 'A leased job must not be handed to a second runner.');
    }

    /* ----------------------------------------------------- State machine */

    public function test_invalid_state_transitions_are_rejected(): void {
        $post = $this->post([S::TELEGRAM]);
        $this->publisher()->dispatch($post);
        $this->drain();

        $post = $post->fresh();
        $this->assertSame(S::PUBLISHED, $post->status);

        $this->expectException(\RuntimeException::class);
        $post->transitionTo(S::DRAFT);
    }

    public function test_roll_up_reports_partial_rather_than_published(): void {
        $this->assertSame(
            S::PARTIALLY_PUBLISHED,
            PostStateMachine::rollUp([S::PUBLISHED, S::FAILED])
        );

        $this->assertSame(S::PUBLISHED, PostStateMachine::rollUp([S::PUBLISHED, S::PUBLISHED]));
        $this->assertSame(S::FAILED, PostStateMachine::rollUp([S::FAILED, S::FAILED]));

        // Still in flight while something is queued behind a success.
        $this->assertSame(S::PUBLISHING, PostStateMachine::rollUp([S::PUBLISHED, S::QUEUED]));
    }

    public function test_a_post_with_no_connected_account_fails_fast(): void {
        $post = $this->post([S::TELEGRAM]);

        $post->targets->first()->account->update(['status' => S::ACCOUNT_DISCONNECTED]);

        $this->expectException(\RuntimeException::class);
        $this->publisher()->dispatch($post->fresh('targets'));
    }

    /* --------------------------------------------------------- Helpers */

    /** Runs the queue the way cron would. */
    protected function drain(int $passes = 1): void {
        $runner = app(SocialQueueRunner::class);

        for ($i = 0; $i < $passes; $i++) {
            $runner->run(maxSeconds: 5, maxJobs: 10);
        }
    }

    /** Pulls every pending job's schedule into the past, as time passing would. */
    protected function forceDue(): void {
        SocialPublishJob::where('status', S::JOB_QUEUED)->update(['available_at' => now()->subMinute()]);
    }
}
