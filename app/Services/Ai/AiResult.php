<?php

namespace App\Services\Ai;

/**
 * Provider-agnostic result of one generation call.
 *
 * `text` is the model's raw textual output (expected to be JSON).
 * `raw` is the complete decoded provider payload, stored for auditing.
 * Usage fields are null when the provider does not report them.
 */
class AiResult {
    public function __construct(
        public readonly string $text,
        public readonly array $raw = [],
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?int $totalTokens = null,
    ) {}

    public function hasUsage(): bool {
        return $this->inputTokens !== null || $this->outputTokens !== null;
    }
}
