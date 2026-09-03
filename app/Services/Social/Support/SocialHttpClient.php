<?php

namespace App\Services\Social\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The single outbound HTTP path for every platform adapter.
 *
 * Two jobs beyond issuing the request:
 *
 *  1. Redaction. Every log line and every stored attempt summary passes through
 *     redact(), so an access token can never end up in social_publish_attempts,
 *     laravel.log or an error shown to an admin.
 *
 *  2. Classification. classify() turns a platform's HTTP response into a
 *     PlatformException that says whether retrying could help, so the publisher
 *     does not sit in a retry loop against a permanently invalid request.
 */
class SocialHttpClient {

    /** Keys whose values are replaced wholesale wherever they appear. */
    const SECRET_KEYS = [
        'access_token', 'refresh_token', 'client_secret', 'client_id', 'code',
        'code_verifier', 'authorization', 'bearer', 'token', 'app_secret',
        'bot_token', 'secret', 'password', 'api_key', 'id_token', 'signature',
        'appsecret_proof', 'state', 'oauth_token', 'oauth_token_secret',
    ];

    public function __construct(
        protected int $timeout = 120,
        protected int $connectTimeout = 15,
    ) {}

    public static function make(?int $timeout = null): self {
        return new self(
            $timeout ?: (int) config('social.http_timeout', 120),
            (int) config('social.http_connect_timeout', 15),
        );
    }

    /** A configured request with no auth applied. */
    public function request(): PendingRequest {
        return Http::timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            // Retries are owned by the publishing pipeline (which records each
            // attempt); silent client-level retries would hide failures and can
            // duplicate a post whose response was merely lost.
            ->withoutRedirecting()
            ->withUserAgent('QuizMitra-SocialCenter/1.0');
    }

    public function withToken(string $token): PendingRequest {
        return $this->request()->withToken($token);
    }

    /**
     * Recursively replaces secret-ish values in an array with a marker.
     * Matching is on the key name, so an unexpected token field added by a
     * platform still gets caught as long as it is named conventionally.
     */
    public static function redact($data, int $depth = 0) {
        if ($depth > 8) {
            return '[truncated]';
        }

        if (is_array($data)) {
            $out = [];
            foreach ($data as $key => $value) {
                $lower = is_string($key) ? strtolower($key) : $key;
                if (is_string($lower) && static::isSecretKey($lower)) {
                    $out[$key] = '[redacted]';
                    continue;
                }
                $out[$key] = static::redact($value, $depth + 1);
            }
            return $out;
        }

        if (is_string($data)) {
            return static::redactString($data);
        }

        return $data;
    }

    protected static function isSecretKey(string $key): bool {
        foreach (self::SECRET_KEYS as $secret) {
            if ($key === $secret || str_contains($key, $secret)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Scrubs credentials that appear inline in a string - bearer headers, query
     * parameters and long opaque blobs that look like tokens.
     */
    public static function redactString(string $value): string {
        $value = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9\-\._~\+\/=]{8,}/i', '$1 [redacted]', $value);
        $value = preg_replace('/([?&](?:access_token|token|client_secret|bot_token|code|state|appsecret_proof)=)[^&\s"\']+/i', '$1[redacted]', $value);
        // Telegram bot tokens appear in the URL path: /bot<id>:<secret>/method
        $value = preg_replace('#/bot\d+:[A-Za-z0-9_\-]+#', '/bot[redacted]', $value);
        return $value;
    }

    /** A short, redacted excerpt of a response body for the attempts log. */
    public static function summarise(Response $response, int $limit = 2000): string {
        return mb_substr(static::redactString($response->body()), 0, $limit);
    }

    /**
     * Turns a non-2xx response into a classified exception.
     *
     * Retryable: 408, 429 and 5xx - transient conditions where the exact same
     * request may succeed shortly. Everything else is a permanent rejection and
     * retrying would just burn quota and risk duplicates.
     */
    public static function classify(string $platform, Response $response, ?string $fallbackMessage = null): PlatformException {
        $status = $response->status();
        $body   = $response->json() ?: [];

        $apiMessage = data_get($body, 'error.message')
            ?? data_get($body, 'error_description')
            ?? data_get($body, 'error.detail')
            ?? data_get($body, 'detail')
            ?? data_get($body, 'title')
            ?? data_get($body, 'message')
            ?? data_get($body, 'description')          // Telegram
            ?? data_get($body, 'errors.0.message')     // X
            ?? data_get($body, 'error.0.message');

        $apiMessage = is_string($apiMessage) ? static::redactString($apiMessage) : null;
        $detail     = static::summarise($response, 1500);
        $name       = ucfirst($platform);

        if (in_array($status, [401], true)) {
            return new PlatformException(
                "$name rejected the stored credentials. The access token is invalid or expired - reconnect the account.",
                'token_expired', false, $status, $detail, true
            );
        }

        if ($status === 403) {
            // Graph APIs report an expired token as 403/190 as often as 401.
            $subcode = (int) data_get($body, 'error.code', 0);
            if (in_array($subcode, [190, 102], true)) {
                return new PlatformException(
                    "$name session expired. Reconnect the account.",
                    'token_expired', false, $status, $detail, true
                );
            }
            return new PlatformException(
                "$name denied the request: the connected account is missing a required permission." . ($apiMessage ? " ($apiMessage)" : ''),
                'permission_denied', false, $status, $detail, true
            );
        }

        if ($status === 429) {
            $retryAfter = (int) $response->header('Retry-After');
            $e = PlatformException::rateLimited($platform, $retryAfter ?: null);
            $e->detail = $detail;
            return $e;
        }

        if ($status === 408 || $status >= 500) {
            return new PlatformException(
                "$name is temporarily unavailable (HTTP $status). The publish will be retried automatically.",
                'temporary_failure', true, $status, $detail
            );
        }

        if ($status === 404) {
            return new PlatformException(
                "$name could not find the target resource. It may have been deleted, or the connected account may no longer have access to it.",
                'not_found', false, $status, $detail
            );
        }

        return new PlatformException(
            $apiMessage
                ? "$name rejected the request: $apiMessage"
                : ($fallbackMessage ?: "$name rejected the request (HTTP $status)."),
            'rejected', false, $status, $detail
        );
    }

    /** Transport-level failures (DNS, TLS, timeout) are always worth retrying. */
    public static function classifyThrowable(string $platform, \Throwable $e): PlatformException {
        if ($e instanceof PlatformException) {
            return $e;
        }

        $message = static::redactString($e->getMessage());

        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return new PlatformException(
                ucfirst($platform) . ' could not be reached (network timeout). The publish will be retried automatically.',
                'network_error', true, null, $message
            );
        }

        return new PlatformException(
            ucfirst($platform) . ' request failed unexpectedly.',
            'unexpected_error', true, null, $message, false, $e
        );
    }

    /** Debug logging that is safe to leave enabled. */
    public static function log(string $platform, string $message, array $context = []): void {
        Log::channel(config('logging.default'))->info("[social:$platform] $message", static::redact($context));
    }
}
