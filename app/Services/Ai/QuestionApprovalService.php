<?php

namespace App\Services\Ai;

use App\Models\AiGeneratedQuestion;
use App\Models\AiQuestionGeneration;
use App\Models\BankOption;
use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;

/**
 * Promotes reviewed AI questions into the existing Question Bank.
 *
 * This is the only place in the AI module that writes to bank_questions /
 * bank_options, and every write runs inside a transaction.
 */
class QuestionApprovalService {
    /**
     * bank_questions.difficulty is ENUM('easy','medium','hard'). The generator
     * also offers "expert", so it is stored as expert on the AI side and
     * folded into hard at promotion time.
     */
    public static function bankDifficulty(string $difficulty): string {
        return $difficulty === 'expert' ? 'hard' : $difficulty;
    }

    /** The generator's "mcq" maps onto the bank's existing mcq_single type. */
    public static function bankQuestionType(string $type): string {
        return $type === 'mcq' ? 'mcq_single' : $type;
    }

    /**
     * Validates and promotes a set of AI questions. All-or-nothing: any
     * failure rolls back the whole batch so the bank is never left partial.
     *
     * @return array{imported:int, skipped:int, errors:array}
     */
    public function approve(AiQuestionGeneration $generation, array $questionIds, ?int $adminId = null): array {
        $questions = $generation->questions()
            ->whereIn('id', $questionIds)
            ->get();

        if ($questions->isEmpty()) {
            throw new \RuntimeException('No questions were selected for approval.');
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        DB::transaction(function () use ($questions, $generation, $adminId, &$imported, &$skipped, &$errors) {
            foreach ($questions as $question) {
                // Already in the bank - approving twice must not duplicate it.
                if ($question->isPublished()) {
                    $skipped++;
                    continue;
                }

                if ($question->blockedByDuplicate()) {
                    $errors[] = "Q{$question->sort_order} is flagged as a possible duplicate. Use \"Keep Anyway\" first if you want it imported.";
                    $skipped++;
                    continue;
                }

                $problems = $this->validateForBank($question, $generation);
                if ($problems) {
                    // A bad row fails the whole batch rather than silently
                    // importing a subset the admin did not review.
                    throw new \RuntimeException(
                        "Q{$question->sort_order} cannot be imported: " . implode(' ', $problems)
                    );
                }

                $this->promote($question, $generation, $adminId);
                $imported++;
            }
        });

        $generation->syncCounts();

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** Full pre-flight validation against the constraints of the bank tables. */
    public function validateForBank(AiGeneratedQuestion $question, AiQuestionGeneration $generation): array {
        $problems = [];

        $category = Category::find($generation->category_id);
        if (!$category) {
            $problems[] = 'the generation\'s category no longer exists.';
        } elseif ($category->parent_id !== null) {
            $problems[] = 'the generation\'s category is not a top-level category.';
        }

        if ($generation->sub_category_id) {
            $sub = Category::find($generation->sub_category_id);
            if (!$sub) {
                $problems[] = 'the sub-category no longer exists.';
            } elseif ($sub->parent_id !== $generation->category_id) {
                $problems[] = 'the sub-category does not belong to the selected category.';
            }
        }

        if (trim((string) $question->question) === '') {
            $problems[] = 'the question text is empty.';
        }

        $options = $question->optionList();
        $expected = $question->question_type === 'true_false' ? 2 : 4;

        if (count($options) !== $expected) {
            $problems[] = "it has " . count($options) . " options but {$expected} are required.";
        }

        if (!array_key_exists($question->correct_answer, $options)) {
            $problems[] = "the correct answer '{$question->correct_answer}' matches no option.";
        }

        if (trim((string) $question->explanation) === '') {
            $problems[] = 'the explanation is empty.';
        }

        if (!array_key_exists($question->difficulty, \App\Models\AiGenerationSetting::DIFFICULTIES)) {
            $problems[] = "the difficulty '{$question->difficulty}' is not valid.";
        }

        return $problems;
    }

    /** Creates the bank question and its options for one AI question. */
    private function promote(AiGeneratedQuestion $question, AiQuestionGeneration $generation, ?int $adminId): void {
        $bank = BankQuestion::create([
            'category_id'     => $generation->category_id,
            'sub_category_id' => $generation->sub_category_id,
            'question_type'   => self::bankQuestionType($question->question_type),
            'difficulty'      => self::bankDifficulty($question->difficulty),
            'question_text'   => $question->question,
            'explanation'     => $question->explanation,
            'default_marks'   => 1,
            'status'          => 1,
        ]);

        $correctOptionId = null;
        $sortOrder       = 0;

        foreach ($question->optionList() as $letter => $text) {
            $isCorrect = $letter === $question->correct_answer;
            $option = BankOption::create([
                'bank_question_id' => $bank->id,
                'option_text'      => $text,
                'is_correct'       => $isCorrect,
                'sort_order'       => $sortOrder++,
            ]);

            if ($isCorrect) {
                $correctOptionId = $option->id;
            }
        }

        $bank->correct_option_id = $correctOptionId;
        $bank->save();

        $question->question_id = $bank->id;
        $question->status      = AiGeneratedQuestion::STATUS_PUBLISHED;
        $question->reviewed_by = $adminId;
        $question->reviewed_at = now();
        $question->save();
    }

    /**
     * Attaches this generation's published questions to a quiz, appending
     * after any questions already there and skipping ones already attached.
     *
     * @return array{attached:int, skipped:int}
     */
    public function attachToQuiz(AiQuestionGeneration $generation, Quiz $quiz): array {
        $bankIds = $generation->questions()
            ->where('status', AiGeneratedQuestion::STATUS_PUBLISHED)
            ->whereNotNull('question_id')
            ->pluck('question_id');

        if ($bankIds->isEmpty()) {
            throw new \RuntimeException('No published questions are available to add. Approve some questions first.');
        }

        $attached = 0;
        $skipped  = 0;

        DB::transaction(function () use ($bankIds, $quiz, &$attached, &$skipped) {
            $existing = DB::table('quiz_bank_question')
                ->where('quiz_id', $quiz->id)
                ->pluck('bank_question_id')
                ->flip();

            $order = (int) DB::table('quiz_bank_question')
                ->where('quiz_id', $quiz->id)
                ->max('question_order');

            $rows = [];
            foreach ($bankIds as $bankId) {
                if ($existing->has($bankId)) {
                    $skipped++;
                    continue;
                }

                $rows[] = [
                    'quiz_id'          => $quiz->id,
                    'bank_question_id' => $bankId,
                    'question_order'   => ++$order,
                    'marks'            => $quiz->marks_per_correct ?: 1,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
                $attached++;
            }

            if ($rows) {
                DB::table('quiz_bank_question')->insert($rows);
            }

            $quiz->total_questions = $quiz->questions()->count();
            $quiz->save();
        });

        return ['attached' => $attached, 'skipped' => $skipped];
    }
}
