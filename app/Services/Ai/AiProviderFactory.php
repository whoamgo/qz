<?php

namespace App\Services\Ai;

use App\Models\AiGenerationSetting;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\AnthropicProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;

class AiProviderFactory {
    /**
     * Builds the configured driver. The API key is read here, server-side,
     * and handed straight to the driver - it never travels further up.
     */
    public function make(AiGenerationSetting $settings, ?string $provider = null, ?string $model = null): AiProvider {
        $provider = $provider ?: $settings->provider;
        $model    = $model ?: $settings->model;
        $apiKey   = $settings->apiKeyFor($provider);

        if (!$apiKey) {
            $label = AiGenerationSetting::PROVIDERS[$provider] ?? $provider;
            throw new AiProviderException(
                "No API key is configured for {$label}. Add one under Quiz Manager → AI Question Generator → AI Settings.",
                'missing_key'
            );
        }

        $args = [
            $apiKey,
            $model,
            (float) $settings->temperature,
            (int) $settings->max_tokens,
            (int) $settings->request_timeout,
        ];

        return match ($provider) {
            'gemini'    => new GeminiProvider(...$args),
            'openai'    => new OpenAiProvider(...$args),
            'anthropic' => new AnthropicProvider(...$args),
            default     => throw new AiProviderException("Unknown AI provider '{$provider}'.", 'unknown_provider'),
        };
    }
}
