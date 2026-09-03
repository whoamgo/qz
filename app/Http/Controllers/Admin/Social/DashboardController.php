<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialPost;
use App\Models\Social\SocialPostPlatform;
use App\Services\Social\PlatformRegistry;
use App\Services\Social\SocialAnalyticsService;
use App\Services\Social\SocialPermission;
use App\Services\Social\SocialQueueRunner;
use Illuminate\Http\Request;

class DashboardController extends SocialBaseController {

    public function index(SocialAnalyticsService $analytics, SocialQueueRunner $runner, PlatformRegistry $registry) {
        $pageTitle = 'Social Media Dashboard';

        $from = now()->subDays(29)->startOfDay();
        $to   = now()->endOfDay();

        $counts  = $analytics->dashboardCounts();
        $summary = $analytics->summary($from, $to);
        $byPlatform = $analytics->byPlatform($from, $to);

        // One card per platform, whether or not it is connected - a missing
        // account is information, not something to hide.
        $accounts = SocialAccount::orderByDesc('is_default')->get()->groupBy('platform');

        $platformCards = collect(S::PLATFORMS)->map(function ($name, $key) use ($accounts, $byPlatform, $registry) {
            $account = $accounts->get($key, collect())->first();

            return [
                'key'         => $key,
                'name'        => $name,
                'icon'        => S::platformIcon($key),
                'color'       => S::platformColor($key),
                'configured'  => $registry->make($key)->isConfigured(),
                'account'     => $account,
                'performance' => $byPlatform[$key] ?? null,
                'total'       => $accounts->get($key, collect())->count(),
            ];
        })->values();

        $recentPosts = SocialPost::with('targets')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $upcoming = SocialPost::with('targets')
            ->whereIn('status', [S::SCHEDULED, S::QUEUED, S::APPROVED])
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get();

        $recentFailures = SocialPostPlatform::with('post')
            ->where('status', S::FAILED)
            ->orderByDesc('last_attempt_at')
            ->limit(5)
            ->get();

        $queue    = $runner->stats();
        $timeline = $analytics->timeline($from, $to);

        $pendingApproval = SocialPost::where('status', S::PENDING_APPROVAL)->count();

        return view('admin.social.dashboard', compact(
            'pageTitle', 'counts', 'summary', 'platformCards', 'recentPosts',
            'upcoming', 'recentFailures', 'queue', 'timeline', 'byPlatform', 'pendingApproval'
        ));
    }

    /** Polled by the queue widget so the dashboard reflects live progress. */
    public function queueStatus(SocialQueueRunner $runner) {
        return response()->json([
            'queue'  => $runner->stats(),
            'counts' => app(SocialAnalyticsService::class)->dashboardCounts(),
        ]);
    }

    /**
     * Runs the queue on demand.
     *
     * Useful on hosts where cron is coarse, and as a way to prove the pipeline
     * works without waiting. Publishing rights are required - this really does
     * publish.
     */
    public function processQueue(Request $request, SocialQueueRunner $runner) {
        $this->can(SocialPermission::PUBLISH);

        $stats = $runner->run(maxSeconds: 25, maxJobs: 10);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'stats' => $stats]);
        }

        $notify[] = $stats['processed']
            ? ['success', "Processed {$stats['processed']} job(s): {$stats['published']} published, {$stats['failed']} failed."]
            : ['info', 'Nothing is currently due for publishing.'];

        return back()->withNotify($notify);
    }
}
