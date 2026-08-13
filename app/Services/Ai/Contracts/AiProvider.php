<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiResult;

interface AiProvider {
    /**
     * Sends the prompt and returns the model's textual output.
     *
     * Implementations must throw AiProviderException with an admin-readable
     * message for any failure (auth, rate limit, timeout, empty response),
     * rather than returning a partial or fabricated result.
     */
    public function generate(string $systemPrompt, string $userPrompt): AiResult;

    /** Machine key, e.g. "gemini". */
    public function key(): string;

    /**
     * Per-million-token pricing used for cost estimates, as
     * ['input' => float, 'output' => float]. Empty when unknown.
     */
    public function pricing(): array;
}
