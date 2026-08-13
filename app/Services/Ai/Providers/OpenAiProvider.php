<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiResult;
use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;

/**
 * NOT VERIFIED AGAINST THE LIVE API.
 *
 * No OpenAI key was configured on this install, so this driver was written to
 * the documented Chat Completions contract but never executed end to end.
 * Treat the first real run as a smoke test.
 */
class OpenAiProvider implements AiProvider {
    const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private string $apiKey,
        private string $model,
        private float $temperature,
        private int $maxTokens,
        private int $timeout,
    ) {}

    public function key(): string {
        return 'openai';
    }

    public function pricing(): array {
        return [];
    }

    public function generate(string $systemPrompt, string $userPrompt): AiResult {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post(self::ENDPOINT, [
                    'model'    => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature'     => $this->temperature,
                    'max_tokens'      => $this->maxTokens,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw AiProviderException::timeout('OpenAI', $this->timeout);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw AiProviderException::auth('OpenAI');
        }

        if ($response->status() === 429) {
            throw AiProviderException::rateLimit('OpenAI');
        }

        if (!$response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            throw AiProviderException::unavailable('OpenAI', (string) $message);
        }

        $body = $response->json() ?? [];
        $text = $body['choices'][0]['message']['content'] ?? '';

        if (trim($text) === '') {
            throw AiProviderException::emptyResponse('OpenAI');
        }

        $usage = $body['usage'] ?? [];

        return new AiResult(
            text: $text,
            raw: $body,
            inputTokens: $usage['prompt_tokens'] ?? null,
            outputTokens: $usage['completion_tokens'] ?? null,
            totalTokens: $usage['total_tokens'] ?? null,
        );
    }
}
