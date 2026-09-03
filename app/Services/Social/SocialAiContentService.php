<?php

namespace App\Services\Social;

use App\Models\AiGenerationSetting;
use App\Models\Social\SocialSetting;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\Str;

/**
 * The optional AI content assistant.
 *
 * Reuses the panel's existing AI provider layer, so there is one place that
 * holds model keys and one settings screen that manages them.
 *
 * Nothing generated here is ever published on its own. The service returns
 * suggestions, the wizard renders them into editable fields, and only what the
 * admin saves is used. `ai_auto_publish` exists in settings but is off by
 * default and is still subject to the approval workflow.
 */
class SocialAiContentService {

    /** Cap on how much source text is sent, to bound cost and latency. */
    const MAX_SOURCE_CHARS = 6000;

    public function __construct(protected AiProviderFactory $factory) {}

    public function isEnabled(): bool {
        $settings = SocialSetting::config();
        $ai       = AiGenerationSetting::config();

        return $settings->ai_enabled && $ai->enabled && $ai->hasKeyFor($ai->provider);
    }

    /** Why the generator is unavailable, for an honest empty state. */
    public function unavailableReason(): ?string {
        $settings = SocialSetting::config();
        $ai       = AiGenerationSetting::config();

        if (!$settings->ai_enabled) {
            return 'The AI content assistant is switched off in Social Media → Settings.';
        }
        if (!$ai->enabled) {
            return 'AI generation is switched off in Quiz Manager → AI Settings.';
        }
        if (!$ai->hasKeyFor($ai->provider)) {
            return 'No API key is configured for ' . (AiGenerationSetting::PROVIDERS[$ai->provider] ?? $ai->provider) . '.';
        }

        return null;
    }

    /**
     * Generates platform-specific copy from a piece of source material.
     *
     * @param array $source ['type' => 'quiz|question|blog|text|url', 'title' => .., 'body' => .., 'url' => ..]
     * @param array $platforms platform keys to write for
     * @return array platform => ['title' => .., 'caption' => .., ...] plus 'hashtags' and 'cta'
     *
     * @throws AiProviderException with an admin-readable message.
     */
    public function generate(array $source, array $platforms, array $options = []): array {
        if ($reason = $this->unavailableReason()) {
            throw new AiProviderException($reason, 'disabled');
        }

        $settings = AiGenerationSetting::config();
        $provider = $this->factory->make($settings);

        $result = $provider->generate(
            $this->systemPrompt(),
            $this->userPrompt($source, $platforms, $options)
        );

        $parsed = $this->parseJson($result->text);

        if (!$parsed) {
            throw new AiProviderException(
                'The AI returned a response that could not be read as content. Try again, or write the copy manually.',
                'unparsable_response'
            );
        }

        SocialAuditLogger::record('ai.generate', [
            'description' => 'Generated social copy for ' . implode(', ', $platforms),
            'context'     => [
                'provider'  => $settings->provider,
                'model'     => $settings->model,
                'platforms' => $platforms,
                'tokens'    => ['in' => $result->inputTokens, 'out' => $result->outputTokens],
            ],
        ]);

        return $this->normalise($parsed, $platforms);
    }

    /* --------------------------------------------------------------- Prompts */

    protected function systemPrompt(): string {
        return <<<'PROMPT'
You are a social media copywriter for Quiz Mitra, an Indian competitive-exam quiz
platform (SSC, Banking, Railway, UPSC, general knowledge and current affairs).

Write copy that a real person would stop scrolling for. Rules:

- Write for each platform's own conventions and character limits. Do not reuse
  the same sentence across every platform.
- Never invent facts, statistics, dates or claims about the quiz. Use only what
  the source material states. If a detail is not given, leave it out.
- Never promise prizes, guaranteed results or exam questions you cannot verify.
- Hashtags must be relevant and specific. No hashtag spam, no unrelated trends.
- Indian English. Plain, direct language. No emoji walls - at most two or three,
  and only where they help.
- Do not include the URL inside the caption text: it is added separately.

Return ONLY valid JSON matching the requested schema. No markdown, no code
fences, no commentary outside the JSON.
PROMPT;
    }

    protected function userPrompt(array $source, array $platforms, array $options): string {
        $limits = collect($platforms)->map(function ($platform) {
            $caps  = PlatformCapability::for($platform);
            $limit = $caps['limits']['caption_max'] ?? 1000;
            $tags  = $caps['limits']['hashtag_max'] ?? 5;

            return "- {$platform}: caption max {$limit} characters, max {$tags} hashtags";
        })->implode("\n");

        $body     = Str::limit(strip_tags((string) ($source['body'] ?? '')), self::MAX_SOURCE_CHARS, '');
        $language = $options['language'] ?? 'English';
        $tone     = $options['tone'] ?? 'energetic but factual';

        $schema = collect($platforms)->mapWithKeys(fn ($p) => [$p => $this->schemaFor($p)])->all();
        $schema['hashtags'] = ['#example', '#tags'];
        $schema['cta']      = 'short call to action';

        return implode("\n\n", array_filter([
            'SOURCE MATERIAL',
            'Type: ' . ($source['type'] ?? 'text'),
            $source['title'] ?? null ? 'Title: ' . $source['title'] : null,
            $source['url'] ?? null ? 'URL (for context only, do not put it in the caption): ' . $source['url'] : null,
            $body ? "Content:\n" . $body : null,

            'REQUIREMENTS',
            'Language: ' . $language,
            'Tone: ' . $tone,
            "Platform limits:\n" . $limits,

            'Return JSON with exactly this shape (omit nothing):',
            json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]));
    }

    /** The fields each platform's tab actually has, so the AI fills all of them. */
    protected function schemaFor(string $platform): array {
        return match ($platform) {
            \App\Constants\SocialStatus::YOUTUBE => [
                'title'       => 'video title, max 100 characters',
                'description' => 'full video description',
                'tags'        => ['keyword one', 'keyword two'],
                'short_title' => 'punchier title if published as a Short',
            ],
            \App\Constants\SocialStatus::INSTAGRAM => [
                'caption'       => 'instagram caption',
                'reel_caption'  => 'shorter caption if published as a Reel',
                'first_comment' => 'text for the first comment',
            ],
            \App\Constants\SocialStatus::X => [
                'caption' => 'post text within the character limit',
                'thread'  => ['optional follow-up post', 'another'],
            ],
            \App\Constants\SocialStatus::LINKEDIN => [
                'caption' => 'professional post, context-led',
            ],
            \App\Constants\SocialStatus::TELEGRAM => [
                'caption'     => 'telegram message',
                'button_text' => 'short button label',
            ],
            default => ['caption' => 'post text for this platform'],
        };
    }

    /* ---------------------------------------------------------------- Output */

    /**
     * Extracts JSON from the model's reply.
     *
     * Models sometimes wrap JSON in a code fence or add a sentence before it
     * despite instructions, so the outermost object is located rather than
     * assuming the whole reply parses.
     */
    protected function parseJson(string $text): ?array {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Clamps whatever came back to the real platform limits.
     *
     * The model is told the limits but is not bound by them, and a caption two
     * characters over would be rejected at publish time.
     */
    protected function normalise(array $parsed, array $platforms): array {
        $out = [
            'hashtags' => $this->cleanHashtags($parsed['hashtags'] ?? []),
            'cta'      => Str::limit((string) ($parsed['cta'] ?? ''), 120, ''),
        ];

        foreach ($platforms as $platform) {
            $data  = (array) ($parsed[$platform] ?? []);
            $limit = PlatformCapability::limit($platform, 'caption_max', 1000);
            $tags  = PlatformCapability::limit($platform, 'hashtag_max', 5);

            $clean = [
                'caption'  => Str::limit(trim((string) ($data['caption'] ?? '')), $limit, ''),
                'hashtags' => array_slice($out['hashtags'], 0, max(0, (int) $tags)),
            ];

            foreach (['title', 'description', 'first_comment', 'button_text', 'short_title', 'reel_caption'] as $field) {
                if (!empty($data[$field])) {
                    $clean[$field] = trim((string) $data[$field]);
                }
            }

            if (!empty($clean['title'])) {
                $clean['title'] = Str::limit($clean['title'], PlatformCapability::limit($platform, 'title_max', 200), '');
            }
            if (!empty($clean['description'])) {
                $clean['description'] = Str::limit($clean['description'], PlatformCapability::limit($platform, 'description_max', 5000), '');
            }
            if (!empty($data['tags']) && is_array($data['tags'])) {
                $clean['tags'] = array_slice(array_map(fn ($t) => ltrim(trim((string) $t), '#'), $data['tags']), 0, 15);
            }
            if (!empty($data['thread']) && is_array($data['thread'])) {
                $clean['thread'] = array_slice(array_map(fn ($t) => Str::limit(trim((string) $t), $limit, ''), $data['thread']), 0, 10);
            }

            $out[$platform] = $clean;
        }

        return $out;
    }

    protected function cleanHashtags($input): array {
        return \App\Models\Social\SocialHashtagGroup::normalise($input);
    }
}
