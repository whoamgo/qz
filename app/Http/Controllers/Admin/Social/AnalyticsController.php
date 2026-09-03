<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialAccount;
use App\Services\Social\SocialAnalyticsService;
use App\Services\Social\SocialPermission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsController extends SocialBaseController {

    const RANGES = [
        'today' => 'Today',
        '7'     => 'Last 7 days',
        '30'    => 'Last 30 days',
        '90'    => 'Last 90 days',
        'custom'=> 'Custom range',
    ];

    public function index(Request $request, SocialAnalyticsService $analytics) {
        $pageTitle = 'Social Analytics';

        [$from, $to, $range] = $this->range($request);

        $summary    = $analytics->summary($from, $to);
        $byPlatform = $analytics->byPlatform($from, $to);
        $timeline   = $analytics->timeline($from, $to);

        $accounts  = SocialAccount::connected()->orderByDesc('followers')->get();
        $lastSync  = SocialAccount::connected()->max('last_sync_at');

        // Platforms that expose no metrics at all get an explicit note rather
        // than a row of zeroes that reads like poor performance.
        $noMetrics = collect(S::PLATFORMS)->keys()->filter(
            fn ($p) => !\App\Services\Social\PlatformCapability::supports($p, 'analytics')
        )->values();

        return view('admin.social.analytics.index', compact(
            'pageTitle', 'summary', 'byPlatform', 'timeline', 'accounts',
            'from', 'to', 'range', 'lastSync', 'noMetrics'
        ));
    }

    public function topContent(Request $request, SocialAnalyticsService $analytics) {
        $pageTitle = 'Top Content';

        $request->validate([
            'sort'     => ['nullable', Rule::in(['views', 'likes', 'comments', 'shares', 'clicks', 'engagement', 'engagement_rate'])],
            'platform' => ['nullable', Rule::in(array_keys(S::PLATFORMS))],
        ]);

        $sort  = $request->input('sort', 'views');
        [$from] = $this->range($request);

        $items = $analytics->topContent($sort, 25, $request->platform, $from);

        return view('admin.social.analytics.top', compact('pageTitle', 'items', 'sort'));
    }

    public function sync(SocialAnalyticsService $analytics) {
        $this->can(SocialPermission::VIEW);

        $stats = $analytics->syncAll();

        $notify[] = ['success', "Synced {$stats['accounts']} account(s) and refreshed metrics on {$stats['posts']} post(s)."];
        if ($stats['errors']) {
            $notify[] = ['warning', "{$stats['errors']} account(s) could not be reached."];
        }

        return back()->withNotify($notify);
    }

    /** Resolves the range selector into concrete dates. */
    protected function range(Request $request): array {
        $range = $request->input('range', '30');

        if ($range === 'custom' && $request->from && $request->to) {
            return [
                Carbon::parse($request->from)->startOfDay(),
                Carbon::parse($request->to)->endOfDay(),
                'custom',
            ];
        }

        if ($range === 'today') {
            return [now()->startOfDay(), now()->endOfDay(), 'today'];
        }

        $days = in_array($range, ['7', '30', '90'], true) ? (int) $range : 30;

        return [now()->subDays($days - 1)->startOfDay(), now()->endOfDay(), (string) $days];
    }
}
