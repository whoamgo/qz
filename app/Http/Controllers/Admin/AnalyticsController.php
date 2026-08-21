<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read + maintenance side of the analytics system. Every screen shares the same
 * date-range resolver so filters behave consistently. All aggregate queries are
 * scoped to status = 'valid' (only accepted events count) and driven by indexed
 * columns.
 */
class AnalyticsController extends Controller {

    /* ================================================================ dashboard */

    public function dashboard(Request $request) {
        $pageTitle = 'Analytics Dashboard';
        [$from, $to, $rangeKey, $rangeLabel] = $this->resolveRange($request);

        $valid = fn() => AnalyticsEvent::valid()->inRange($from, $to);

        // ---- Summary cards -------------------------------------------------
        $totalPageViews = (clone $valid())->ofType(AnalyticsEvent::TYPE_PAGE_VIEW)->count();
        $totalClicks    = (clone $valid())->ofType(AnalyticsEvent::TYPE_CLICK)->count();
        $uniqueVisitors = (int) (clone $valid())->distinct()
            ->count(DB::raw('COALESCE(visitor_id, session_id)'));

        $todayFrom = Carbon::today();
        $todayValid = fn() => AnalyticsEvent::valid()->where('created_at', '>=', $todayFrom);
        $todayPageViews = (clone $todayValid())->ofType(AnalyticsEvent::TYPE_PAGE_VIEW)->count();
        $todayClicks    = (clone $todayValid())->ofType(AnalyticsEvent::TYPE_CLICK)->count();

        $mostViewedPage = (clone $valid())->ofType(AnalyticsEvent::TYPE_PAGE_VIEW)
            ->select('page_path', DB::raw('COUNT(*) as c'))
            ->groupBy('page_path')->orderByDesc('c')->first();

        $mostClickedElement = (clone $valid())->ofType(AnalyticsEvent::TYPE_CLICK)
            ->whereNotNull('element_name')
            ->select('element_name', DB::raw('COUNT(*) as c'))
            ->groupBy('element_name')->orderByDesc('c')->first();

        $topCountry = (clone $valid())->whereNotNull('country_name')
            ->where('country_name', '!=', 'Unknown')
            ->select('country_name', DB::raw('COUNT(DISTINCT COALESCE(visitor_id, session_id)) as c'))
            ->groupBy('country_name')->orderByDesc('c')->first();

        $cards = [
            'total_page_views'  => $totalPageViews,
            'unique_visitors'   => $uniqueVisitors,
            'total_clicks'      => $totalClicks,
            'today_page_views'  => $todayPageViews,
            'today_clicks'      => $todayClicks,
            'most_viewed_page'  => $mostViewedPage?->page_path ?? '—',
            'most_clicked'      => $mostClickedElement?->element_name ?? '—',
            'top_country'       => $topCountry?->country_name ?? '—',
        ];

        // ---- Charts --------------------------------------------------------
        $charts = [
            'today_hourly'  => $this->hourlySeries(Carbon::today(), Carbon::now()),
            'last7_daily'   => $this->dailySeries(Carbon::today()->subDays(6), Carbon::now()),
            'countries'     => $this->countrySeries($from, $to, 10),
        ];

        return view('admin.analytics.dashboard', compact(
            'pageTitle', 'cards', 'charts', 'rangeKey', 'rangeLabel', 'from', 'to'
        ));
    }

    /* ============================================================== page table */

    public function pages(Request $request) {
        $pageTitle = 'Page Analytics';
        [$from, $to, $rangeKey, $rangeLabel] = $this->resolveRange($request);
        $search = trim((string) $request->get('search'));
        $sort   = $request->get('sort', 'views');

        $query = AnalyticsEvent::valid()->inRange($from, $to)
            ->ofType(AnalyticsEvent::TYPE_PAGE_VIEW)
            ->select(
                'page_path',
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors'),
                DB::raw('MAX(created_at) as last_visited')
            )
            ->when($search !== '', fn($q) => $q->where('page_path', 'like', "%{$search}%"))
            ->groupBy('page_path');

        $query = $this->applySort($query, $sort, [
            'views' => 'views', 'visitors' => 'visitors', 'last_visited' => 'last_visited', 'page' => 'page_path',
        ], 'views');

        $rows = $query->paginate(getPaginate())->withQueryString();

        // Supplement only the visible slice with click counts + top country.
        $paths = collect($rows->items())->pluck('page_path')->all();
        $clicksByPath = $this->countBy($from, $to, AnalyticsEvent::TYPE_CLICK, 'page_path', $paths);
        $topCountryByPath = $this->topCountryBy($from, $to, 'page_path', $paths);

        foreach ($rows as $row) {
            $row->clicks      = $clicksByPath[$row->page_path] ?? 0;
            $row->top_country = $topCountryByPath[$row->page_path] ?? '—';
        }

        return view('admin.analytics.pages', compact(
            'pageTitle', 'rows', 'rangeKey', 'rangeLabel', 'from', 'to', 'search', 'sort'
        ));
    }

    /* ============================================================= click table */

    public function clicks(Request $request) {
        $pageTitle = 'Click Analytics';
        [$from, $to, $rangeKey, $rangeLabel] = $this->resolveRange($request);
        $search = trim((string) $request->get('search'));
        $sort   = $request->get('sort', 'clicks');

        $query = AnalyticsEvent::valid()->inRange($from, $to)
            ->ofType(AnalyticsEvent::TYPE_CLICK)
            ->whereNotNull('element_name')
            ->select(
                'element_name', 'element_category', 'page_path',
                DB::raw('COUNT(*) as clicks'),
                DB::raw('COUNT(DISTINCT COALESCE(user_id, visitor_id, session_id)) as users'),
                DB::raw('MAX(created_at) as last_clicked')
            )
            ->when($search !== '', fn($q) => $q->where(function ($w) use ($search) {
                $w->where('element_name', 'like', "%{$search}%")
                  ->orWhere('element_category', 'like', "%{$search}%")
                  ->orWhere('page_path', 'like', "%{$search}%");
            }))
            ->groupBy('element_name', 'element_category', 'page_path');

        $query = $this->applySort($query, $sort, [
            'clicks' => 'clicks', 'users' => 'users', 'last_clicked' => 'last_clicked', 'element' => 'element_name',
        ], 'clicks');

        $rows = $query->paginate(getPaginate())->withQueryString();

        return view('admin.analytics.clicks', compact(
            'pageTitle', 'rows', 'rangeKey', 'rangeLabel', 'from', 'to', 'search', 'sort'
        ));
    }

    /* =========================================================== country table */

    public function countries(Request $request) {
        $pageTitle = 'Geography / Countries';
        [$from, $to, $rangeKey, $rangeLabel] = $this->resolveRange($request);

        $rows = AnalyticsEvent::valid()->inRange($from, $to)
            ->select(
                'country_name', 'country_code',
                DB::raw('COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors'),
                DB::raw("SUM(event_type = '" . AnalyticsEvent::TYPE_PAGE_VIEW . "') as views"),
                DB::raw("SUM(event_type = '" . AnalyticsEvent::TYPE_CLICK . "') as clicks")
            )
            ->groupBy('country_name', 'country_code')
            ->orderByDesc('visitors')
            ->paginate(getPaginate())->withQueryString();

        return view('admin.analytics.countries', compact(
            'pageTitle', 'rows', 'rangeKey', 'rangeLabel', 'from', 'to'
        ));
    }

    /* ============================================================= raw events */

    public function events(Request $request) {
        $pageTitle = 'Analytics Events (Raw)';
        [$from, $to, $rangeKey, $rangeLabel] = $this->resolveRange($request);
        $status = $request->get('status');
        $type   = $request->get('type');

        $rows = AnalyticsEvent::with('user:id,username')
            ->inRange($from, $to)
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($type, fn($q) => $q->where('event_type', $type))
            ->orderByDesc('id')
            ->paginate(getPaginate())->withQueryString();

        $statuses = [AnalyticsEvent::STATUS_VALID, AnalyticsEvent::STATUS_DUPLICATE,
                     AnalyticsEvent::STATUS_RATE_LIMITED, AnalyticsEvent::STATUS_BOT];

        return view('admin.analytics.events', compact(
            'pageTitle', 'rows', 'rangeKey', 'rangeLabel', 'from', 'to', 'status', 'type', 'statuses'
        ));
    }

    /* ================================================================== clear */

    public function clear(Request $request) {
        $request->validate(['range' => 'required|in:today,last_7_days,last_30_days,all']);

        $q = AnalyticsEvent::query();
        switch ($request->range) {
            case 'today':        $q->where('created_at', '>=', Carbon::today()); break;
            case 'last_7_days':  $q->where('created_at', '>=', Carbon::today()->subDays(6)); break;
            case 'last_30_days': $q->where('created_at', '>=', Carbon::today()->subDays(29)); break;
            case 'all':          /* no filter — everything */ break;
        }

        $deleted = $q->delete();

        $notify[] = ['success', "Analytics cleared ({$request->range}). {$deleted} record(s) removed."];
        return back()->withNotify($notify);
    }

    /* =============================================================== internals */

    /** Resolves a filter into [Carbon $from, Carbon $to, string $key, string $label]. */
    private function resolveRange(Request $request): array {
        $key = $request->get('range', 'last_7_days');
        $now = Carbon::now();

        return match ($key) {
            'today'        => [Carbon::today(), $now, 'today', 'Today'],
            'yesterday'    => [Carbon::yesterday(), Carbon::yesterday()->endOfDay(), 'yesterday', 'Yesterday'],
            'last_30_days' => [Carbon::today()->subDays(29), $now, 'last_30_days', 'Last 30 Days'],
            'this_month'   => [Carbon::now()->startOfMonth(), $now, 'this_month', 'This Month'],
            'custom'       => $this->customRange($request),
            default        => [Carbon::today()->subDays(6), $now, 'last_7_days', 'Last 7 Days'],
        };
    }

    private function customRange(Request $request): array {
        $from = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : Carbon::today()->subDays(6);
        $to   = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : Carbon::now();
        if ($to->lt($from)) { [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()]; }
        return [$from, $to, 'custom', $from->toDateString() . ' → ' . $to->toDateString()];
    }

    /** Applies a whitelisted sort column (prevents SQL injection via ?sort=). */
    private function applySort($query, ?string $sort, array $allowed, string $default) {
        $col = $allowed[$sort] ?? $allowed[$default];
        return $query->orderByDesc($col);
    }

    /** 24-bucket hourly series (page views + clicks) for a single day. */
    private function hourlySeries(Carbon $from, Carbon $to): array {
        $views  = $this->groupedCounts(AnalyticsEvent::TYPE_PAGE_VIEW, $from, $to, 'HOUR(created_at)');
        $clicks = $this->groupedCounts(AnalyticsEvent::TYPE_CLICK, $from, $to, 'HOUR(created_at)');
        $labels = [];
        $v = []; $c = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02d:00', $h);
            $v[] = (int) ($views[$h] ?? 0);
            $c[] = (int) ($clicks[$h] ?? 0);
        }
        return ['labels' => $labels, 'views' => $v, 'clicks' => $c];
    }

    /** Daily series (page views + clicks) between two dates, gap-filled. */
    private function dailySeries(Carbon $from, Carbon $to): array {
        $views  = $this->groupedCounts(AnalyticsEvent::TYPE_PAGE_VIEW, $from, $to, 'DATE(created_at)');
        $clicks = $this->groupedCounts(AnalyticsEvent::TYPE_CLICK, $from, $to, 'DATE(created_at)');
        $labels = []; $v = []; $c = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $labels[] = $d->format('d M');
            $v[] = (int) ($views[$key] ?? 0);
            $c[] = (int) ($clicks[$key] ?? 0);
        }
        return ['labels' => $labels, 'views' => $v, 'clicks' => $c];
    }

    /** [bucket => count] of valid events of a type grouped by a raw expression. */
    private function groupedCounts(string $type, Carbon $from, Carbon $to, string $rawGroup): array {
        return AnalyticsEvent::valid()->ofType($type)->inRange($from, $to)
            ->select(DB::raw("$rawGroup as bucket"), DB::raw('COUNT(*) as c'))
            ->groupBy(DB::raw($rawGroup))
            ->pluck('c', 'bucket')->all();
    }

    /** Top-N countries by unique visitors for the country chart. */
    private function countrySeries(Carbon $from, Carbon $to, int $limit): array {
        $rows = AnalyticsEvent::valid()->inRange($from, $to)
            ->whereNotNull('country_name')
            ->select('country_name', DB::raw('COUNT(DISTINCT COALESCE(visitor_id, session_id)) as c'))
            ->groupBy('country_name')->orderByDesc('c')->limit($limit)->get();
        return ['labels' => $rows->pluck('country_name')->all(), 'data' => $rows->pluck('c')->map(fn($n) => (int) $n)->all()];
    }

    /** valid event counts of a type keyed by a column, restricted to given keys. */
    private function countBy(Carbon $from, Carbon $to, string $type, string $column, array $keys): array {
        if (empty($keys)) return [];
        return AnalyticsEvent::valid()->ofType($type)->inRange($from, $to)
            ->whereIn($column, $keys)
            ->select($column, DB::raw('COUNT(*) as c'))
            ->groupBy($column)->pluck('c', $column)->all();
    }

    /** Top country name per grouping key (valid events), for the visible slice. */
    private function topCountryBy(Carbon $from, Carbon $to, string $column, array $keys): array {
        if (empty($keys)) return [];
        $rows = AnalyticsEvent::valid()->inRange($from, $to)
            ->whereIn($column, $keys)
            ->whereNotNull('country_name')->where('country_name', '!=', 'Unknown')
            ->select($column, 'country_name', DB::raw('COUNT(*) as c'))
            ->groupBy($column, 'country_name')
            ->orderByDesc('c')->get();

        $out = [];
        foreach ($rows as $r) {
            if (!isset($out[$r->{$column}])) { $out[$r->{$column}] = $r->country_name; }
        }
        return $out;
    }
}
