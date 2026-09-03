<?php

namespace App\Services\Social\Support;

/** Outcome of "Test Connection" on an account card. */
class ConnectionResult {

    public function __construct(
        public bool $ok,
        public string $message,
        /** Safe, displayable facts: account name, follower count, scopes. */
        public array $details = [],
    ) {}

    public static function ok(string $message, array $details = []): self {
        return new self(true, $message, $details);
    }

    public static function fail(string $message, array $details = []): self {
        return new self(false, $message, $details);
    }
}
