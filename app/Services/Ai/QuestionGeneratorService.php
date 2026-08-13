<?php

namespace App\Services\Ai;

use App\Models\AiGeneratedQuestion;
use App\Models\AiGenerationSetting;
use App\Models\AiQuestionGeneration;
use App\Models\BankQuestion;
use App\Models\Category;

/**
 * Builds the prompt, calls the configured provider, and turns the response
 * into reviewable rows in ai_generated_questions.
 *
 * This service never writes to bank_questions - promotion is the sole job of
 * QuestionApprovalService, and only on explicit admin approval.
 */
class QuestionGeneratorService {
    /** Normalised-similarity threshold (percent) above which two questions are "the same". */
    const SIMILARITY_THRESHOLD = 85;

    /** Cap on how many existing questions are pulled in for similarity comparison. */
    const DUPLICATE_SCAN_LIMIT = 2000;

    public function __construct(private AiProviderFactory $factory) {}

    // ------------------------------------------------------------------ run

    /**
     * Executes a generation end to end. The generation row is created first so
     * that a failure is still recorded in history with its error, per §17.
     */
    public function run(array $input, ?int $adminId = null): AiQuestionGeneration {
        $settings = AiGenerationSetting::config();

        if (!$settings->enabled) {
            throw new AiProviderException(
                'The AI Question Generator is disabled. Enable it under AI Settings.',
                'disabled'
            );
        }

        $category    = Category::find($input['category_id']);
        $subCategory = !empty($input['sub_category_id']) ? Category::find($input['sub_category_id']) : null;

        $systemPrompt = $settings->system_prompt ?: AiGenerationSetting::DEFAULT_SYSTEM_PROMPT;
        $userPrompt   = $this->buildPrompt($input, $category, $subCategory, $settings);

        $generation = AiQuestionGeneration::create([
            'category_id'             => $input['category_id'],
            'sub_category_id'         => $input['sub_category_id'] ?? null,
            'quiz_id'                 => $input['quiz_id'] ?? null,
            'topic'                   => $input['topic'] ?? null,
            'difficulty'              => $input['difficulty'],
            'question_type'           => $input['question_type'],
            'language'                => $input['language'],
            'quantity'                => $input['quantity'],
            'provider'                => $settings->provider,
            'model'                   => $settings->model,
            'temperature'             => $settings->temperature,
            'prompt'                  => $userPrompt,
            'system_prompt'           => $systemPrompt,
            'additional_instructions' => $input['additional_instructions'] ?? null,
            'status'                  => AiQuestionGeneration::STATUS_GENERATING,
            'requested_count'         => $input['quantity'],
            'created_by'              => $adminId,
        ]);

        try {
            $provider = $this->factory->make($settings);
            $result   = $provider->generate($systemPrompt, $userPrompt);

            $generation->raw_response = json_encode($result->raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $generation->input_tokens  = $result->inputTokens;
            $generation->output_tokens = $result->outputTokens;
            $generation->total_tokens  = $result->totalTokens;
            $generation->estimated_cost = $this->estimateCost($provider->pricing(), $result);
            $generation->save();

            $questions = $this->parseResponse($result->text);
            $stored    = $this->storeQuestions($generation, $questions, $input);

            $generation->generated_count = $stored;
            $generation->status = $stored === 0
                ? AiQuestionGeneration::STATUS_FAILED
                : ($stored < $generation->requested_count
                    ? AiQuestionGeneration::STATUS_PARTIALLY_COMPLETED
                    : AiQuestionGeneration::STATUS_COMPLETED);

            if ($stored === 0) {
                $generation->error_message = 'The AI returned no usable questions. Every item failed validation.';
            }

            $generation->save();
            $generation->syncCounts();
        } catch (\Throwable $e) {
            $generation->status        = AiQuestionGeneration::STATUS_FAILED;
            $generation->error_message = $e->getMessage();
            $generation->save();

            throw $e;
        }

        return $generation->fresh();
    }

    /**
     * Regenerates a single question, holding category, sub-category, topic,
     * difficulty, language and type fixed. Returns the replaced row.
     */
    public function regenerateOne(AiGeneratedQuestion $question): AiGeneratedQuestion {
        $generation = $question->generation;
        $settings   = AiGenerationSetting::config();

        if ($question->isPublished()) {
            throw new AiProviderException(
                'This question has already been published to the Question Bank and cannot be regenerated.',
                'already_published'
            );
        }

        $category    = $generation->category;
        $subCategory = $generation->subCategory;

        $input = [
            'category_id'             => $generation->category_id,
            'sub_category_id'         => $generation->sub_category_id,
            'topic'                   => $generation->topic,
            'difficulty'              => $question->difficulty ?: $generation->difficulty,
            'question_type'           => $question->question_type ?: $generation->question_type,
            'language'                => $generation->language,
            'quantity'                => 1,
            'additional_instructions' => trim(
                ($generation->additional_instructions ?? '') . "\n\n" .
                'Do NOT repeat this question, produce a different one on the same topic: "' . $question->question . '"'
            ),
        ];

        $systemPrompt = $generation->system_prompt ?: AiGenerationSetting::DEFAULT_SYSTEM_PROMPT;
        $userPrompt   = $this->buildPrompt($input, $category, $subCategory, $settings);

        $provider = $this->factory->make($settings);
        $result   = $provider->generate($systemPrompt, $userPrompt);
        $parsed   = $this->parseResponse($result->text);

        if (empty($parsed)) {
            throw new AiProviderException(
                'The AI did not return a usable replacement question. Try again.',
                'empty_response'
            );
        }

        $normalised = $this->normalise($parsed[0], $input);
        $errors     = $this->validateQuestion($normalised);

        if ($errors) {
            throw new AiProviderException(
                'The replacement question failed validation: ' . implode(' ', $errors),
                'invalid_output'
            );
        }

        $question->fill([
            'question'          => $normalised['question'],
            'options'           => $normalised['options'],
            'correct_answer'    => $normalised['correct_answer'],
            'explanation'       => $normalised['explanation'],
            'difficulty'        => $normalised['difficulty'],
            'status'            => AiGeneratedQuestion::STATUS_PENDING_REVIEW,
            'validation_errors' => null,
            'reviewed_by'       => null,
            'reviewed_at'       => null,
        ]);

        // Re-run duplicate detection against the fresh text.
        $this->applyDuplicateFlags($question, $generation);
        $question->save();

        // Roll the accumulated token usage forward onto the generation.
        $generation->input_tokens  = (int) $generation->input_tokens + (int) $result->inputTokens;
        $generation->output_tokens = (int) $generation->output_tokens + (int) $result->outputTokens;
        $generation->total_tokens  = (int) $generation->total_tokens + (int) $result->totalTokens;
        $generation->save();
        $generation->syncCounts();

        return $question->fresh();
    }

    // --------------------------------------------------------------- prompt

    /** Assembles the user prompt from the admin's selections. */
    public function buildPrompt(array $input, ?Category $category, ?Category $subCategory, AiGenerationSetting $settings): string {
        $quantity   = (int) $input['quantity'];
        $type       = $input['question_type'];
        $difficulty = $input['difficulty'];
        $language   = ucfirst($input['language']);

        $categoryName = $category?->name ?? 'General Knowledge';
        $subName      = $subCategory?->name;
        $topic        = trim((string) ($input['topic'] ?? ''));
        $extra        = trim((string) ($input['additional_instructions'] ?? ''));

        $scope = "Category: {$categoryName}";
        if ($subName) {
            $scope .= "\nSub-category: {$subName}";
        }
        if ($topic !== '') {
            $scope .= "\nSpecific topic: {$topic}";
        }

        $optionRule = $type === 'true_false'
            ? 'Exactly 2 options keyed "A" and "B", where A is "True" and B is "False".'
            : 'Exactly 4 options keyed "A", "B", "C" and "D".';

        $difficultyGuide = match ($difficulty) {
            'easy'   => 'Straightforward recall a well-prepared beginner would answer confidently.',
            'medium' => 'Requires solid subject familiarity; distractors should be tempting.',
            'hard'   => 'Demands precise knowledge or multi-step reasoning.',
            'expert' => 'Olympiad or top-percentile difficulty; assumes deep specialist knowledge.',
            default  => 'Moderate difficulty.',
        };

        $prompt = <<<PROMPT
Generate exactly {$quantity} examination question(s).

{$scope}
Difficulty: {$difficulty} - {$difficultyGuide}
Question type: {$type}
Language: {$language}. Write the question text, all options and the explanation in {$language}.

Requirements for every question:
- {$optionRule}
- Exactly one correct option.
- "correct_answer" must be the letter key of the correct option.
- "explanation" must state why the correct answer is right, in 1-3 sentences. It must not be empty.
- "difficulty" must be one of: easy, medium, hard, expert.
- Questions must be distinct from one another. Do not paraphrase the same fact twice.
PROMPT;

        if ($extra !== '') {
            $prompt .= "\n\nAdditional instructions from the administrator (follow these strictly):\n{$extra}";
        }

        $prompt .= "\n\n" . $this->schemaBlock();

        return $prompt;
    }

    /** The strict JSON contract appended to every prompt. */
    private function schemaBlock(): string {
        return <<<'SCHEMA'
Return ONLY this JSON structure, with no surrounding text or markdown:

{
  "questions": [
    {
      "question": "string",
      "options": { "A": "string", "B": "string", "C": "string", "D": "string" },
      "correct_answer": "A",
      "explanation": "string",
      "difficulty": "easy|medium|hard|expert"
    }
  ]
}
SCHEMA;
    }

    // ---------------------------------------------------------------- parse

    /**
     * Decodes the model output into a list of raw question arrays.
     * Tolerates code fences and leading prose, but never invents content.
     */
    public function parseResponse(string $text): array {
        $clean = trim($text);

        // Strip ```json ... ``` fences some models emit despite instructions.
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        // Fall back to the outermost {...} span if there is stray prose around it.
        if (json_last_error() !== JSON_ERROR_NONE) {
            $start = strpos($clean, '{');
            $end   = strrpos($clean, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new AiProviderException(
                'The AI response was not valid JSON (' . json_last_error_msg() . '). The raw response is stored on this generation for inspection.',
                'invalid_json'
            );
        }

        // Accept {"questions": [...]} or a bare [...] array.
        $questions = $decoded['questions'] ?? (array_is_list($decoded) ? $decoded : null);

        if (!is_array($questions)) {
            throw new AiProviderException(
                'The AI response was valid JSON but contained no "questions" array.',
                'invalid_schema'
            );
        }

        return array_values(array_filter($questions, 'is_array'));
    }

    // ------------------------------------------------------------ normalise

    /** Coerces one raw AI item into the shape stored in ai_generated_questions. */
    public function normalise(array $raw, array $input): array {
        $options = [];
        $rawOptions = $raw['options'] ?? [];

        if (array_is_list($rawOptions)) {
            // Some models return ["...","...",...] instead of a keyed object.
            foreach (array_values($rawOptions) as $i => $text) {
                $letter = chr(65 + $i);
                $options[$letter] = $this->clean((string) $text);
            }
        } else {
            foreach ($rawOptions as $letter => $text) {
                $letter = strtoupper(trim((string) $letter));
                if ($letter !== '') {
                    $options[$letter] = $this->clean((string) $text);
                }
            }
        }

        $options = array_filter($options, fn($v) => $v !== '');

        $answer = strtoupper(trim((string) ($raw['correct_answer'] ?? $raw['answer'] ?? '')));
        // Tolerate "Option B" / "B)" style answers.
        if (preg_match('/\b([A-D])\b/', $answer, $m)) {
            $answer = $m[1];
        }

        $difficulty = strtolower(trim((string) ($raw['difficulty'] ?? $input['difficulty'])));
        if (!array_key_exists($difficulty, AiGenerationSetting::DIFFICULTIES)) {
            $difficulty = $input['difficulty'];
        }

        return [
            'question'       => $this->clean((string) ($raw['question'] ?? '')),
            'options'        => $options,
            'correct_answer' => $answer,
            'explanation'    => $this->clean((string) ($raw['explanation'] ?? '')),
            'difficulty'     => $difficulty,
            'question_type'  => $input['question_type'],
        ];
    }

    /** Strips markup and control characters from model output. */
    private function clean(string $value): string {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    // ------------------------------------------------------------- validate

    /** Returns a list of reasons the question is unusable; empty means valid. */
    public function validateQuestion(array $q): array {
        $errors = [];

        if (mb_strlen($q['question']) < 10) {
            $errors[] = 'Question text is missing or too short.';
        }

        $expected = $q['question_type'] === 'true_false' ? 2 : 4;
        if (count($q['options']) !== $expected) {
            $errors[] = "Expected exactly {$expected} options, got " . count($q['options']) . '.';
        }

        if (count(array_unique(array_map('mb_strtolower', $q['options']))) !== count($q['options'])) {
            $errors[] = 'Options are not distinct.';
        }

        if ($q['correct_answer'] === '') {
            $errors[] = 'Correct answer is missing.';
        } elseif (!array_key_exists($q['correct_answer'], $q['options'])) {
            $errors[] = "Correct answer '{$q['correct_answer']}' does not match any option key.";
        }

        if (trim($q['explanation']) === '') {
            $errors[] = 'Explanation is empty.';
        }

        if (!array_key_exists($q['difficulty'], AiGenerationSetting::DIFFICULTIES)) {
            $errors[] = "Difficulty '{$q['difficulty']}' is not valid.";
        }

        return $errors;
    }

    // -------------------------------------------------------------- storage

    /** Normalises, validates, dedupes and persists the batch. */
    private function storeQuestions(AiQuestionGeneration $generation, array $rawQuestions, array $input): int {
        $stored = 0;

        foreach ($rawQuestions as $index => $raw) {
            $q      = $this->normalise($raw, $input);
            $errors = $this->validateQuestion($q);

            // Items that fail hard validation are kept with their reasons so
            // the admin can see what the model produced, but marked rejected.
            $record = new AiGeneratedQuestion([
                'generation_id'     => $generation->id,
                'question'          => $q['question'] ?: '(empty)',
                'options'           => $q['options'],
                'correct_answer'    => $q['correct_answer'] ?: '?',
                'explanation'       => $q['explanation'],
                'difficulty'        => $q['difficulty'],
                'question_type'     => $q['question_type'],
                'status'            => $errors
                    ? AiGeneratedQuestion::STATUS_REJECTED
                    : AiGeneratedQuestion::STATUS_PENDING_REVIEW,
                'validation_errors' => $errors ? implode(' ', $errors) : null,
                'sort_order'        => $index + 1,
            ]);

            $this->applyDuplicateFlags($record, $generation);
            $record->save();

            if (!$errors) {
                $stored++;
            }
        }

        return $stored;
    }

    // ------------------------------------------------------------ duplicates

    /**
     * Flags the question against the existing bank, earlier questions in this
     * batch, and questions from previous AI generations (§11).
     */
    public function applyDuplicateFlags(AiGeneratedQuestion $record, AiQuestionGeneration $generation): void {
        $needle = $this->normaliseForCompare($record->question);

        if ($needle === '') {
            return;
        }

        $best      = null;
        $bestScore = 0;

        // 1. Existing question bank, scoped to the same category.
        foreach ($this->bankCandidates($generation->category_id) as $candidate) {
            $score = $this->similarity($needle, $this->normaliseForCompare($candidate->question_text));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = ['bank', $candidate->id];
            }
        }

        // 2. Other AI questions - this batch first, then previous generations.
        $others = AiGeneratedQuestion::where('id', '!=', $record->id ?? 0)
            ->whereHas('generation', fn($q) => $q->where('category_id', $generation->category_id))
            ->where('status', '!=', AiGeneratedQuestion::STATUS_REJECTED)
            ->latest('id')
            ->limit(self::DUPLICATE_SCAN_LIMIT)
            ->get(['id', 'question']);

        foreach ($others as $candidate) {
            $score = $this->similarity($needle, $this->normaliseForCompare($candidate->question));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = ['ai', $candidate->id];
            }
        }

        if ($best && $bestScore >= self::SIMILARITY_THRESHOLD) {
            $record->duplicate_flag   = true;
            $record->similarity_score = (int) round($bestScore);
            $record->status           = AiGeneratedQuestion::STATUS_DUPLICATE;

            if ($best[0] === 'bank') {
                $record->duplicate_question_id = $best[1];
            } else {
                $record->duplicate_generated_id = $best[1];
            }
        } else {
            $record->duplicate_flag          = false;
            $record->similarity_score        = null;
            $record->duplicate_question_id   = null;
            $record->duplicate_generated_id  = null;
        }
    }

    private function bankCandidates(?int $categoryId) {
        return BankQuestion::when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->latest('id')
            ->limit(self::DUPLICATE_SCAN_LIMIT)
            ->get(['id', 'question_text']);
    }

    private function normaliseForCompare(string $text): string {
        $text = mb_strtolower(strip_tags($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Percentage similarity, with an exact-match short circuit. */
    private function similarity(string $a, string $b): float {
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b) {
            return 100;
        }

        // similar_text is O(n^2); skip pairs too different in length to matter.
        $lenA = mb_strlen($a);
        $lenB = mb_strlen($b);
        if (min($lenA, $lenB) / max($lenA, $lenB) < 0.6) {
            return 0;
        }

        similar_text($a, $b, $percent);
        return $percent;
    }

    // ------------------------------------------------------------------ cost

    private function estimateCost(array $pricing, AiResult $result): ?float {
        if (!$pricing || !$result->hasUsage()) {
            return null;
        }

        $in  = (int) $result->inputTokens;
        $out = (int) $result->outputTokens;

        return round(
            ($in / 1_000_000) * ($pricing['input'] ?? 0)
            + ($out / 1_000_000) * ($pricing['output'] ?? 0),
            6
        );
    }
}
