<?php

namespace App\Services\Social\Support;

use Exception;
use Throwable;

/**
 * A failure talking to a platform, classified so the publisher knows whether
 * retrying could possibly help.
 *
 * The message carried here is written for an admin to read ("Instagram access
 * token expired. Reconnect Instagram.") and is safe to render - construction
 * goes through the named constructors below, which never embed credentials.
 */
class PlatformException extends Exception {

    /** Machine-readable classification, stored on the failed target. */
    public string $errorCode;

    /** True when the same request might succeed later (429, 5xx, timeout). */
    public bool $retryable;

    /** HTTP status, when the failure came from a response. */
    public ?int $httpStatus;

    /** Redacted response excerpt for the attempts log. */
    public ?string $detail;

    /** True when the account must be reconnected before anything can work. */
    public bool $requiresReconnect;

    public function __construct(
        string $message,
        string $errorCode = 'platform_error',
        bool $retryable = false,
        ?int $httpStatus = null,
        ?string $detail = null,
        bool $requiresReconnect = false,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus ?: 0, $previous);
        $this->errorCode         = $errorCode;
        $this->retryable         = $retryable;
        $this->httpStatus        = $httpStatus;
        $this->detail            = $detail;
        $this->requiresReconnect = $requiresReconnect;
    }

    public static function notConfigured(string $platform): self {
        return new self(
            ucfirst($platform) . ' is not configured. Add its API credentials to the server environment, then connect the account.',
            'not_configured'
        );
    }

    public static function notConnected(string $platform): self {
        return new self(
            'No connected ' . ucfirst($platform) . ' account. Connect an account under Social Media → Accounts.',
            'not_connected'
        );
    }

    public static function tokenExpired(string $platform): self {
        return new self(
            ucfirst($platform) . ' access token expired. Reconnect ' . ucfirst($platform) . '.',
            'token_expired',
            false,
            401,
            null,
            true
        );
    }

    public static function permission(string $platform, ?string $detail = null): self {
        return new self(
            ucfirst($platform) . ' rejected the request because the connected account is missing a required permission. Reconnect the account and grant all requested permissions.',
            'permission_denied',
            false,
            403,
            $detail,
            true
        );
    }

    public static function rateLimited(string $platform, ?int $retryAfter = null): self {
        return new self(
            ucfirst($platform) . ' rate limit reached.' . ($retryAfter ? ' Retrying in ' . $retryAfter . 's.' : ' The publish will be retried automatically.'),
            'rate_limited',
            true,
            429
        );
    }

    public static function unsupported(string $platform, string $what): self {
        return new self(
            ucfirst($platform) . ' does not support ' . $what . '.',
            'unsupported'
        );
    }

    public static function invalidMedia(string $message): self {
        return new self($message, 'invalid_media');
    }

    public function toArray(): array {
        return [
            'code'      => $this->errorCode,
            'message'   => $this->getMessage(),
            'retryable' => $this->retryable,
            'status'    => $this->httpStatus,
        ];
    }
}
