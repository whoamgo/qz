<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialPublishJob;
use App\Models\Social\SocialSetting;
use App\Services\Social\SocialPermission;
use App\Services\Social\SocialQueueRunner;
use Illuminate\Http\Request;

class QueueController extends SocialBaseController {

    public function index(Request $request, SocialQueueRunner $runner) {
        $pageTitle = 'Publishing Queue';

        $query = SocialPublishJob::with(['post', 'target', 'account'])->latest('id');

        if ($status = $request->status) {
            $query->where('status', $status);
        }
        if ($platform = $request->platform) {
            $query->where('platform', $platform);
        }

        $jobs  = $query->paginate(getPaginate())->withQueryString();
        $stats = $runner->stats();

        // Whether background processing is actually wired up on this host.
        $lastCron = gs('last_cron');
        $cronHealthy = $lastCron && \Carbon\Carbon::parse($lastCron)->gt(now()->subMinutes(15));

        return view('admin.social.queue.index', compact('pageTitle', 'jobs', 'stats', 'cronHealthy', 'lastCron'));
    }

    public function run(SocialQueueRunner $runner) {
        $this->can(SocialPermission::PUBLISH);

        $stats = $runner->run(maxSeconds: 25, maxJobs: 10);

        $notify[] = $stats['processed']
            ? ['success', "Processed {$stats['processed']} job(s): {$stats['published']} published, {$stats['failed']} failed, {$stats['retrying']} awaiting retry."]
            : ['info', 'Nothing is currently due.'];

        return back()->withNotify($notify);
    }

    public function cancel(SocialPublishJob $job) {
        $this->can(SocialPermission::SCHEDULE);

        if ($job->status === S::JOB_PROCESSING) {
            $notify[] = ['error', 'This job is running right now and cannot be cancelled mid-flight.'];
            return back()->withNotify($notify);
        }

        $job->update(['status' => S::JOB_CANCELLED, 'completed_at' => now()]);

        if ($job->target && !in_array($job->target->status, [S::PUBLISHED], true)) {
            $job->target->update(['status' => S::CANCELLED]);
            $job->post?->syncStatusFromTargets();
        }

        $notify[] = ['success', 'Job cancelled.'];
        return back()->withNotify($notify);
    }

    public function retry(SocialPublishJob $job) {
        $this->can(SocialPermission::RETRY);

        if (!$job->target) {
            $notify[] = ['error', 'The post this job belonged to no longer exists.'];
            return back()->withNotify($notify);
        }

        try {
            app(\App\Services\Social\SocialPublisher::class)->retry($job->target);
            $notify[] = ['success', 'Queued for another attempt.'];
        } catch (\Throwable $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    /**
     * Frees jobs whose worker died mid-run.
     *
     * The runner reclaims expired leases automatically; this is the manual
     * override for when someone wants it to happen now.
     */
    public function releaseStuck() {
        $this->can(SocialPermission::PUBLISH);

        $lease = (int) SocialSetting::config()->job_lease_seconds;

        $released = SocialPublishJob::where('status', S::JOB_PROCESSING)
            ->where('reserved_at', '<', now()->subSeconds($lease))
            ->update([
                'status'         => S::JOB_QUEUED,
                'reserved_at'    => null,
                'reserved_token' => null,
                'available_at'   => now(),
            ]);

        $notify[] = $released
            ? ['success', "Released $released stuck job(s) back to the queue."]
            : ['info', 'No stuck jobs were found.'];

        return back()->withNotify($notify);
    }
}
