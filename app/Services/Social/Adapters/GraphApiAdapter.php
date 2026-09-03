<?php

namespace App\Services\Social\Adapters;

use App\Models\Social\SocialAccount;
use App\Services\Social\Support\PlatformException;
use App\Services\Social\Support\SocialHttpClient;
use Illuminate\Http\Request;

/**
 * Shared behaviour for the Meta Graph family (Facebook, Instagram, Threads,
 * WhatsApp), which share an OAuth shape, an error shape and a request shape.
 *
 * Every call carries an appsecret_proof - an HMAC of the access token keyed with
 * the app secret. Meta uses it to prove the call came from this server, so a
 * token stolen from elsewhere cannot be replayed against our app.
 */
abstract class GraphApiAdapter extends AbstractPlatformAdapter {

    protected function apiBase(): string {
        return rtrim($this->url((string) $this->config('api_base')), '/');
    }

    /** HMAC-SHA256 of the token, keyed with the app secret. */
    protected function appSecretProof(string $token): ?string {
        $secret = $this->config('client_secret');
        return $secret ? hash_hmac('sha256', $token, $secret) : null;
    }

    protected function graphGet(string $token, string $path, array $query = []): array {
        if ($proof = $this->appSecretProof($token)) {
            $query['appsecret_proof'] = $proof;
        }

        return $this->result(
            $this->http->withToken($token)->get($this->apiBase() . '/' . ltrim($path, '/'), $query)
        );
    }

    protected function graphPost(string $token, string $path, array $params = []): array {
        if ($proof = $this->appSecretProof($token)) {
            $params['appsecret_proof'] = $proof;
        }

        return $this->result(
            $this->http->withToken($token)->asForm()->post($this->apiBase() . '/' . ltrim($path, '/'), $params)
        );
    }

    protected function graphDelete(string $token, string $path, array $params = []): bool {
        if ($proof = $this->appSecretProof($token)) {
            $params['appsecret_proof'] = $proof;
        }

        $response = $this->http->withToken($token)->delete($this->apiBase() . '/' . ltrim($path, '/'), $params);
        return $response->successful();
    }

    /* ------------------------------------------------------------------ OAuth */

    public function authorizationUrl(string $state): array {
        if (!$this->isConfigured()) {
            throw PlatformException::notConfigured($this->key());
        }

        $url = $this->url((string) $this->config('authorize_url')) . '?' . http_build_query([
            'client_id'     => $this->config('client_id'),
            'redirect_uri'  => $this->redirectUri(),
            'state'         => $state,
            'response_type' => 'code',
            'scope'         => implode(',', (array) $this->config('scopes', [])),
        ]);

        return ['url' => $url, 'session' => []];
    }

    /**
     * Exchanges the code for a token.
     *
     * Meta's first token is short-lived (about an hour), which would break
     * scheduled publishing within the day, so it is immediately swapped for the
     * long-lived (~60 day) variant.
     */
    public function exchangeCallback(Request $request, array $session): array {
        if ($error = $request->query('error_description') ?: $request->query('error')) {
            throw new PlatformException(
                ucfirst($this->key()) . ' authorisation was declined: ' . SocialHttpClient::redactString((string) $error),
                'oauth_declined'
            );
        }

        $code = (string) $request->query('code');
        if (!$code) {
            throw new PlatformException('The authorisation callback did not include a code.', 'oauth_invalid');
        }

        $short = $this->result($this->http->request()->get($this->url((string) $this->config('token_url')), [
            'client_id'     => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'redirect_uri'  => $this->redirectUri(),
            'code'          => $code,
        ]));

        $token = $short['access_token'] ?? null;
        if (!$token) {
            throw new PlatformException('No access token was returned by ' . ucfirst($this->key()) . '.', 'oauth_invalid');
        }

        $long      = $this->exchangeLongLived($token);
        $token     = $long['access_token'] ?? $token;
        $expiresIn = (int) ($long['expires_in'] ?? $short['expires_in'] ?? 0);

        return [
            'credentials' => ['access_token' => $token],
            'expires_at'  => $expiresIn ? now()->addSeconds($expiresIn)->toDateTimeString() : null,
            'scopes'      => implode(',', (array) $this->config('scopes', [])),
            'profile'     => $this->profileFromToken($token),
        ];
    }

    protected function exchangeLongLived(string $shortToken): array {
        $response = $this->http->request()->get($this->url((string) $this->config('token_url')), [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->config('client_id'),
            'client_secret'     => $this->config('client_secret'),
            'fb_exchange_token' => $shortToken,
        ]);

        // A failure here is not fatal - the short-lived token still works for
        // now, and the account will simply need reconnecting sooner.
        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /**
     * Refreshes by re-exchanging the current long-lived token, which Meta
     * allows and which resets the 60-day window.
     */
    public function refreshToken(SocialAccount $account): bool {
        $token = $account->accessToken();
        if (!$token || !$this->isConfigured()) {
            return false;
        }

        $refreshed = $this->exchangeLongLived($token);
        if (empty($refreshed['access_token'])) {
            return false;
        }

        $account->setCredentials(['access_token' => $refreshed['access_token']]);
        $account->token_expires_at   = isset($refreshed['expires_in'])
            ? now()->addSeconds((int) $refreshed['expires_in'])
            : null;
        $account->token_refreshed_at = now();
        $account->save();

        return true;
    }

    /** Facts about the authorising user/asset, used to label the account. */
    abstract protected function profileFromToken(string $token): array;
}
