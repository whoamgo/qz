<?php

namespace App\Http\Middleware;

use App\Models\Social\SocialSetting;
use App\Services\Social\SocialPermission;
use Closure;
use Illuminate\Http\Request;

/**
 * Gate for every Social Media Center route.
 *
 * Two checks: the module has to be switched on, and the admin needs at least
 * view access. Per-action abilities are enforced in the controllers.
 */
class SocialCenter {

    public function handle(Request $request, Closure $next) {
        $settings = SocialSetting::config();

        // The super admin keeps access while the module is off, otherwise
        // switching it off would lock away the switch that turns it back on.
        if (!$settings->enabled && !$request->user('admin')?->isSuperAdmin()) {
            $notify[] = ['error', 'The Social Media Center is currently disabled.'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        SocialPermission::authorize(SocialPermission::VIEW);

        return $next($request);
    }
}
