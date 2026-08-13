<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGenerationSetting extends Model {
    protected $table = 'ai_generation_settings';

    protected $fillable = [
        'enabled', 'provider', 'model',
        'gemini_api_key', 'openai_api_key', 'anthropic_api_key',
        'temperature', 'max_tokens', 'request_timeout',
        'default_language', 'default_difficulty', 'default_question_type',
        'default_quantity', 'max_quantity',
        'system_prompt', 'default_user_prompt',
    ];

    protected $casts = [
        'enabled'           => 'boolean',
        'temperature'       => 'float',
        // Encrypted at rest. Combined with $hidden below, keys cannot reach
        // the frontend through model serialisation.
        'gemini_api_key'    => 'encrypted',
        'openai_api_key'    => 'encrypted',
        'anthropic_api_key' => 'encrypted',
    ];

    protected $hidden = ['gemini_api_key', 'openai_api_key', 'anthropic_api_key'];

    const PROVIDERS = [
        'gemini'    => 'Google Gemini',
        'openai'    => 'OpenAI',
        'anthropic' => 'Anthropic Claude',
    ];

    const LANGUAGES = ['english' => 'English', 'hindi' => 'Hindi'];

    /**
     * The generator offers "expert", which bank_questions.difficulty does not
     * accept. Expert questions are stored as expert here and downgraded to
     * hard when promoted - see QuestionApprovalService::bankDifficulty().
     */
    const DIFFICULTIES = [
        'easy'   => 'Easy',
        'medium' => 'Medium',
        'hard'   => 'Hard',
        'expert' => 'Expert',
    ];

    const QUESTION_TYPES = ['mcq' => 'MCQ (single answer)', 'true_false' => 'True / False'];

    const QUANTITIES = [5, 10, 20, 50, 100];

    public const DEFAULT_SYSTEM_PROMPT = <<<'PROMPT'
You are an expert examination question writer for competitive exams.

Write factually accurate, unambiguous, exam-oriented questions. Every question
must be answerable from the question text alone, with exactly one defensible
correct answer among the options. Distractors must be plausible but clearly
wrong to a well-prepared candidate.

Never invent statistics, dates, or attributions. If you are not confident a
fact is correct, choose a different question. Prefer stable, settled facts over
recent events that may become outdated.

Return ONLY valid JSON matching the requested schema. No markdown, no code
fences, no commentary outside the JSON.
PROMPT;

    /** The single settings row, created with defaults on first access. */
    public static function config(): self {
        return static::firstOrCreate([], [
            'enabled'       => false,
            'provider'      => 'gemini',
            'model'         => 'gemini-flash-latest',
            'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
        ]);
    }

    /** Returns the key for a provider without ever exposing it in views. */
    public function apiKeyFor(string $provider): ?string {
        $key = match ($provider) {
            'gemini'    => $this->gemini_api_key,
            'openai'    => $this->openai_api_key,
            'anthropic' => $this->anthropic_api_key,
            default     => null,
        };

        // Fall back to the key the legacy Exam generator already uses, so an
        // install that configured Gemini there works without re-entering it.
        if (!$key && $provider === 'gemini') {
            $key = gs('gemini_api_key') ?: null;
        }

        return $key ?: null;
    }

    public function hasKeyFor(string $provider): bool {
        return (bool) $this->apiKeyFor($provider);
    }
}
