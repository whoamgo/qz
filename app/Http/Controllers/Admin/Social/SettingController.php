<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Admin;
use App\Models\Social\SocialSetting;
use App\Services\Social\PlatformRegistry;
use App\Services\Social\SocialAuditLogger;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Social Center settings and role assignment.
 *
 * Platform credentials are NOT editable here: they come from the environment,
 * so secrets stay out of the database and out of Git. This screen only reports
 * whether each one is present.
 */
class SettingController extends SocialBaseController {

    public function index(PlatformRegistry $registry) {
        $this->can(SocialPermission::MANAGE_SETTINGS);

        $pageTitle = 'Social Media Settings';
        $settings  = SocialSetting::config();

        // Presence only - never the value.
        $credentials = collect(S::PLATFORMS)->map(function ($name, $key) use ($registry) {
            return [
                'key'        => $key,
                'name'       => $name,
                'icon'       => S::platformIcon($key),
                'configured' => $registry->make($key)->isConfigured(),
                'auth'       => config("social.platforms.$key.auth", 'oauth2'),
                'redirect'   => rtrim((string) config('social.redirect_base'), '/') . '/admin/social/accounts/callback/' . $key,
            ];
        })->values();

        $mediaService = app(\App\Services\Social\SocialMediaService::class);

        $environment = [
            'app_url'       => config('app.url'),
            'https'         => str_starts_with(strtolower((string) config('app.url')), 'https://'),
            'public_host'   => !preg_match('/localhost|127\.0\.0\.1|\.local/i', (string) config('app.url')),
            'ffprobe'       => $mediaService->canProbeVideo(),
            'last_cron'     => gs('last_cron'),
            'timezone'      => config('app.timezone'),
        ];

        return view('admin.social.settings.index', compact('pageTitle', 'settings', 'credentials', 'environment'));
    }

    public function update(Request $request) {
        $this->can(SocialPermission::MANAGE_SETTINGS);

        $data = $request->validate([
            'enabled'                => 'nullable|boolean',
            'require_approval'       => 'nullable|boolean',
            'utm_enabled'            => 'nullable|boolean',
            'utm_medium'             => 'nullable|string|max:60|regex:/^[A-Za-z0-9_\-]+$/',
            'utm_source_map'         => 'nullable|string|max:500',
            'auto_draft_from_quiz'   => 'nullable|boolean',
            'ai_enabled'             => 'nullable|boolean',
            'ai_auto_publish'        => 'nullable|boolean',
            'max_attempts'           => 'required|integer|min:1|max:10',
            'retry_base_seconds'     => 'required|integer|min:10|max:3600',
            'request_timeout'        => 'required|integer|min:15|max:600',
            'job_lease_seconds'      => 'required|integer|min:60|max:3600',
            'schedule_grace_minutes' => 'required|integer|min:5|max:1440',
            'max_image_mb'           => 'required|integer|min:1|max:100',
            'max_video_mb'           => 'required|integer|min:1|max:2048',
            'default_timezone'       => 'nullable|timezone',
        ], [
            'utm_medium.regex' => 'The UTM medium may only contain letters, numbers, hyphens and underscores.',
        ]);

        $settings = SocialSetting::config();

        foreach (['enabled', 'require_approval', 'utm_enabled', 'auto_draft_from_quiz', 'ai_enabled', 'ai_auto_publish'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $settings->fill($data)->save();
        SocialSetting::flush();

        SocialAuditLogger::record('settings.update', [
            'description' => 'Updated Social Media Center settings',
            'context'     => ['approval' => $settings->require_approval, 'enabled' => $settings->enabled],
        ]);

        $notify[] = ['success', 'Settings saved.'];

        if ($settings->ai_auto_publish) {
            $notify[] = ['warning', 'AI auto-publishing is on. AI-written copy can now reach live accounts without a human reading it first.'];
        }

        return back()->withNotify($notify);
    }

    /* ------------------------------------------------------------ Roles */

    public function roles() {
        $this->can(SocialPermission::MANAGE_SETTINGS);

        $pageTitle = 'Roles & Permissions';

        $admins = Admin::orderBy('name')->get();

        $matrix = collect(S::ROLES)->map(fn ($label, $role) => [
            'role'      => $role,
            'label'     => $label,
            'abilities' => array_fill_keys(SocialPermission::abilitiesFor($role), true),
        ])->values();

        return view('admin.social.settings.roles', [
            'pageTitle' => $pageTitle,
            'admins'    => $admins,
            'roles'     => S::ROLES,
            'matrix'    => $matrix,
            'abilities' => SocialPermission::ABILITY_LABELS,
        ]);
    }

    public function updateRole(Request $request, Admin $admin) {
        $this->can(SocialPermission::MANAGE_SETTINGS);

        $request->validate(['role' => ['required', Rule::in(array_keys(S::ROLES))]]);

        // Demoting yourself out of super admin would leave you unable to undo
        // it, and could leave the install with no super admin at all.
        if ($admin->id === Auth::guard('admin')->id() && $request->role !== S::ROLE_SUPER_ADMIN) {
            $notify[] = ['error', 'You cannot remove your own super admin role. Ask another super admin to change it.'];
            return back()->withNotify($notify);
        }

        if ($admin->isSuperAdmin() && $request->role !== S::ROLE_SUPER_ADMIN) {
            $remaining = Admin::where('role', S::ROLE_SUPER_ADMIN)->where('id', '!=', $admin->id)->count();
            if ($remaining === 0) {
                $notify[] = ['error', 'This is the only super admin. Promote another admin first.'];
                return back()->withNotify($notify);
            }
        }

        $admin->role = $request->role;
        $admin->save();

        SocialAuditLogger::record('settings.update', [
            'description' => 'Set ' . $admin->name . "'s role to " . (S::ROLES[$request->role] ?? $request->role),
        ]);

        $notify[] = ['success', $admin->name . ' is now a ' . (S::ROLES[$request->role] ?? $request->role) . '.'];
        return back()->withNotify($notify);
    }
}
