<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoint that receives click / business events from the frontend
 * tracker (assets/web/js/analytics-track.js). Protected by CSRF (web group)
 * and a request throttle. Security-sensitive fields (user id, ip, session,
 * timestamp) are resolved server-side and never trusted from the payload.
 */
class AnalyticsTrackController extends Controller {
    public function __construct(private AnalyticsTrackingService $analytics) {}

    public function store(Request $request): JsonResponse {
        if (!config('analytics.enabled', true)) {
            return response()->json(['status' => 'disabled'], 202);
        }

        $allowed = (array) config('analytics.event_types', ['click']);

        $data = $request->validate([
            'event_type'       => ['required', 'string', 'in:' . implode(',', $allowed)],
            'element_name'     => ['nullable', 'string', 'max:190'],
            'element_category' => ['nullable', 'string', 'max:100'],
            'element_id'       => ['nullable', 'string', 'max:190'],
            'element_type'     => ['nullable', 'string', 'max:50'],
            'page_path'        => ['nullable', 'string', 'max:2000'],
            'page_url'         => ['nullable', 'string', 'max:2000'],
            'page_title'       => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->analytics->trackEvent($data['event_type'], $data, $request);

        // Always 202: the client does not need (and should not learn) whether an
        // event counted — that would leak the dedup/rate-limit thresholds.
        return response()->json(['status' => 'accepted'], 202);
    }
}
