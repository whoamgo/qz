<?php

namespace App\Services\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialPublishJob;
use App\Models\Social\SocialSetting;
use Illuminate\Support\Facades\Log;

/**
 * Drains the publishing queue.
 *
 * Invoked three ways, all of which are safe to overlap because jobs are claimed
 * with a lease:
 *
 *   - the existing cron system (CronController), for shared hosting
 *   - `php artisan social:work`, for a real scheduler or supervisor
 *   - a manual "Process queue now" button in the publishing queue screen
 *
 * A time budget keeps the runner inside PHP's execution limit: it stops
 * claiming new work when the budget is spent, and the next run continues.
 */
class SocialQueueRunner {

    public function __construct(
        protected SocialPublisher $publisher,
        protected PlatformRegistry $registry,
    ) {}

    /**
     * @param int $maxSeconds soft budget; a job already running is allowed to finish.
     * @param int $maxJobs    hard cap on jobs per invocation.
     * @return array{processed:int,published:int,failed:int,retrying:int,skipped:int}
     */
    public function run(int $maxSeconds = 240, int $maxJobs = 25): array {
        $settings = SocialSetting::config();
        $stats    = ['processed' => 0, 'published' => 0, 'failed' => 0, 'retrying' => 0, 'skipped' => 0];

        if (!$settings->enabled) {
            return $stats;
        }

        $deadline = microtime(true) + $maxSeconds;
        $token    = SocialPublishJob::newToken();

        $this->refreshExpiringTokens();

        while (microtime(true) < $deadline && $stats['processed'] < $maxJobs) {
            $jobs = SocialPublishJob::claim($token, 3, (int) $settings->job_lease_seconds);

            if ($jobs->isEmpty()) {
                break;
            }

            foreach ($jobs as $job) {
                if (microtime(true) >= $deadline) {
                    // Budget spent mid-batch: hand the untouched jobs back so
                    // the next run picks them up immediately.
                    $job->release(now());
                    $stats['skipped']++;
                    continue;
                }

                $stats['processed']++;

                try {
                    $finished = match ($job->type) {
                        S::JOB_SYNC_ANALYTICS => $this->runAnalyticsJob($job),
                        S::JOB_REFRESH_TOKEN  => $this->runTokenJob($job),
                        S::JOB_SYNC_INBOX     => $this->runInboxJob($job),
                        default               => $this->publisher->execute($job),
                    };

                    $job->refresh();

                    if ($job->status === S::JOB_COMPLETED) {
                        $stats['published']++;
                    } elseif ($job->status === S::JOB_FAILED) {
                        $stats['failed']++;
                    } elseif (!$finished) {
                        $stats['retrying']++;
                    }
                } catch (\Throwable $e) {
                    // A job that throws out of execute() is a bug, not a
                    // platform failure - record it and move on rather than
                    // stalling the whole queue.
                    Log::error('[social] job crashed', ['job' => $job->id, 'error' => $e->getMessage()]);
                    $job->fail('The publishing worker hit an unexpected error: ' . $e->getMessage());
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Refreshes tokens that are close to expiring.
     *
     * Done ahead of the publish loop so a scheduled post does not fail on an
     * expiry that was foreseeable an hour earlier.
     */
    public function refreshExpiringTokens(int $withinMinutes = 60): int {
        $accounts = SocialAccount::query()
            ->whereIn('status', [S::ACCOUNT_CONNECTED, S::ACCOUNT_TOKEN_EXPIRED])
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addMinutes($withinMinutes))
            ->get();

        $refreshed = 0;

        foreach ($accounts as $account) {
            try {
                $adapter = $this->registry->make($account->platform);

                if ($adapter->refreshToken($account)) {
                    $refreshed++;
                    SocialAuditLogger::forModel('account.token_refresh', $account, [
                        'platform'    => $account->platform,
                        'description' => 'Refreshed the access token for ' . $account->display_name,
                    ]);
                    continue;
                }

                // No refresh mechanism and already past expiry: mark it so the
                // accounts screen tells the admin to reconnect.
                if ($account->tokenExpired(0) && $account->status !== S::ACCOUNT_TOKEN_EXPIRED) {
                    $account->markProblem(
                        S::ACCOUNT_TOKEN_EXPIRED,
                        S::platformName($account->platform) . ' access token expired. Reconnect the account.'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[social] token refresh failed', [
                    'account'  => $account->id,
                    'platform' => $account->platform,
                ]);
            }
        }

        return $refreshed;
    }

    protected function runAnalyticsJob(SocialPublishJob $job): bool {
        app(SocialAnalyticsService::class)->syncAccount($job->account);
        $job->complete();
        return true;
    }

    protected function runTokenJob(SocialPublishJob $job): bool {
        if ($job->account) {
            $this->registry->make($job->account->platform)->refreshToken($job->account);
        }
        $job->complete();
        return true;
    }

    protected function runInboxJob(SocialPublishJob $job): bool {
        if ($job->account) {
            app(SocialInboxService::class)->syncAccount($job->account);
        }
        $job->complete();
        return true;
    }

    /** Queue depth and health, for the dashboard and the queue screen. */
    public function stats(): array {
        return [
            'queued'     => SocialPublishJob::where('status', S::JOB_QUEUED)->count(),
            'due'        => SocialPublishJob::due()->count(),
            'processing' => SocialPublishJob::where('status', S::JOB_PROCESSING)->count(),
            'failed'     => SocialPublishJob::where('status', S::JOB_FAILED)->count(),
            'completed'  => SocialPublishJob::where('status', S::JOB_COMPLETED)->count(),
            'stuck'      => SocialPublishJob::where('status', S::JOB_PROCESSING)
                ->where('reserved_at', '<', now()->subSeconds((int) SocialSetting::config()->job_lease_seconds))
                ->count(),
        ];
    }
}
