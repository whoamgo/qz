<?php

namespace App\Services\Ai;

/**
 * Carries an admin-facing explanation of why a generation call failed,
 * separated from the technical detail kept for the audit trail.
 */
class AiProviderException extends \RuntimeException {
    public function __construct(
        string $message,
        public readonly string $reason = 'provider_error',
        public readonly ?string $detail = null,
    ) {
        parent::__construct($message);
    }

    public static function auth(string $provider): self {
        return new self(
            "The {$provider} API rejected the configured API key. Check the key in AI Settings.",
            'auth'
        );
    }

    public static function rateLimit(string $provider): self {
        return new self(
            "The {$provider} API rate limit was reached. Wait a moment and try again, or reduce the number of questions.",
            'rate_limit'
        );
    }

    public static function timeout(string $provider, int $seconds): self {
        return new self(
            "The {$provider} API did not respond within {$seconds}s. Try generating fewer questions per request.",
            'timeout'
        );
    }

    public static function emptyResponse(string $provider): self {
        return new self(
            "The {$provider} API returned an empty response. This usually means the response hit the token limit before any content was produced - raise Max Tokens in AI Settings or request fewer questions.",
            'empty_response'
        );
    }

    public static function unavailable(string $provider, string $detail): self {
        return new self(
            "The {$provider} API is currently unavailable. {$detail}",
            'unavailable',
            $detail
        );
    }
}
