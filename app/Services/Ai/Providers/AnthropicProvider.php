<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiResult;
use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;

/**
 * NOT VERIFIED AGAINST THE LIVE API.
 *
 * No Anthropic key was configured on this install, so this driver was written
 * to the documented Messages API contract but never executed end to end.
 * Treat the first real run as a smoke test.
 */
class AnthropicProvider implements AiProvider {
    const ENDPOINT    = 'https://api.anthropic.com/v1/messages';
    const API_VERSION = '2023-06-01';

    public function __construct(
        private string $apiKey,
        private string $model,
        private float $temperature,
        private int $maxTokens,
        private int $timeout,
    ) {}

    public function key(): string {
        return 'anthropic';
    }

    public function pricing(): array {
        return [];
    }

    public function generate(string $systemPrompt, string $userPrompt): AiResult {
        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'Content-Type'      => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post(self::ENDPOINT, [
                    'model'      => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'temperature' => $this->temperature,
                    // Anthropic takes the system prompt as a top-level field
                    // rather than as a message with role "system".
                    'system'     => $systemPrompt,
                    'messages'   => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw AiProviderException::timeout('Anthropic', $this->timeout);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw AiProviderException::auth('Anthropic');
        }

        if ($response->status() === 429) {
            throw AiProviderException::rateLimit('Anthropic');
        }

        if (!$response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            throw AiProviderException::unavailable('Anthropic', (string) $message);
        }

        $body = $response->json() ?? [];

        // Content is a list of blocks; concatenate the text ones.
        $text = collect($body['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        if (trim($text) === '') {
            throw AiProviderException::emptyResponse('Anthropic');
        }

        $usage = $body['usage'] ?? [];
        $in    = $usage['input_tokens'] ?? null;
        $out   = $usage['output_tokens'] ?? null;

        return new AiResult(
            text: $text,
            raw: $body,
            inputTokens: $in,
            outputTokens: $out,
            totalTokens: ($in !== null && $out !== null) ? $in + $out : null,
        );
    }
}
