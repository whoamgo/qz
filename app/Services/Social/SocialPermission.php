<?php

namespace App\Services\Social;

use App\Constants\SocialStatus as S;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;

/**
 * Role-based access control for the Social Media Center.
 *
 * The matrix is explicit rather than hierarchical: "an editor may create drafts
 * but may never publish" is a rule worth being able to read off the page, and a
 * numeric rank comparison hides exactly that kind of distinction.
 *
 * Every check is server-side. The UI hides buttons a role cannot use, but that
 * is a courtesy - the controllers re-check before acting, because a hidden
 * button is not a permission.
 */
class SocialPermission {

    /* -------------------------------------------------------- Ability names */

    const VIEW           = 'social.view';
    const CREATE         = 'social.create';
    const EDIT           = 'social.edit';
    const DELETE         = 'social.delete';
    const PUBLISH        = 'social.publish';
    const SCHEDULE       = 'social.schedule';
    const RETRY          = 'social.retry';
    const APPROVE        = 'social.approve';
    const MANAGE_MEDIA   = 'social.media.manage';
    const DELETE_MEDIA   = 'social.media.delete';
    const MANAGE_LIBRARY = 'social.library.manage';   // templates, hashtags, campaigns
    const MANAGE_AUTOMATION = 'social.automation.manage';
    const MANAGE_ACCOUNTS   = 'social.accounts.manage';
    const MANAGE_SETTINGS   = 'social.settings.manage';
    const VIEW_LOGS         = 'social.logs.view';
    const USE_AI            = 'social.ai.use';
    const MANAGE_INBOX      = 'social.inbox.manage';

    /** Human labels for the roles/permissions reference screen. */
    const ABILITY_LABELS = [
        self::VIEW              => 'View dashboards, posts and analytics',
        self::CREATE            => 'Create posts and drafts',
        self::EDIT              => 'Edit existing posts',
        self::DELETE            => 'Delete posts',
        self::PUBLISH           => 'Publish immediately',
        self::SCHEDULE          => 'Schedule and reschedule posts',
        self::RETRY             => 'Retry failed publishes',
        self::APPROVE           => 'Approve or reject posts',
        self::MANAGE_MEDIA      => 'Upload and edit media',
        self::DELETE_MEDIA      => 'Delete media',
        self::MANAGE_LIBRARY    => 'Manage campaigns, templates and hashtags',
        self::MANAGE_AUTOMATION => 'Create and toggle automations',
        self::MANAGE_ACCOUNTS   => 'Connect and disconnect accounts',
        self::MANAGE_SETTINGS   => 'Change Social Center settings',
        self::VIEW_LOGS         => 'View activity logs',
        self::USE_AI            => 'Use the AI content generator',
        self::MANAGE_INBOX      => 'Read and manage the inbox',
    ];

    /* ------------------------------------------------------------- The matrix */

    const MATRIX = [
        S::ROLE_SUPER_ADMIN => ['*'],

        S::ROLE_ADMIN => [
            self::VIEW, self::CREATE, self::EDIT, self::DELETE, self::PUBLISH,
            self::SCHEDULE, self::RETRY, self::APPROVE, self::MANAGE_MEDIA,
            self::DELETE_MEDIA, self::MANAGE_LIBRARY, self::MANAGE_AUTOMATION,
            self::VIEW_LOGS, self::USE_AI, self::MANAGE_INBOX,
            // Deliberately excluded: MANAGE_ACCOUNTS and MANAGE_SETTINGS.
            // Connecting accounts grants long-lived platform credentials, which
            // stays with the super admin.
        ],

        S::ROLE_MANAGER => [
            self::VIEW, self::CREATE, self::EDIT, self::DELETE, self::PUBLISH,
            self::SCHEDULE, self::RETRY, self::MANAGE_MEDIA, self::DELETE_MEDIA,
            self::MANAGE_LIBRARY, self::USE_AI, self::MANAGE_INBOX,
        ],

        S::ROLE_EDITOR => [
            self::VIEW, self::CREATE, self::EDIT, self::MANAGE_MEDIA, self::USE_AI,
            // An editor prepares content; a manager decides when it goes out.
        ],

        S::ROLE_VIEWER => [
            self::VIEW,
        ],
    ];

    public static function abilitiesFor(string $role): array {
        $abilities = self::MATRIX[$role] ?? self::MATRIX[S::ROLE_VIEWER];

        return $abilities === ['*'] ? array_keys(self::ABILITY_LABELS) : $abilities;
    }

    /** Does the given admin (defaulting to the logged-in one) have $ability? */
    public static function allows(string $ability, ?Admin $admin = null): bool {
        $admin = $admin ?: Auth::guard('admin')->user();
        if (!$admin) {
            return false;
        }

        $abilities = self::MATRIX[$admin->role] ?? self::MATRIX[S::ROLE_VIEWER];

        return $abilities === ['*'] || in_array($ability, $abilities, true);
    }

    public static function denies(string $ability, ?Admin $admin = null): bool {
        return !self::allows($ability, $admin);
    }

    /**
     * Aborts the request when the ability is missing.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public static function authorize(string $ability, ?Admin $admin = null): void {
        if (self::denies($ability, $admin)) {
            SocialAuditLogger::record('permission.denied', [
                'result'      => 'denied',
                'description' => 'Blocked: ' . (self::ABILITY_LABELS[$ability] ?? $ability),
            ]);

            abort(403, 'Your role does not allow this action.');
        }
    }

    /** Every ability of the current admin, for sharing with Blade. */
    public static function current(): array {
        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            return [];
        }

        return array_fill_keys(self::abilitiesFor($admin->role), true);
    }
}
