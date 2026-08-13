<?php

namespace App\Services;

use App\Models\BankOption;
use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\QuizImport;
use App\Models\QuizImportRow;
use App\Models\QuizXpSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Quiz importer.
 *
 * Same pipeline as QuestionImportService — upload, stage, validate, review,
 * approve — but a batch here creates Quizzes with their questions attached.
 * One CSV row is one question; rows sharing a quiz_slug (or title) form a quiz.
 *
 * Nothing touches quizzes / bank_questions outside approve(), which runs in a
 * single transaction.
 */
class QuizImportService {
    const DISK          = 'local';
    const CHUNK_SIZE    = 200;
    const APPROVE_CHUNK = 500;

    const HEADERS = [
        'quiz_title', 'quiz_slug', 'quiz_description', 'category_id', 'quiz_type',
        'price', 'quiz_difficulty', 'time_limit', 'pass_percentage',
        'marks_per_correct', 'negative_marking', 'quiz_status',
        'question', 'question_type', 'option_a', 'option_b', 'option_c',
        'option_d', 'correct_answer', 'explanation', 'question_difficulty',
    ];

    const REQUIRED_HEADERS = [
        'quiz_title', 'category_id', 'question', 'question_type',
        'option_a', 'option_b', 'correct_answer',
    ];

    const QUESTION_TYPES  = ['mcq_single', 'mcq_multi', 'true_false'];
    const DIFFICULTIES    = ['easy', 'medium', 'hard'];
    const QUIZ_TYPES      = ['free', 'paid', 'subscription'];
    const QUIZ_STATUSES   = ['draft', 'published', 'archived'];
    const OPTION_LETTERS  = ['A', 'B', 'C', 'D'];

    private ?array $categoryIds = null;

    // ------------------------------------------------------------ csv helpers

    public static function readCsvLine($handle): array|false|null {
        return fgetcsv($handle, 0, ',', '"', '');
    }

    public static function writeCsvLine($handle, array $fields): void {
        fputcsv($handle, $fields, ',', '"', '', PHP_EOL);
    }

    public function absolutePath(QuizImport $import): string {
        return Storage::disk(self::DISK)->path($import->stored_file);
    }

    public function readHeaderMap(QuizImport $import): array {
        $handle = @fopen($this->absolutePath($import), 'r');
        if (!$handle) {
            throw new \RuntimeException('Uploaded file could not be opened for reading.');
        }

        $header = self::readCsvLine($handle);
        fclose($handle);

        if (!$header) {
            throw new \RuntimeException('The file appears to be empty.');
        }

        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);

        $map = [];
        foreach ($header as $i => $name) {
            $key = str_replace([' ', '-'], '_', strtolower(trim((string) $name)));
            if ($key !== '') { $map[$key] = $i; }
        }

        $missing = array_diff(self::REQUIRED_HEADERS, array_keys($map));
        if ($missing) {
            throw new \RuntimeException('Missing required column(s): ' . implode(', ', $missing) . '.');
        }

        return $map;
    }

    public function countDataRows(QuizImport $import): int {
        $handle = @fopen($this->absolutePath($import), 'r');
        if (!$handle) {
            throw new \RuntimeException('Uploaded file could not be opened for reading.');
        }

        $count = 0;
        self::readCsvLine($handle);
        while (($row = self::readCsvLine($handle)) !== false) {
            if (!$this->isBlankRow($row)) { $count++; }
        }
        fclose($handle);

        return $count;
    }

    // ------------------------------------------------------------- processing

    public function processChunk(QuizImport $import, int $limit = self::CHUNK_SIZE): QuizImport {
        $headerMap = $this->readHeaderMap($import);
        $handle    = @fopen($this->absolutePath($import), 'r');
        if (!$handle) {
            throw new \RuntimeException('Uploaded file could not be opened for reading.');
        }

        if ($import->file_cursor > 0) {
            fseek($handle, $import->file_cursor);
        } else {
            self::readCsvLine($handle);
        }

        $rowNumber = $import->processed_records;
        $staged    = [];
        $read      = 0;

        while ($read < $limit && ($raw = self::readCsvLine($handle)) !== false) {
            if ($this->isBlankRow($raw)) { continue; }
            $rowNumber++;
            $read++;
            $staged[] = $this->buildRow($raw, $headerMap, $rowNumber + 1);
        }

        $cursor = ftell($handle);
        $atEof  = feof($handle);
        fclose($handle);

        if ($staged) { $this->stageRows($import, $staged); }

        $import->file_cursor       = $cursor;
        $import->processed_records = $rowNumber;
        $import->status = ($atEof || $read === 0)
            ? QuizImport::STATUS_READY_FOR_REVIEW
            : QuizImport::STATUS_PROCESSING;
        $import->save();

        $this->refreshCounts($import);

        return $import->fresh();
    }

    /** Normalises one CSV line into the staging shape. */
    private function buildRow(array $raw, array $headerMap, int $rowNumber): array {
        $get = function (string $key) use ($raw, $headerMap): string {
            $i = $headerMap[$key] ?? null;
            if ($i === null || !array_key_exists($i, $raw)) { return ''; }
            return trim((string) $raw[$i]);
        };

        $title = $get('quiz_title');
        $slug  = $get('quiz_slug');
        $categoryRaw = $get('category_id');

        // Rows are grouped by slug when present, otherwise by title. Either way
        // the key is normalised so casing/spacing differences do not split a quiz.
        $key = $slug !== '' ? $slug : $title;
        $quizKey = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $key), '-'));

        return [
            'row_number'          => $rowNumber,
            'quiz_key'            => $quizKey ?: null,
            'quiz_title'          => $title ?: null,
            'quiz_slug'           => $slug ?: null,
            'quiz_description'    => $get('quiz_description') ?: null,
            'category_raw'        => $categoryRaw ?: null,
            'category_id'         => $this->resolveCategory($categoryRaw),
            'quiz_type'           => strtolower($get('quiz_type')) ?: null,
            'price'               => is_numeric($get('price')) ? (float) $get('price') : null,
            'quiz_difficulty'     => strtolower($get('quiz_difficulty')) ?: null,
            'time_limit'          => is_numeric($get('time_limit')) ? (int) $get('time_limit') : null,
            'pass_percentage'     => is_numeric($get('pass_percentage')) ? (int) $get('pass_percentage') : null,
            'marks_per_correct'   => is_numeric($get('marks_per_correct')) ? (float) $get('marks_per_correct') : null,
            'negative_marking'    => is_numeric($get('negative_marking')) ? (float) $get('negative_marking') : null,
            'quiz_status'         => strtolower($get('quiz_status')) ?: null,
            'question'            => $get('question'),
            'question_type'       => strtolower($get('question_type')) ?: null,
            'option_a'            => $get('option_a') ?: null,
            'option_b'            => $get('option_b') ?: null,
            'option_c'            => $get('option_c') ?: null,
            'option_d'            => $get('option_d') ?: null,
            'correct_answer'      => strtoupper(str_replace(' ', '', $get('correct_answer'))) ?: null,
            'explanation'         => $get('explanation') ?: null,
            'question_difficulty' => strtolower($get('question_difficulty')) ?: null,
        ];
    }

    /**
     * Accepts a numeric category id, or a category name/slug, and returns the
     * id of a matching TOP-LEVEL category. Sub-categories are rejected because
     * the public site is category-based.
     */
    private function resolveCategory(string $value): ?int {
        if ($value === '') { return null; }

        if ($this->categoryIds === null) {
            $this->categoryIds = [];
            Category::whereNull('parent_id')->get(['id', 'name', 'slug'])->each(function ($c) {
                $this->categoryIds['id:' . $c->id] = $c->id;
                $this->categoryIds['name:' . mb_strtolower(trim($c->name))] = $c->id;
                $this->categoryIds['slug:' . mb_strtolower(trim($c->slug))] = $c->id;
            });
        }

        if (ctype_digit($value)) {
            return $this->categoryIds['id:' . (int) $value] ?? null;
        }

        $needle = mb_strtolower(trim($value));
        return $this->categoryIds['name:' . $needle] ?? $this->categoryIds['slug:' . $needle] ?? null;
    }

    // -------------------------------------------------------------- staging

    private function stageRows(QuizImport $import, array $rows): void {
        $questions = array_values(array_filter(array_map(
            fn($r) => $r['question'] !== '' ? $r['question'] : null, $rows
        )));
        $slugs = array_values(array_filter(array_map(fn($r) => $r['quiz_key'], $rows)));

        $existingQuestions = $this->existingQuestionMap($questions);
        $existingQuizzes   = $this->existingQuizMap($rows);
        $seenQuestions     = $this->alreadyStagedQuestions($import, $questions);

        $now = now();
        $records = [];

        foreach ($rows as $row) {
            $errors = $this->validateRow($row);

            $duplicateReason = null;
            $dupQuizId = null;
            $dupQuestionId = null;

            // A quiz whose slug/title already exists in the quizzes table.
            $quizKey = $row['quiz_key'];
            if ($quizKey && isset($existingQuizzes[$quizKey])) {
                $duplicateReason = 'A quiz with this title/slug already exists';
                $dupQuizId = $existingQuizzes[$quizKey];
            }

            // A question already in the bank for this category.
            $qKey = $this->questionKey($row['question'], $row['category_id']);
            if (!$duplicateReason && $row['question'] !== '' && isset($existingQuestions[$qKey])) {
                $duplicateReason = 'This question already exists in the Question Bank';
                $dupQuestionId = $existingQuestions[$qKey];
            }

            // The same question repeated inside this file.
            if (!$duplicateReason && $row['question'] !== '' && isset($seenQuestions[$qKey])) {
                $duplicateReason = 'This question is repeated earlier in the same file';
            }

            if ($duplicateReason) {
                $status = QuizImportRow::STATUS_DUPLICATE;
            } elseif ($errors) {
                $status = QuizImportRow::STATUS_INVALID;
            } else {
                $status = QuizImportRow::STATUS_VALID;
                $seenQuestions[$qKey] = true;
            }

            $records[] = array_merge($row, [
                'import_id'             => $import->id,
                'validation_status'     => $status,
                'validation_errors'     => $errors ? json_encode($errors) : null,
                'duplicate_flag'        => (bool) $duplicateReason,
                'duplicate_reason'      => $duplicateReason,
                'duplicate_quiz_id'     => $dupQuizId,
                'duplicate_question_id' => $dupQuestionId,
                'processed_at'          => $now,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
        }

        foreach (array_chunk($records, 100) as $batch) {
            QuizImportRow::insert($batch);
        }
    }

    // ------------------------------------------------------------- validation

    /** Returns human-readable problems with a row; empty means valid. */
    public function validateRow(array $row): array {
        $errors = [];

        // ---- quiz level ----
        if (($row['quiz_title'] ?? '') === '' || $row['quiz_title'] === null) {
            $errors[] = 'Quiz title is required.';
        } elseif (mb_strlen($row['quiz_title']) < 3) {
            $errors[] = 'Quiz title is too short.';
        }

        if (($row['category_raw'] ?? '') === '' || $row['category_raw'] === null) {
            $errors[] = 'Category is required.';
        } elseif (!$row['category_id']) {
            $errors[] = "Category '{$row['category_raw']}' does not match a top-level category (use its id, name or slug).";
        }

        if (!empty($row['quiz_type']) && !in_array($row['quiz_type'], self::QUIZ_TYPES, true)) {
            $errors[] = "Quiz type '{$row['quiz_type']}' is invalid. Use free, paid or subscription.";
        }
        if (($row['quiz_type'] ?? '') === 'paid' && !($row['price'] > 0)) {
            $errors[] = 'A paid quiz needs a price greater than 0.';
        }
        if (!empty($row['quiz_difficulty']) && !in_array($row['quiz_difficulty'], self::DIFFICULTIES, true)) {
            $errors[] = "Quiz difficulty '{$row['quiz_difficulty']}' is invalid. Use easy, medium or hard.";
        }
        if (!empty($row['quiz_status']) && !in_array($row['quiz_status'], self::QUIZ_STATUSES, true)) {
            $errors[] = "Quiz status '{$row['quiz_status']}' is invalid. Use draft, published or archived.";
        }
        if ($row['pass_percentage'] !== null && ($row['pass_percentage'] < 0 || $row['pass_percentage'] > 100)) {
            $errors[] = 'Passing percentage must be between 0 and 100.';
        }

        // ---- question level ----
        $question = trim((string) ($row['question'] ?? ''));
        if ($question === '') {
            $errors[] = 'Question is required.';
        } elseif (mb_strlen($question) < 10) {
            $errors[] = 'Question must be at least 10 characters.';
        }

        $type = $row['question_type'] ?? null;
        if (!$type) {
            $errors[] = 'Question type is required.';
        } elseif (!in_array($type, self::QUESTION_TYPES, true)) {
            $errors[] = "Question type '{$type}' is invalid. Use one of: " . implode(', ', self::QUESTION_TYPES) . '.';
        }

        $qDiff = $row['question_difficulty'] ?? null;
        if ($qDiff && !in_array($qDiff, self::DIFFICULTIES, true)) {
            $errors[] = "Question difficulty '{$qDiff}' is invalid. Use easy, medium or hard.";
        }

        $filled = [];
        foreach (self::OPTION_LETTERS as $letter) {
            $v = trim((string) ($row['option_' . strtolower($letter)] ?? ''));
            if ($v !== '') { $filled[$letter] = $v; }
        }

        $minOptions = $type === 'true_false' ? 2 : 4;
        if (count($filled) < $minOptions) {
            $errors[] = $type === 'true_false'
                ? 'True/False questions require options A and B.'
                : 'All four options (A, B, C, D) are required.';
        }
        if (count(array_unique(array_map('mb_strtolower', $filled))) !== count($filled)) {
            $errors[] = 'Options must be distinct; duplicate option text found.';
        }

        $answer = trim((string) ($row['correct_answer'] ?? ''));
        if ($answer === '') {
            $errors[] = 'Correct answer is required.';
        } else {
            $letters = array_values(array_filter(explode(',', $answer)));
            $invalid = array_diff($letters, self::OPTION_LETTERS);
            if ($invalid) {
                $errors[] = 'Correct answer must reference option letters A-D, got: ' . implode(', ', $invalid) . '.';
            } else {
                foreach ($letters as $l) {
                    if (!isset($filled[$l])) { $errors[] = "Correct answer '{$l}' points at an empty option."; }
                }
                if ($type === 'mcq_multi' && count($letters) < 2) {
                    $errors[] = 'Multi-answer questions need at least two correct letters, e.g. "A,C".';
                }
                if ($type !== 'mcq_multi' && count($letters) > 1) {
                    $errors[] = 'Only multi-answer questions may list more than one correct letter.';
                }
            }
        }

        if (!empty($row['explanation']) && mb_strlen($row['explanation']) > 5000) {
            $errors[] = 'Explanation exceeds 5000 characters.';
        }

        return $errors;
    }

    /** Re-validates a single staged row after an admin edit. */
    public function revalidateRow(QuizImportRow $row): QuizImportRow {
        $row->category_id = $this->resolveCategory((string) $row->category_raw);

        $data = $row->only(array_merge(QuizImportRow::EDITABLE_FIELDS, ['category_id', 'price']));
        $errors = $this->validateRow($data);

        $row->validation_errors = $errors ?: null;
        $row->validation_status = $errors ? QuizImportRow::STATUS_INVALID : QuizImportRow::STATUS_VALID;
        $row->duplicate_flag    = false;
        $row->duplicate_reason  = null;
        $row->processed_at      = now();
        $row->save();

        $this->refreshCounts($row->import);

        return $row;
    }

    // --------------------------------------------------------------- approval

    /**
     * Creates every valid quiz with its questions, inside one transaction.
     * Any failure rolls the whole batch back.
     *
     * @return array{quizzes:int, questions:int}
     */
    public function approve(QuizImport $import): array {
        if (!$import->canApprove()) {
            throw new \RuntimeException('This import is not in a state that can be approved.');
        }

        $quizCount = 0;
        $questionCount = 0;

        try {
            DB::transaction(function () use ($import, &$quizCount, &$questionCount) {
                // Group the valid rows into quizzes, preserving file order.
                $groups = $import->validRows()->orderBy('row_number')->get()->groupBy('quiz_key');

                foreach ($groups as $key => $rows) {
                    $first = $rows->first();

                    // Re-validate at promotion time — categories may have changed.
                    foreach ($rows as $r) {
                        $problems = $this->validateRow(
                            $r->only(array_merge(QuizImportRow::EDITABLE_FIELDS, ['category_id', 'price']))
                        );
                        if ($problems) {
                            throw new \RuntimeException(
                                "Row {$r->row_number} failed re-validation: " . implode(' ', $problems)
                            );
                        }
                    }

                    $quiz = $this->createQuiz($first, $rows->count());
                    $quizCount++;

                    $order = 0;
                    foreach ($rows as $r) {
                        $question = $this->createQuestion($r, $quiz);
                        $questionCount++;

                        DB::table('quiz_bank_question')->insert([
                            'quiz_id'          => $quiz->id,
                            'bank_question_id' => $question->id,
                            'question_order'   => ++$order,
                            'marks'            => $quiz->marks_per_correct,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);

                        $r->quiz_id           = $quiz->id;
                        $r->bank_question_id  = $question->id;
                        $r->validation_status = QuizImportRow::STATUS_IMPORTED;
                        $r->save();
                    }

                    $quiz->total_questions = $order;
                    $quiz->save();
                }

                $import->imported_quizzes   = $quizCount;
                $import->imported_questions = $questionCount;
                $import->status             = QuizImport::STATUS_COMPLETED;
                $import->approved_at        = now();
                $import->completed_at       = now();
                $import->error_message      = null;
                $import->save();
            });
        } catch (\Throwable $e) {
            $import->status        = QuizImport::STATUS_FAILED;
            $import->error_message = $e->getMessage();
            $import->save();
            throw $e;
        }

        $import->refresh();

        return ['quizzes' => $quizCount, 'questions' => $questionCount];
    }

    private function createQuiz(QuizImportRow $row, int $questionCount): Quiz {
        $slug = $row->quiz_slug ?: $row->quiz_title;
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $slug), '-'));

        // Guarantee slug uniqueness without overwriting an existing quiz.
        $base = $slug;
        $i = 1;
        while (Quiz::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        $quiz = Quiz::create([
            'title'                => $row->quiz_title,
            'slug'                 => $slug,
            'description'          => $row->quiz_description,
            'category_id'          => $row->category_id,
            'sub_category_id'      => null,
            'quiz_type'            => $row->quiz_type ?: 'free',
            'price'                => $row->quiz_type === 'paid' ? ($row->price ?: 0) : 0,
            'difficulty'           => $row->quiz_difficulty ?: 'medium',
            'total_questions'      => $questionCount,
            'time_limit'           => $row->time_limit ?? 0,
            'pass_percentage'      => $row->pass_percentage ?? 0,
            'marks_per_correct'    => $row->marks_per_correct ?? 1,
            'negative_marking'     => $row->negative_marking ?? 0,
            'randomize_questions'  => false,
            'randomize_options'    => false,
            'show_result'          => true,
            'show_correct_answers' => true,
            'show_explanation'     => true,
            'status'               => $row->quiz_status ?: 'draft',
        ]);

        // Keep the gamification module's expectations intact.
        QuizXpSetting::firstOrCreate(
            ['quiz_id' => $quiz->id],
            ['xp_enabled' => true, 'use_global_rules' => true]
        );

        return $quiz;
    }

    private function createQuestion(QuizImportRow $row, Quiz $quiz): BankQuestion {
        $question = BankQuestion::create([
            'category_id'     => $row->category_id,
            'sub_category_id' => null,
            'question_type'   => $row->question_type,
            'difficulty'      => $row->question_difficulty ?: ($row->quiz_difficulty ?: 'medium'),
            'question_text'   => $row->question,
            'explanation'     => $row->explanation,
            'default_marks'   => $row->marks_per_correct ?? 1,
            'status'          => 1,
        ]);

        $correct = array_filter(explode(',', (string) $row->correct_answer));
        $firstCorrectId = null;
        $sort = 0;

        foreach ($row->optionMap() as $letter => $text) {
            if ($text === null || trim($text) === '') { continue; }

            $isCorrect = in_array($letter, $correct, true);
            $option = BankOption::create([
                'bank_question_id' => $question->id,
                'option_text'      => $text,
                'is_correct'       => $isCorrect,
                'sort_order'       => $sort++,
            ]);

            if ($isCorrect && $firstCorrectId === null) { $firstCorrectId = $option->id; }
        }

        $question->correct_option_id = $firstCorrectId;
        $question->save();

        return $question;
    }

    // -------------------------------------------------------------- internals

    public function refreshCounts(QuizImport $import): void {
        $counts = QuizImportRow::where('import_id', $import->id)
            ->selectRaw('validation_status, COUNT(*) as total')
            ->groupBy('validation_status')
            ->pluck('total', 'validation_status');

        $import->valid_records     = (int) ($counts[QuizImportRow::STATUS_VALID] ?? 0);
        $import->invalid_records   = (int) ($counts[QuizImportRow::STATUS_INVALID] ?? 0);
        $import->duplicate_records = (int) ($counts[QuizImportRow::STATUS_DUPLICATE] ?? 0);
        $import->failed_records    = (int) ($counts[QuizImportRow::STATUS_FAILED] ?? 0);

        $import->total_quizzes = (int) QuizImportRow::where('import_id', $import->id)
            ->whereNotNull('quiz_key')->distinct()->count('quiz_key');
        $import->valid_quizzes = (int) QuizImportRow::where('import_id', $import->id)
            ->where('validation_status', QuizImportRow::STATUS_VALID)
            ->whereNotNull('quiz_key')->distinct()->count('quiz_key');

        $import->save();
    }

    private function isBlankRow(?array $row): bool {
        if ($row === null || $row === [null]) { return true; }
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') { return false; }
        }
        return true;
    }

    private function questionKey(?string $question, ?int $categoryId): string {
        return mb_strtolower(trim((string) $question)) . '|' . ($categoryId ?? 0);
    }

    private function existingQuestionMap(array $questions): array {
        if (!$questions) { return []; }

        $map = [];
        BankQuestion::whereIn('question_text', $questions)
            ->get(['id', 'question_text', 'category_id'])
            ->each(function ($q) use (&$map) {
                $map[$this->questionKey($q->question_text, $q->category_id)] = $q->id;
            });

        return $map;
    }

    /** Existing quizzes keyed the same way rows are grouped. */
    private function existingQuizMap(array $rows): array {
        $keys = array_values(array_filter(array_map(fn($r) => $r['quiz_key'], $rows)));
        if (!$keys) { return []; }

        $map = [];
        Quiz::withTrashed()->get(['id', 'title', 'slug'])->each(function ($q) use (&$map) {
            foreach ([$q->slug, $q->title] as $candidate) {
                $k = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $candidate), '-'));
                if ($k !== '') { $map[$k] = $q->id; }
            }
        });

        return array_intersect_key($map, array_flip($keys));
    }

    private function alreadyStagedQuestions(QuizImport $import, array $questions): array {
        if (!$questions) { return []; }

        $map = [];
        QuizImportRow::where('import_id', $import->id)
            ->where('validation_status', QuizImportRow::STATUS_VALID)
            ->whereIn('question', $questions)
            ->get(['question', 'category_id'])
            ->each(function ($r) use (&$map) {
                $map[$this->questionKey($r->question, $r->category_id)] = true;
            });

        return $map;
    }
}
