<?php

namespace App\Services\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialAnalytic;
use App\Models\Social\SocialPostPlatform;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pulls metrics from the platforms and answers the dashboard's questions.
 *
 * Everything reported here comes from a real API response. Where a platform
 * exposes no metric (Telegram post views, WhatsApp delivery), the value stays
 * null and the UI shows a dash - it never shows a zero that reads like data.
 */
class SocialAnalyticsService {

    public function __construct(protected PlatformRegistry $registry) {}

    /* --------------------------------------------------------------- Sync */

    /** Syncs every connected account plus its recently published posts. */
    public function syncAll(): array {
        $stats = ['accounts' => 0, 'posts' => 0, 'errors' => 0];

        foreach (SocialAccount::connected()->get() as $account) {
            try {
                $this->syncAccount($account);
                $stats['accounts']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        $stats['posts'] = $this->syncRecentPosts();

        return $stats;
    }

    /** Today's account-level snapshot. */
    public function syncAccount(?SocialAccount $account): bool {
        if (!$account || !$account->isPublishable()) {
            return false;
        }

        $adapter = $this->registry->make($account->platform);
        $date    = now()->toDateString();

        $metrics = $adapter->fetchAccountMetrics($account, $date);
        if ($metrics === null) {
            return false;
        }

        // Yesterday's snapshot gives the growth number the charts plot.
        $previous = SocialAnalytic::where('social_account_id', $account->id)
            ->where('date', '<', $date)
            ->orderByDesc('date')
            ->first();

        $followers = $metrics['followers'] ?? null;

        SocialAnalytic::updateOrCreate(
            ['social_account_id' => $account->id, 'date' => $date],
            array_filter([
                'platform'        => $account->platform,
                'followers'       => $followers,
                'followers_delta' => ($followers !== null && $previous?->followers !== null)
                    ? $followers - $previous->followers
                    : null,
                'impressions'     => $metrics['impressions'] ?? null,
                'reach'           => $metrics['reach'] ?? null,
                'views'           => $metrics['views'] ?? null,
                'likes'           => $metrics['likes'] ?? null,
                'comments'        => $metrics['comments'] ?? null,
                'shares'          => $metrics['shares'] ?? null,
                'clicks'          => $metrics['clicks'] ?? null,
                'posts_published' => SocialPostPlatform::where('social_account_id', $account->id)
                    ->whereDate('published_at', $date)->count(),
                'raw'             => $metrics,
            ], fn ($v) => $v !== null)
        );

        if ($followers !== null) {
            $account->followers    = $followers;
            $account->last_sync_at = now();
            $account->save();
        }

        return true;
    }

    /**
     * Refreshes per-post metrics.
     *
     * Only recent posts are polled - engagement on a month-old post barely
     * moves, and platform quotas are better spent on what is still live.
     */
    public function syncRecentPosts(int $days = 30, int $limit = 100): int {
        $targets = SocialPostPlatform::with('account')
            ->where('status', S::PUBLISHED)
            ->whereNotNull('platform_post_id')
            ->where('published_at', '>=', now()->subDays($days))
            ->where(function ($q) {
                $q->whereNull('metrics_synced_at')->orWhere('metrics_synced_at', '<', now()->subHours(6));
            })
            ->orderBy('metrics_synced_at')
            ->limit($limit)
            ->get();

        $synced = 0;

        foreach ($targets as $target) {
            if (!$target->account || !$target->account->isPublishable()) {
                continue;
            }

            try {
                $metrics = $this->registry->make($target->platform)->fetchPostMetrics($target->account, $target);

                // Stamp the timestamp either way, so a platform with no metrics
                // is not re-polled every six hours forever.
                $target->metrics_synced_at = now();
                if ($metrics) {
                    $target->metrics = array_merge($target->metrics ?? [], $metrics);
                    $synced++;
                }
                $target->save();
            } catch (\Throwable $e) {
                $target->metrics_synced_at = now();
                $target->save();
            }
        }

        return $synced;
    }

    /* ------------------------------------------------------------ Reporting */

    /** Headline numbers for the dashboard, over a date range. */
    public function summary(Carbon $from, Carbon $to): array {
        $targets = SocialPostPlatform::where('status', S::PUBLISHED)
            ->whereBetween('published_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();

        $sum = fn (string $key) => $targets->sum(fn ($t) => (int) $t->metric($key, 0));

        $likes    = $sum('likes');
        $comments = $sum('comments');
        $shares   = $sum('shares');
        $saves    = $sum('saves');
        $views    = $sum('views');
        $reach    = $sum('reach') ?: $sum('impressions');

        $engagements = $likes + $comments + $shares + $saves;

        return [
            'posts'           => $targets->count(),
            'reach'           => $reach,
            'views'           => $views,
            'likes'           => $likes,
            'comments'        => $comments,
            'shares'          => $shares,
            'saves'           => $saves,
            'clicks'          => $sum('clicks'),
            'engagements'     => $engagements,
            // Engagement rate is meaningless without a denominator, so it is
            // null rather than 0 when no reach data came back.
            'engagement_rate' => $reach > 0 ? round(($engagements / $reach) * 100, 2) : null,
            'followers'       => SocialAccount::connected()->sum('followers'),
        ];
    }

    /** Per-platform breakdown for the comparison table and charts. */
    public function byPlatform(Carbon $from, Carbon $to): array {
        $rows = SocialPostPlatform::select('platform')
            ->where('status', S::PUBLISHED)
            ->whereBetween('published_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get()
            ->groupBy('platform');

        $out = [];

        foreach (S::PLATFORMS as $platform => $name) {
            $targets = $rows->get($platform, collect());
            $sum     = fn (string $key) => $targets->sum(fn ($t) => (int) $t->metric($key, 0));

            $reach       = $sum('reach') ?: $sum('impressions');
            $engagements = $sum('likes') + $sum('comments') + $sum('shares') + $sum('saves');

            $out[$platform] = [
                'platform'        => $platform,
                'name'            => $name,
                'icon'            => S::platformIcon($platform),
                'color'           => S::platformColor($platform),
                'posts'           => $targets->count(),
                'reach'           => $reach,
                'views'           => $sum('views'),
                'likes'           => $sum('likes'),
                'comments'        => $sum('comments'),
                'shares'          => $sum('shares'),
                'clicks'          => $sum('clicks'),
                'engagements'     => $engagements,
                'engagement_rate' => $reach > 0 ? round(($engagements / $reach) * 100, 2) : null,
                'followers'       => (int) SocialAccount::platform($platform)->connected()->sum('followers'),
            ];
        }

        return $out;
    }

    /** Daily series for the trend charts. */
    public function timeline(Carbon $from, Carbon $to): array {
        $rows = SocialAnalytic::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select('date')
            ->selectRaw('SUM(followers) as followers')
            ->selectRaw('SUM(COALESCE(reach, impressions, 0)) as reach')
            ->selectRaw('SUM(COALESCE(views, 0)) as views')
            ->selectRaw('SUM(COALESCE(likes,0) + COALESCE(comments,0) + COALESCE(shares,0)) as engagements')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $publishedByDay = SocialPostPlatform::where('status', S::PUBLISHED)
            ->whereBetween('published_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(published_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $series = ['followers' => [], 'reach' => [], 'views' => [], 'engagements' => [], 'posts' => []];

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $key      = $date->toDateString();
            $labels[] = $date->format('d M');
            $row      = $rows->get($key);

            $series['followers'][]   = (int) ($row->followers ?? 0);
            $series['reach'][]       = (int) ($row->reach ?? 0);
            $series['views'][]       = (int) ($row->views ?? 0);
            $series['engagements'][] = (int) ($row->engagements ?? 0);
            $series['posts'][]       = (int) ($publishedByDay[$key] ?? 0);
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * Best-performing published content.
     *
     * Sorting happens in PHP because the metrics live in a JSON column and
     * ordering by a JSON path is not portable across MySQL and SQLite.
     */
    public function topContent(string $sortBy = 'views', int $limit = 20, ?string $platform = null, ?Carbon $from = null): array {
        $query = SocialPostPlatform::with('post')
            ->where('status', S::PUBLISHED)
            ->whereNotNull('metrics');

        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($from) {
            $query->where('published_at', '>=', $from);
        }

        return $query->orderByDesc('published_at')
            ->limit(500)
            ->get()
            ->map(function (SocialPostPlatform $target) {
                $likes    = (int) $target->metric('likes', 0);
                $comments = (int) $target->metric('comments', 0);
                $shares   = (int) $target->metric('shares', 0);
                $saves    = (int) $target->metric('saves', 0);
                $reach    = (int) ($target->metric('reach', 0) ?: $target->metric('impressions', 0));

                return [
                    'target'          => $target,
                    'views'           => (int) $target->metric('views', 0),
                    'likes'           => $likes,
                    'comments'        => $comments,
                    'shares'          => $shares,
                    'clicks'          => (int) $target->metric('clicks', 0),
                    'engagement'      => $likes + $comments + $shares + $saves,
                    'engagement_rate' => $reach > 0 ? round((($likes + $comments + $shares + $saves) / $reach) * 100, 2) : 0,
                ];
            })
            ->sortByDesc($sortBy)
            ->take($limit)
            ->values()
            ->all();
    }

    /** Counts for the dashboard KPI cards. */
    public function dashboardCounts(): array {
        $today = now()->toDateString();

        return [
            'published_today' => SocialPostPlatform::where('status', S::PUBLISHED)
                ->whereDate('published_at', $today)->count(),
            'scheduled'       => DB::table('social_posts')->whereNull('deleted_at')
                ->where('status', S::SCHEDULED)->count(),
            'drafts'          => DB::table('social_posts')->whereNull('deleted_at')
                ->whereIn('status', [S::DRAFT, S::PENDING_APPROVAL])->count(),
            'failed'          => SocialPostPlatform::where('status', S::FAILED)->count(),
            'partial'         => DB::table('social_posts')->whereNull('deleted_at')
                ->where('status', S::PARTIALLY_PUBLISHED)->count(),
            'accounts_ok'     => SocialAccount::connected()->count(),
            'accounts_bad'    => SocialAccount::whereIn('status', [
                S::ACCOUNT_TOKEN_EXPIRED, S::ACCOUNT_PERMISSION_ISSUE, S::ACCOUNT_ERROR,
            ])->count(),
        ];
    }
}
