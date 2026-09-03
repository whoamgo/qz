<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus;
use App\Http\Controllers\Controller;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialSetting;
use App\Services\Social\PlatformCapability;
use App\Services\Social\PlatformRegistry;
use App\Services\Social\SocialPermission;

/**
 * Shared behaviour for the Social Media Center screens.
 *
 * Every action authorises explicitly. The sidebar and buttons already hide what
 * a role cannot do, but that is presentation - this is the check that counts.
 */
abstract class SocialBaseController extends Controller {

    protected function can(string $ability): void {
        SocialPermission::authorize($ability);
    }

    /** Accounts grouped by platform, for pickers and status strips. */
    protected function accountsByPlatform() {
        return SocialAccount::orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->groupBy('platform');
    }

    /** Connected, publishable accounts keyed by platform. */
    protected function publishableAccounts() {
        return SocialAccount::connected()
            ->orderByDesc('is_default')
            ->get()
            ->groupBy('platform');
    }

    /**
     * The data every wizard-ish screen needs: which platforms exist, which are
     * configured, which have a usable account, and what each one can carry.
     */
    protected function platformContext(): array {
        $registry = app(PlatformRegistry::class);
        $accounts = $this->publishableAccounts();

        $platforms = [];
        foreach (array_keys(SocialStatus::PLATFORMS) as $key) {
            $description = $registry->describe($key);

            $description['accounts']  = $accounts->get($key, collect())->map(fn ($a) => [
                'id'       => $a->id,
                'name'     => $a->display_name,
                'username' => $a->username,
                'avatar'   => $a->avatar_url,
            ])->values()->all();
            $description['available'] = $description['configured'] && count($description['accounts']) > 0;

            // Exactly why a platform cannot be selected, so the UI never shows
            // a disabled checkbox with no explanation.
            $description['reason'] = match (true) {
                !$description['configured'] => SocialStatus::platformName($key) . ' is not configured on this server.',
                !$description['accounts']   => 'No ' . SocialStatus::platformName($key) . ' account is connected.',
                default                     => null,
            };

            $platforms[$key] = $description;
        }

        return $platforms;
    }

    protected function settings(): SocialSetting {
        return SocialSetting::config();
    }

    /** The capability matrix as JSON for the browser. */
    protected function capabilitiesJson(): string {
        return json_encode(PlatformCapability::toJs(), JSON_UNESCAPED_SLASHES);
    }
}
