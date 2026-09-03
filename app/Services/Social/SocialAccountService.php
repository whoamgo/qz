<?php

namespace App\Services\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialAccount;
use App\Services\Social\Support\ConnectionResult;
use App\Services\Social\Support\PlatformException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Connecting, testing and disconnecting platform accounts.
 *
 * OAuth state handling is the security-critical part. The `state` value is
 * generated here, kept server-side in the admin's session, and compared with
 * hash_equals on the way back. Without that check, anyone could hand our
 * callback an authorisation code for *their* account and silently attach it to
 * this panel.
 *
 * Tokens are written straight into the account's encrypted credentials and are
 * never returned to the caller, so nothing token-shaped can reach a view.
 */
class SocialAccountService {

    const SESSION_KEY = 'social_oauth';

    public function __construct(protected PlatformRegistry $registry) {}

    /* ------------------------------------------------------------- Connect */

    /**
     * Starts an OAuth connection and returns the URL to redirect the admin to.
     */
    public function beginConnect(string $platform, Request $request): string {
        $adapter = $this->registry->make($platform);

        if (!$adapter->isConfigured()) {
            throw PlatformException::notConfigured($platform);
        }

        $state = Str::random(48);
        $auth  = $adapter->authorizationUrl($state);

        // The state and any PKCE verifier live only in the session - never in a
        // cookie we set ourselves, and never in the URL we hand the browser.
        $request->session()->put(self::SESSION_KEY . '.' . $platform, [
            'state'      => $state,
            'expires_at' => now()->addSeconds((int) config('social.oauth_state_ttl', 600))->timestamp,
            'session'    => $auth['session'] ?? [],
        ]);

        SocialAuditLogger::record('account.connect', [
            'platform'    => $platform,
            'description' => 'Started an OAuth connection for ' . S::platformName($platform),
        ]);

        return $auth['url'];
    }

    /**
     * Handles the callback.
     *
     * @return array{account:?SocialAccount,choices:array,platform:string}
     *         `choices` is non-empty when the platform returned several Pages
     *         or organisations and the admin has to pick one.
     */
    public function handleCallback(string $platform, Request $request): array {
        $adapter = $this->registry->make($platform);
        $stored  = $request->session()->pull(self::SESSION_KEY . '.' . $platform);

        $this->assertValidState($stored, (string) $request->query('state'), $platform);

        $data = $adapter->exchangeCallback($request, $stored['session'] ?? []);

        $profile = $data['profile'] ?? [];
        $choices = $profile['pages'] ?? [];

        // Several publishable destinations: park the credentials in the session
        // and let the admin choose. The token still never leaves the server.
        if (count($choices) > 1) {
            $request->session()->put(self::SESSION_KEY . '.pending.' . $platform, [
                'data'       => $data,
                'expires_at' => now()->addMinutes(15)->timestamp,
            ]);

            return ['account' => null, 'choices' => $choices, 'platform' => $platform];
        }

        $account = $this->persist($platform, $data, $choices[0] ?? null);

        return ['account' => $account, 'choices' => [], 'platform' => $platform];
    }

    /** Completes a connection after the admin picked a Page/organisation. */
    public function completeChoice(string $platform, string $choiceId, Request $request): SocialAccount {
        $pending = $request->session()->pull(self::SESSION_KEY . '.pending.' . $platform);

        if (!$pending || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            throw new PlatformException('The connection expired before a destination was chosen. Start again.', 'oauth_expired');
        }

        $choices = data_get($pending, 'data.profile.pages', []);
        $choice  = collect($choices)->firstWhere('id', $choiceId);

        if (!$choice) {
            throw new PlatformException('That destination is no longer available. Start the connection again.', 'oauth_invalid');
        }

        return $this->persist($platform, $pending['data'], $choice);
    }

    /**
     * Rejects a callback whose state is missing, stale or mismatched.
     *
     * hash_equals rather than === so the comparison does not leak the expected
     * value through timing.
     */
    protected function assertValidState(?array $stored, string $received, string $platform): void {
        if (!$stored || empty($stored['state'])) {
            throw new PlatformException(
                'This ' . S::platformName($platform) . ' connection could not be verified. Start the connection from the Accounts page and complete it in the same browser.',
                'oauth_state_missing'
            );
        }

        if (($stored['expires_at'] ?? 0) < now()->timestamp) {
            throw new PlatformException('The connection attempt timed out. Please try again.', 'oauth_state_expired');
        }

        if (!$received || !hash_equals((string) $stored['state'], $received)) {
            throw new PlatformException(
                'The authorisation response did not match the request that started it, so it was rejected.',
                'oauth_state_mismatch'
            );
        }
    }

    /* ------------------------------------------------------------- Persist */

    /**
     * Writes (or updates) the account. Matching on (platform, external_id)
     * means reconnecting refreshes the existing row instead of creating a
     * duplicate card for the same channel.
     */
    protected function persist(string $platform, array $data, ?array $choice = null): SocialAccount {
        $profile     = $data['profile'] ?? [];
        $credentials = $data['credentials'] ?? [];

        $externalId = $choice['id'] ?? ($profile['external_id'] ?? null);

        if ($choice) {
            // Page/organisation specific credentials override the user-level ones.
            $credentials = array_merge($credentials, array_filter([
                'page_access_token' => $choice['access_token'] ?? null,
                'page_id'           => $choice['page_id'] ?? ($platform === S::FACEBOOK ? $choice['id'] : null),
                'ig_user_id'        => $platform === S::INSTAGRAM ? $choice['id'] : null,
                'author_urn'        => $platform === S::LINKEDIN ? $choice['id'] : null,
            ], fn ($v) => $v !== null));
        }

        $account = SocialAccount::firstOrNew([
            'platform'    => $platform,
            'external_id' => $externalId,
        ]);

        $account->fill([
            'name'         => $choice['name'] ?? ($profile['name'] ?? S::platformName($platform)),
            'username'     => $choice['username'] ?? ($profile['username'] ?? null),
            'avatar_url'   => $choice['avatar'] ?? ($profile['avatar_url'] ?? null),
            'profile_url'  => $choice['link'] ?? ($profile['profile_url'] ?? null),
            'status'       => S::ACCOUNT_CONNECTED,
            'scopes'       => $data['scopes'] ?? null,
            'followers'    => $choice['followers'] ?? ($profile['followers'] ?? 0),
            'following'    => $profile['following'] ?? 0,
            'media_count'  => $profile['media_count'] ?? 0,
            'last_sync_at' => now(),
            'last_error'   => null,
            'connected_by' => Auth::guard('admin')->id(),
            'meta'         => $profile['meta'] ?? null,
        ]);

        $account->setCredentials($credentials);
        $account->token_expires_at = $data['expires_at'] ?? null;

        // First account for a platform becomes the default publishing target.
        if (!SocialAccount::platform($platform)->where('id', '!=', $account->id ?? 0)->exists()) {
            $account->is_default = true;
        }

        $account->save();

        SocialAuditLogger::forModel('account.connect', $account, [
            'platform'    => $platform,
            'description' => 'Connected ' . S::platformName($platform) . ' account "' . $account->display_name . '"',
        ]);

        return $account;
    }

    /**
     * Connects a platform that uses a pasted credential rather than OAuth
     * (Telegram bot tokens, WhatsApp system-user tokens).
     */
    public function connectWithToken(string $platform, array $credentials): SocialAccount {
        $adapter = $this->registry->make($platform);

        $account = new SocialAccount([
            'platform' => $platform,
            'status'   => S::ACCOUNT_CONNECTED,
        ]);
        $account->setCredentials(array_filter($credentials, fn ($v) => $v !== null && $v !== ''));

        // Prove the credential works before storing it - saving a bad token
        // would leave a card claiming "Connected" that fails on first publish.
        $profile = $adapter->fetchProfile($account);

        $externalId = $profile['external_id'] ?? null;

        $existing = $externalId
            ? SocialAccount::where('platform', $platform)->where('external_id', $externalId)->first()
            : null;

        if ($existing) {
            $account = $existing;
            $account->setCredentials(array_filter($credentials, fn ($v) => $v !== null && $v !== ''));
        }

        $account->fill([
            'platform'     => $platform,
            'external_id'  => $externalId,
            'name'         => $profile['name'] ?? S::platformName($platform),
            'username'     => $profile['username'] ?? null,
            'avatar_url'   => $profile['avatar_url'] ?? null,
            'profile_url'  => $profile['profile_url'] ?? null,
            'followers'    => $profile['followers'] ?? 0,
            'status'       => S::ACCOUNT_CONNECTED,
            'last_sync_at' => now(),
            'last_error'   => null,
            'connected_by' => Auth::guard('admin')->id(),
            'meta'         => $profile['meta'] ?? null,
        ]);

        if (!SocialAccount::platform($platform)->where('id', '!=', $account->id ?? 0)->exists()) {
            $account->is_default = true;
        }

        $account->save();

        SocialAuditLogger::forModel('account.connect', $account, [
            'platform'    => $platform,
            'description' => 'Connected ' . S::platformName($platform) . ' account "' . $account->display_name . '"',
        ]);

        return $account;
    }

    /* ------------------------------------------------------- Test / manage */

    public function test(SocialAccount $account): ConnectionResult {
        $result = $this->registry->make($account->platform)->testConnection($account);

        $account->last_checked_at = now();

        if ($result->ok) {
            $account->status     = S::ACCOUNT_CONNECTED;
            $account->last_error = null;

            // Refresh the cached profile facts while we are here.
            foreach (['name', 'username', 'avatar_url', 'profile_url', 'followers'] as $field) {
                if (!empty($result->details[$field])) {
                    $account->{$field} = $result->details[$field];
                }
            }
            $account->last_sync_at = now();
        } else {
            $account->status     = S::ACCOUNT_ERROR;
            $account->last_error = mb_substr($result->message, 0, 500);
        }

        $account->save();

        SocialAuditLogger::forModel('account.test', $account, [
            'platform'    => $account->platform,
            'result'      => $result->ok ? 'success' : 'failed',
            'description' => $result->message,
        ]);

        return $result;
    }

    /**
     * Disconnects an account and destroys its stored credentials.
     *
     * The row is kept (posts reference it) but every secret is wiped, so a
     * disconnected account holds nothing worth stealing.
     */
    public function disconnect(SocialAccount $account): void {
        $name = $account->display_name;

        $account->forgetCredentials();
        $account->status           = S::ACCOUNT_DISCONNECTED;
        $account->token_expires_at = null;
        $account->scopes           = null;
        $account->last_error       = null;
        $account->save();

        SocialAuditLogger::forModel('account.disconnect', $account, [
            'platform'    => $account->platform,
            'description' => 'Disconnected ' . S::platformName($account->platform) . ' account "' . $name . '"',
        ]);
    }

    /** Refreshes cached profile facts (name, avatar, followers) for a card. */
    public function syncProfile(SocialAccount $account): bool {
        try {
            $profile = $this->registry->make($account->platform)->fetchProfile($account);

            $account->fill(array_filter([
                'name'        => $profile['name'] ?? null,
                'username'    => $profile['username'] ?? null,
                'avatar_url'  => $profile['avatar_url'] ?? null,
                'profile_url' => $profile['profile_url'] ?? null,
            ], fn ($v) => $v !== null));

            $account->followers    = $profile['followers'] ?? $account->followers;
            $account->following    = $profile['following'] ?? $account->following;
            $account->media_count  = $profile['media_count'] ?? $account->media_count;
            $account->last_sync_at = now();
            $account->status       = S::ACCOUNT_CONNECTED;
            $account->last_error   = null;
            $account->save();

            return true;
        } catch (PlatformException $e) {
            $account->markProblem(
                $e->requiresReconnect ? S::ACCOUNT_TOKEN_EXPIRED : S::ACCOUNT_ERROR,
                $e->getMessage()
            );
            return false;
        } catch (\Throwable $e) {
            $account->markProblem(S::ACCOUNT_ERROR, 'Could not reach ' . S::platformName($account->platform) . '.');
            return false;
        }
    }
}
