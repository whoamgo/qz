<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiResult;
use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProvider {
    const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(
        private string $apiKey,
        private string $model,
        private float $temperature,
        private int $maxTokens,
        private int $timeout,
    ) {}

    public function key(): string {
        return 'gemini';
    }

    public function pricing(): array {
        // USD per million tokens. Flash-tier pricing; adjust in AI Settings
        // documentation if the account is on a different tier.
        return ['input' => 0.30, 'output' => 2.50];
    }

    public function generate(string $systemPrompt, string $userPrompt): AiResult {
        try {
            $response = Http::withHeaders([
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $this->apiKey,
            ])
                ->timeout($this->timeout)
                ->post(sprintf(self::ENDPOINT, $this->model), [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
                    ],
                    'generationConfig' => [
                        'temperature'      => $this->temperature,
                        'maxOutputTokens'  => $this->maxTokens,
                        // Constrains the model to emit parseable JSON.
                        'responseMimeType' => 'application/json',
                    ],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw AiProviderException::timeout('Gemini', $this->timeout);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw AiProviderException::auth('Gemini');
        }

        if ($response->status() === 429) {
            throw AiProviderException::rateLimit('Gemini');
        }

        if (!$response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            throw AiProviderException::unavailable('Gemini', (string) $message);
        }

        $body      = $response->json() ?? [];
        $candidate = $body['candidates'][0] ?? null;
        $text      = $candidate['content']['parts'][0]['text'] ?? '';

        if (trim($text) === '') {
            // MAX_TOKENS with no text is the common cause; surface it plainly.
            $finish = $candidate['finishReason'] ?? 'unknown';
            if ($finish === 'MAX_TOKENS') {
                throw AiProviderException::emptyResponse('Gemini');
            }
            if ($finish === 'SAFETY' || $finish === 'PROHIBITED_CONTENT') {
                throw new AiProviderException(
                    'Gemini blocked this request under its safety filters. Rephrase the topic or additional instructions.',
                    'safety'
                );
            }
            throw AiProviderException::emptyResponse('Gemini');
        }

        $usage = $body['usageMetadata'] ?? [];

        return new AiResult(
            text: $text,
            raw: $body,
            inputTokens: $usage['promptTokenCount'] ?? null,
            outputTokens: $usage['candidatesTokenCount'] ?? null,
            totalTokens: $usage['totalTokenCount'] ?? null,
        );
    }
}
