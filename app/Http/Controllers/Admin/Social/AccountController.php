<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialAccount;
use App\Services\Social\PlatformRegistry;
use App\Services\Social\SocialAccountService;
use App\Services\Social\SocialAuditLogger;
use App\Services\Social\SocialPermission;
use App\Services\Social\Support\PlatformException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Connecting and managing platform accounts.
 *
 * Managing accounts is restricted to the super admin: a connection grants
 * long-lived credentials that can publish to a live audience, which is a
 * different level of trust from being allowed to write a post.
 *
 * No response from this controller ever contains a token. Views receive account
 * models whose `credentials` attribute is hidden from serialisation, and the
 * connect forms write credentials without reading them back.
 */
class AccountController extends SocialBaseController {

    public function __construct(
        protected SocialAccountService $service,
        protected PlatformRegistry $registry,
    ) {}

    public function index() {
        $pageTitle = 'Social Accounts';

        $accounts = SocialAccount::orderBy('platform')->orderByDesc('is_default')->get()->groupBy('platform');

        $platforms = collect(S::PLATFORMS)->map(function ($name, $key) use ($accounts) {
            $adapter = $this->registry->make($key);

            return [
                'key'        => $key,
                'name'       => $name,
                'icon'       => S::platformIcon($key),
                'color'      => S::platformColor($key),
                'configured' => $adapter->isConfigured(),
                'auth'       => config("social.platforms.$key.auth", 'oauth2'),
                'accounts'   => $accounts->get($key, collect()),
                // Named so the admin knows exactly which variables to set.
                'env_keys'   => $this->envKeysFor($key),
            ];
        })->values();

        $canManage = SocialPermission::allows(SocialPermission::MANAGE_ACCOUNTS);

        return view('admin.social.accounts.index', compact('pageTitle', 'platforms', 'canManage'));
    }

    /* -------------------------------------------------------------- Connect */

    public function connect(Request $request, string $platform) {
        $this->can(SocialPermission::MANAGE_ACCOUNTS);
        $this->assertKnown($platform);

        try {
            return redirect()->away($this->service->beginConnect($platform, $request));
        } catch (PlatformException $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function callback(Request $request, string $platform) {
        $this->can(SocialPermission::MANAGE_ACCOUNTS);
        $this->assertKnown($platform);

        try {
            $result = $this->service->handleCallback($platform, $request);

            if ($result['choices']) {
                $pageTitle = 'Choose a destination';
                return view('admin.social.accounts.choose', [
                    'pageTitle' => $pageTitle,
                    'platform'  => $platform,
                    'choices'   => $result['choices'],
                ]);
            }

            $notify[] = ['success', S::platformName($platform) . ' connected as "' . $result['account']->display_name . '".'];
        } catch (PlatformException $e) {
            SocialAuditLogger::failure('account.connect', $e->getMessage(), ['platform' => $platform]);
            $notify[] = ['error', $e->getMessage()];
        } catch (\Throwable $e) {
            SocialAuditLogger::failure('account.connect', 'Unexpected error during callback.', ['platform' => $platform]);
            $notify[] = ['error', 'The connection could not be completed. Check the server logs for details.'];
        }

        return to_route('admin.social.accounts.index')->withNotify($notify);
    }

    public function choose(Request $request, string $platform) {
        $this->can(SocialPermission::MANAGE_ACCOUNTS);
        $this->assertKnown($platform);

        $request->validate(['choice' => 'required|string|max:191']);

        try {
            $account  = $this->service->completeChoice($platform, $request->choice, $request);
            $notify[] = ['success', S::platformName($platform) . ' connected as "' . $account->display_name . '".'];
        } catch (PlatformException $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return to_route('admin.social.accounts.index')->withNotify($notify);
    }

    /**
     * Connects a platform whose credential is pasted rather than authorised
     * (Telegram bot tokens, WhatsApp system-user tokens).
     */
    public function connectWithToken(Request $request, string $platform) {
        $this->can(SocialPermission::MANAGE_ACCOUNTS);
        $this->assertKnown($platform);

        $rules = match ($platform) {
            S::TELEGRAM => [
                'bot_token' => 'required|string|max:200',
                'chat_id'   => 'required|string|max:100',
            ],
            S::WHATSAPP => [
                'access_token'    => 'required|string|max:500',
                'phone_number_id' => 'required|string|max:60',
                'business_id'     => 'nullable|string|max:60',
                'recipients'      => 'nullable|string|max:5000',
                'template_name'   => 'nullable|string|max:100',
            ],
            default => abort(422, S::platformName($platform) . ' is connected with OAuth, not a pasted token.'),
        };

        $data = $request->validate($rules);

        if ($platform === S::WHATSAPP && !empty($data['recipients'])) {
            $data['recipients'] = array_values(array_filter(array_map(
                fn ($n) => preg_replace('/\D/', '', trim($n)),
                preg_split('/[\s,;]+/', $data['recipients']) ?: []
            )));
        }

        try {
            $account  = $this->service->connectWithToken($platform, $data);
            $notify[] = ['success', S::platformName($platform) . ' connected as "' . $account->display_name . '".'];
        } catch (PlatformException $e) {
            // The credential was never stored - the adapter rejected it first.
            $notify[] = ['error', $e->getMessage()];
        } catch (\Throwable $e) {
            $notify[] = ['error', 'Could not verify those credentials with ' . S::platformName($platform) . '.'];
        }

        return back()->withNotify($notify ?? []);
    }

    /* --------------------------------------------------------------- Manage */

    public function test(SocialAccount $account) {
        $this->can(SocialPermission::MANAGE_ACCOUNTS);

        $result = $this->service->test($account);

        if (request()->ajax()) {
            return response()->json(['success' => $result->ok, 'message' => $result->message]);
        }

        $notify[] = [$result->ok ? 'success' : 'error', $result->message];
        return back()->withNotify($notify);
    }

    public function sync(SocialAccount $account) {
        $this->can(SocialPermission::MANAGE_ACCOUNTS);

        $ok = $this->service->syncProfile($account);

        $notify[] = $ok
            ? ['success', 'Refreshed the profile for "' . $account->display_name . '".']
            : ['error', $account->last_error ?: 'Could not refresh this account.'];

        return back()->withNotify($notify);
    }

    public function disconnect(SocialAccount $account) {
        $this->can(SocialPermission::MANAGE_ACCOUNTS);

        $this->service->disconnect($account);

        $notify[] = ['success', 'Disconnected. The stored credentials have been erased; scheduled posts for this account will fail until it is reconnected.'];
        return back()->withNotify($notify);
    }

    public function makeDefault(SocialAccount $account) {
        $this->can(SocialPermission::MANAGE_ACCOUNTS);

        SocialAccount::platform($account->platform)->update(['is_default' => false]);
        $account->update(['is_default' => true]);

        $notify[] = ['success', '"' . $account->display_name . '" is now the default ' . $account->platform_name . ' account.'];
        return back()->withNotify($notify);
    }

    public function destroy(SocialAccount $account) {
        $this->can(SocialPermission::MANAGE_ACCOUNTS);

        // Published posts point at this account for their permalinks and
        // metrics; deleting it would orphan that history.
        if ($account->posts()->where('status', S::PUBLISHED)->exists()) {
            $notify[] = ['error', 'This account has published posts attached to it. Disconnect it instead - that erases the credentials but keeps the history.'];
            return back()->withNotify($notify);
        }

        $name = $account->display_name;
        SocialAuditLogger::forModel('account.disconnect', $account, [
            'platform'    => $account->platform,
            'description' => 'Deleted account "' . $name . '"',
        ]);

        $account->delete();

        $notify[] = ['success', 'Removed "' . $name . '".'];
        return back()->withNotify($notify);
    }

    /* -------------------------------------------------------------- Helpers */

    protected function assertKnown(string $platform): void {
        abort_unless($this->registry->has($platform), 404);
    }

    /** The env variable names for a platform, so setup is self-documenting. */
    protected function envKeysFor(string $platform): array {
        return match ($platform) {
            S::YOUTUBE   => ['YOUTUBE_CLIENT_ID', 'YOUTUBE_CLIENT_SECRET'],
            S::FACEBOOK  => ['FACEBOOK_APP_ID', 'FACEBOOK_APP_SECRET'],
            S::INSTAGRAM => ['INSTAGRAM_CLIENT_ID', 'INSTAGRAM_CLIENT_SECRET', '(or reuse FACEBOOK_APP_ID / FACEBOOK_APP_SECRET)'],
            S::X         => ['X_CLIENT_ID', 'X_CLIENT_SECRET'],
            S::LINKEDIN  => ['LINKEDIN_CLIENT_ID', 'LINKEDIN_CLIENT_SECRET'],
            S::THREADS   => ['THREADS_CLIENT_ID', 'THREADS_CLIENT_SECRET'],
            S::TELEGRAM  => ['TELEGRAM_BOT_TOKEN (optional - can be entered below)'],
            S::WHATSAPP  => ['WHATSAPP_ACCESS_TOKEN (optional)', 'WHATSAPP_PHONE_NUMBER_ID (optional)'],
            default      => [],
        };
    }
}
