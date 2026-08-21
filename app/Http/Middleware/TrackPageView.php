<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsTrackingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a page view for public, human, GET HTML requests. Terminable: the
 * actual write (including any first-request-per-IP country lookup) happens in
 * terminate(), AFTER the response is flushed to the browser, so it adds no
 * user-facing latency. Admin/api/auth/ajax/asset requests are skipped.
 */
class TrackPageView {
    /** Request attribute keys used to pass state to terminate(). */
    private const TRACK_FLAG  = '_analytics_track';
    private const VISITOR_KEY = '_analytics_visitor';

    public function __construct(private AnalyticsTrackingService $analytics) {}

    public function handle(Request $request, Closure $next): Response {
        if ($this->shouldTrack($request)) {
            // Ensure the anonymous first-party visitor cookie exists, issuing a
            // fresh one on first visit. Queued now so it rides the response.
            $cookieName = config('analytics.visitor_cookie', 'qz_vid');
            $visitorId  = $request->cookie($cookieName);
            if (!$visitorId || !is_string($visitorId) || strlen($visitorId) > 40) {
                $visitorId = $this->analytics->newVisitorId();
                Cookie::queue(
                    $cookieName,
                    $visitorId,
                    60 * 24 * (int) config('analytics.visitor_cookie_days', 400),
                    '/', null, $request->secure(), true, false, 'lax'
                );
            }
            $request->attributes->set(self::TRACK_FLAG, true);
            $request->attributes->set(self::VISITOR_KEY, $visitorId);
        }

        return $next($request);
    }

    /** Runs after the response has been sent to the client. */
    public function terminate(Request $request, Response $response): void {
        if (!$request->attributes->get(self::TRACK_FLAG)) {
            return;
        }
        // Only count successful HTML responses (not redirects/JSON/downloads).
        if ($response->getStatusCode() !== 200) {
            return;
        }
        $type = (string) $response->headers->get('Content-Type');
        if ($type !== '' && !str_contains($type, 'text/html')) {
            return;
        }

        $this->analytics->trackPageView($request, $request->attributes->get(self::VISITOR_KEY));
    }

    /** Decides whether this request is a public page view worth recording. */
    private function shouldTrack(Request $request): bool {
        if (!config('analytics.enabled', true)) {
            return false;
        }
        if (!$request->isMethod('GET')) {
            return false;
        }
        // XHR / fetch / pjax navigations are not full page views.
        if ($request->ajax() || $request->pjax() || $request->wantsJson()) {
            return false;
        }
        // Excluded areas: admin, api, auth, tracking endpoint, discovery files…
        foreach ((array) config('analytics.exclude_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }
        return true;
    }
}
