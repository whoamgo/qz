<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialPostPlatform;
use App\Services\Social\SocialPermission;
use App\Services\Social\SocialPublisher;
use Illuminate\Http\Request;

/**
 * Everything that failed to publish, with the reason in plain language.
 *
 * Error messages shown here come from PlatformException, which is written for
 * an admin to act on ("Instagram access token expired. Reconnect Instagram.")
 * and never carries credentials or raw provider payloads.
 */
class FailedPostController extends SocialBaseController {

    public function index(Request $request) {
        $pageTitle = 'Failed Posts';

        $query = SocialPostPlatform::with(['post', 'account', 'attemptLogs'])
            ->whereIn('status', [S::FAILED, S::RETRYING])
            ->latest('last_attempt_at');

        if ($platform = $request->platform) {
            $query->where('platform', $platform);
        }
        if ($code = $request->error_code) {
            $query->where('error_code', $code);
        }

        $failures = $query->paginate(getPaginate())->withQueryString();

        $errorCodes = SocialPostPlatform::whereIn('status', [S::FAILED, S::RETRYING])
            ->whereNotNull('error_code')
            ->distinct()
            ->pluck('error_code');

        // Grouping by cause makes the common fix obvious - usually one expired
        // account behind a dozen failures.
        $byReason = SocialPostPlatform::whereIn('status', [S::FAILED, S::RETRYING])
            ->selectRaw('error_code, platform, COUNT(*) as total')
            ->groupBy('error_code', 'platform')
            ->orderByDesc('total')
            ->get();

        return view('admin.social.failed.index', compact('pageTitle', 'failures', 'errorCodes', 'byReason'));
    }

    /**
     * Retries every retryable failure.
     *
     * Failures that need a human first (an expired token, a rejected media
     * format) are skipped and reported, because retrying them just burns
     * attempts and hides the real problem.
     */
    public function retryAll(Request $request, SocialPublisher $publisher) {
        $this->can(SocialPermission::RETRY);

        $blocked = ['token_expired', 'permission_denied', 'not_connected', 'not_configured', 'invalid_media', 'unsupported'];

        $query = SocialPostPlatform::where('status', S::FAILED)
            ->whereNotIn('error_code', $blocked);

        if ($platform = $request->platform) {
            $query->where('platform', $platform);
        }

        $queued  = 0;
        $skipped = 0;

        foreach ($query->limit(200)->get() as $target) {
            try {
                $publisher->retry($target);
                $queued++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        $needsAttention = SocialPostPlatform::where('status', S::FAILED)
            ->whereIn('error_code', $blocked)
            ->count();

        $notify[] = $queued
            ? ['success', "Queued $queued failure(s) for another attempt."]
            : ['info', 'Nothing was eligible for an automatic retry.'];

        if ($needsAttention) {
            $notify[] = ['warning', "$needsAttention failure(s) need attention first - usually an account to reconnect or media to replace."];
        }
        if ($skipped) {
            $notify[] = ['warning', "$skipped could not be queued."];
        }

        return back()->withNotify($notify);
    }
}
