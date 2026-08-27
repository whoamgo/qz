<?php

namespace App\Services;

use App\Models\BankOption;
use App\Models\BankQuestion;
use App\Models\Category;
use App\Models\QuestionImport;
use App\Models\QuestionImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Parses an uploaded question file into the staging tables, validates each row,
 * and — only on explicit approval — promotes valid rows into the question bank.
 *
 * Nothing in this class writes to bank_questions or bank_options outside of
 * approve(), which runs entirely inside a single transaction.
 */
class QuestionImportService {
    /** Rows read from the file per processing request. */
    const CHUNK_SIZE = 200;

    /** Rows promoted per insert batch during approval. */
    const APPROVE_CHUNK = 500;

    const HEADERS = [
        'category', 'sub_category', 'question', 'question_type',
        'option_a', 'option_b', 'option_c', 'option_d',
        'correct_answer', 'explanation', 'difficulty',
    ];

    const REQUIRED_HEADERS = [
        'category', 'question', 'question_type',
        'option_a', 'option_b', 'correct_answer', 'difficulty',
    ];

    const QUESTION_TYPES = ['mcq_single', 'mcq_multi', 'true_false'];
    const DIFFICULTIES   = ['easy', 'medium', 'hard'];
    const OPTION_LETTERS  = ['A', 'B', 'C', 'D'];

    /** Lazily built name => id maps, keyed so lookups stay O(1) across a chunk. */
    private ?array $parentCategories = null;
    private ?array $childCategories  = null;

    // ------------------------------------------------------------ csv helpers

    /**
     * Wraps fgetcsv/fputcsv with an explicit empty escape character. PHP 8.4
     * deprecates relying on the default, and "" is the RFC 4180 behaviour —
     * a backslash in question text stays a literal backslash.
     */
    public static function readCsvLine($handle): array|false|null {
        return fgetcsv($handle, 0, ',', '"', '');
    }

    public static function writeCsvLine($handle, array $fields): void {
        fputcsv($handle, $fields, ',', '"', '', PHP_EOL);
    }

    // ---------------------------------------------------------------- reading

    /**
     * Import files live on the private 'local' disk, never the default disk —
     * this install sets FILESYSTEM_DISK=public, which is web-reachable.
     */
    const DISK = 'local';

    public function absolutePath(QuestionImport $import): string {
        return Storage::disk(self::DISK)->path($import->stored_file);
    }

    /**
     * Reads the header row and returns a header-name => column-index map.
     * Throws when the file is unreadable or a required column is absent.
     */
    public function readHeaderMap(QuestionImport $import): array {
        $path = $this->absolutePath($import);
        $handle = @fopen($path, 'r');
        if (!$handle) {
            throw new \RuntimeException('Uploaded file could not be opened for reading.');
        }

        $header = self::readCsvLine($handle);
        fclose($handle);

        if (!$header) {
            throw new \RuntimeException('The file appears to be empty.');
        }

        // Strip a UTF-8 BOM from the first cell, which Excel adds on CSV export.
        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);

        $map = [];
        foreach ($header as $index => $name) {
            $key = str_replace([' ', '-'], '_', strtolower(trim((string) $name)));
            if ($key !== '') {
                $map[$key] = $index;
            }
        }

        $missing = array_diff(self::REQUIRED_HEADERS, array_keys($map));
        if ($missing) {
            throw new \RuntimeException('Missing required column(s): ' . implode(', ', $missing) . '.');
        }

        return $map;
    }

    /** Counts data rows (excluding the header) without holding the file in memory. */
    public function countDataRows(QuestionImport $import): int {
        $handle = @fopen($this->absolutePath($import), 'r');
        if (!$handle) {
            throw new \RuntimeException('Uploaded file could not be opened for reading.');
        }

        $count = 0;
        self::readCsvLine($handle); // discard header
        while (($row = self::readCsvLine($handle)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }
            $count++;
        }
        fclose($handle);

        return $count;
    }

    // ------------------------------------------------------------- processing

    /**
     * Reads and stages the next chunk of rows, resuming from the stored byte
     * cursor. Returns the refreshed import so the caller can report progress.
     */
    public function processChunk(QuestionImport $import, int $limit = self::CHUNK_SIZE): QuestionImport {
        $headerMap = $this->readHeaderMap($import);
        $handle    = @fopen($this->absolutePath($import), 'r');
        if (!$handle) {
            throw new \RuntimeException('Uploaded file could not be opened for reading.');
        }

        if ($import->file_cursor > 0) {
            fseek($handle, $import->file_cursor);
        } else {
            self::readCsvLine($handle); // skip header on the first pass
        }

        $rowNumber = $import->processed_records;
        $staged    = [];
        $read      = 0;

        while ($read < $limit && ($raw = self::readCsvLine($handle)) !== false) {
            if ($this->isBlankRow($raw)) {
                continue;
            }

            $rowNumber++;
            $read++;
            // +1 so row_number matches the spreadsheet line the admin sees,
            // where line 1 is the header.
            $staged[] = $this->buildRow($import, $raw, $headerMap, $rowNumber + 1);
        }

        $cursor  = ftell($handle);
        $atEof   = feof($handle);
        fclose($handle);

        if ($staged) {
            $this->stageRows($import, $staged);
        }

        $import->file_cursor      = $cursor;
        $import->processed_records = $rowNumber;
        $import->status = $atEof || $read === 0
            ? QuestionImport::STATUS_READY_FOR_REVIEW
            : QuestionImport::STATUS_PROCESSING;
        $import->save();

        $this->refreshCounts($import);

        return $import->fresh();
    }

    /**
     * Validates a chunk against the database and inserts it into staging.
     * Duplicate detection runs per chunk with two bulk queries rather than
     * per-row lookups.
     */
    private function stageRows(QuestionImport $import, array $rows): void {
        $questions = array_values(array_filter(array_map(
            fn($r) => $r['question'] !== '' ? $r['question'] : null,
            $rows
        )));

        $existing = $this->existingQuestionMap($questions);
        $seen     = $this->alreadyStagedMap($import, $questions);

        $now     = now();
        $records = [];

        foreach ($rows as $row) {
            $errors = $this->validateRow($row);

            $duplicateId = null;
            $key = $this->duplicateKey($row['question'], $row['category_id']);

            if ($row['question'] !== '') {
                if (isset($existing[$key])) {
                    $duplicateId = $existing[$key];
                } elseif (isset($seen[$key])) {
                    // A duplicate of an earlier row in this same file.
                    $duplicateId = null;
                }
            }

            $isDuplicate = $duplicateId !== null || isset($seen[$key]);

            if ($isDuplicate) {
                $status = QuestionImportRow::STATUS_DUPLICATE;
            } elseif ($errors) {
                $status = QuestionImportRow::STATUS_INVALID;
            } else {
                $status = QuestionImportRow::STATUS_VALID;
                // Only non-duplicate, valid rows claim the key, so a later
                // identical row is flagged against this one.
                $seen[$key] = true;
            }

            $records[] = [
                'import_id'             => $import->id,
                'row_number'            => $row['row_number'],
                'category_name'         => $row['category_name'],
                'sub_category_name'     => $row['sub_category_name'],
                'category_id'           => $row['category_id'],
                'sub_category_id'       => $row['sub_category_id'],
                'question'              => $row['question'],
                'question_type'         => $row['question_type'],
                'option_a'              => $row['option_a'],
                'option_b'              => $row['option_b'],
                'option_c'              => $row['option_c'],
                'option_d'              => $row['option_d'],
                'correct_answer'        => $row['correct_answer'],
                'explanation'           => $row['explanation'],
                'difficulty'            => $row['difficulty'],
                'validation_status'     => $status,
                'validation_errors'     => $errors ? json_encode($errors) : null,
                'duplicate_flag'        => $isDuplicate,
                'duplicate_question_id' => $duplicateId,
                'processed_at'          => $now,
                'created_at'            => $now,
                'updated_at'            => $now,
            ];
        }

        foreach (array_chunk($records, 100) as $batch) {
            QuestionImportRow::insert($batch);
        }
    }

    /** Normalises a row's raw cells into the staging shape. */
    private function buildRow(QuestionImport $import, array $raw, array $headerMap, int $rowNumber): array {
        $get = function (string $key) use ($raw, $headerMap): string {
            $index = $headerMap[$key] ?? null;
            if ($index === null || !array_key_exists($index, $raw)) {
                return '';
            }
            return trim((string) $raw[$index]);
        };

        $categoryName    = $get('category');
        $subCategoryName = $get('sub_category');

        $categoryId    = $this->resolveParentCategory($categoryName);
        $subCategoryId = $subCategoryName !== ''
            ? $this->resolveChildCategory($subCategoryName, $categoryId)
            : null;

        return [
            'row_number'        => $rowNumber,
            'category_name'     => $categoryName ?: null,
            'sub_category_name' => $subCategoryName ?: null,
            'category_id'       => $categoryId,
            'sub_category_id'   => $subCategoryId,
            'question'          => $get('question'),
            'question_type'     => strtolower($get('question_type')) ?: null,
            'option_a'          => $get('option_a') ?: null,
            'option_b'          => $get('option_b') ?: null,
            'option_c'          => $get('option_c') ?: null,
            'option_d'          => $get('option_d') ?: null,
            'correct_answer'    => strtoupper(str_replace(' ', '', $get('correct_answer'))) ?: null,
            'explanation'       => $get('explanation') ?: null,
            'difficulty'        => strtolower($get('difficulty')) ?: null,
        ];
    }

    // ------------------------------------------------------------- validation

    /**
     * Returns a list of human-readable problems with the row. An empty list
     * means the row is safe to promote.
     */
    public function validateRow(array $row): array {
        $errors = [];

        if (($row['category_name'] ?? '') === '' || $row['category_name'] === null) {
            $errors[] = 'Category is required.';
        } elseif (!$row['category_id']) {
            $errors[] = "Category '{$row['category_name']}' does not exist as a top-level category.";
        }

        if (!empty($row['sub_category_name']) && !$row['sub_category_id']) {
            $errors[] = "Sub-category '{$row['sub_category_name']}' does not exist under the given category.";
        }

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

        $difficulty = $row['difficulty'] ?? null;
        if (!$difficulty) {
            $errors[] = 'Difficulty is required.';
        } elseif (!in_array($difficulty, self::DIFFICULTIES, true)) {
            $errors[] = "Difficulty '{$difficulty}' is invalid. Use easy, medium or hard.";
        }

        // Which option columns must be filled depends on the question type.
        $filled = [];
        foreach (self::OPTION_LETTERS as $letter) {
            $value = trim((string) ($row['option_' . strtolower($letter)] ?? ''));
            if ($value !== '') {
                $filled[$letter] = $value;
            }
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

        // Correct answer must name option letters that actually carry text.
        $answerRaw = trim((string) ($row['correct_answer'] ?? ''));
        if ($answerRaw === '') {
            $errors[] = 'Correct answer is required.';
        } else {
            $letters = array_values(array_filter(explode(',', $answerRaw)));
            $invalid = array_diff($letters, self::OPTION_LETTERS);

            if ($invalid) {
                $errors[] = 'Correct answer must reference option letters A-D, got: ' . implode(', ', $invalid) . '.';
            } else {
                foreach ($letters as $letter) {
                    if (!isset($filled[$letter])) {
                        $errors[] = "Correct answer '{$letter}' points at an empty option.";
                    }
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

    /** Re-runs validation for a single staged row, used after an admin edit. */
    public function revalidateRow(QuestionImportRow $row): QuestionImportRow {
        $row->category_id = $this->resolveParentCategory((string) $row->category_name);
        $row->sub_category_id = $row->sub_category_name
            ? $this->resolveChildCategory((string) $row->sub_category_name, $row->category_id)
            : null;

        $errors = $this->validateRow($row->only(array_merge(
            ['row_number', 'category_name', 'sub_category_name', 'category_id', 'sub_category_id'],
            ['question', 'question_type', 'option_a', 'option_b', 'option_c', 'option_d'],
            ['correct_answer', 'explanation', 'difficulty']
        )));

        $duplicateId = $this->findExistingDuplicate($row->question, $row->category_id, $row->id);
        $stagedClash = $this->findStagedDuplicate($row);

        if ($duplicateId || $stagedClash) {
            $row->validation_status     = QuestionImportRow::STATUS_DUPLICATE;
            $row->duplicate_flag        = true;
            $row->duplicate_question_id = $duplicateId;
        } else {
            $row->validation_status = $errors
                ? QuestionImportRow::STATUS_INVALID
                : QuestionImportRow::STATUS_VALID;
            $row->duplicate_flag        = false;
            $row->duplicate_question_id = null;
        }

        $row->validation_errors = $errors ?: null;
        $row->processed_at      = now();
        $row->save();

        $this->refreshCounts($row->import);

        return $row;
    }

    // -------------------------------------------------------------- approval

    /**
     * Promotes every valid staged row into the question bank inside one
     * transaction. Any failure rolls the whole thing back, leaving the
     * question bank untouched and the import marked failed.
     */
    public function approve(QuestionImport $import): QuestionImport {
        if (!$import->canApprove()) {
            throw new \RuntimeException('This import is not in a state that can be approved.');
        }

        try {
            DB::transaction(function () use ($import) {
                $imported = 0;

                $import->validRows()->orderBy('id')
                    ->chunkById(self::APPROVE_CHUNK, function ($rows) use (&$imported) {
                        foreach ($rows as $row) {
                            // Requirement: validate again at promotion time, since
                            // categories may have changed since staging.
                            $errors = $this->validateRow($row->only([
                                'row_number', 'category_name', 'sub_category_name',
                                'category_id', 'sub_category_id', 'question', 'question_type',
                                'option_a', 'option_b', 'option_c', 'option_d',
                                'correct_answer', 'explanation', 'difficulty',
                            ]));

                            if ($errors) {
                                throw new \RuntimeException(
                                    "Row {$row->row_number} failed re-validation: " . implode(' ', $errors)
                                );
                            }
                        }

                        $imported += $this->promoteChunk($rows);
                    });

                $import->imported_records = $imported;
                $import->status           = QuestionImport::STATUS_COMPLETED;
                $import->approved_at      = now();
                $import->completed_at     = now();
                $import->error_message    = null;
                $import->save();
            });
        } catch (\Throwable $e) {
            // The transaction has already rolled back; record why outside of it.
            $import->status        = QuestionImport::STATUS_FAILED;
            $import->error_message = $e->getMessage();
            $import->save();

            throw $e;
        }

        return $import->fresh();
    }

    /**
     * Promotes a whole chunk of rows, batching the option inserts rather than
     * issuing four per question. Questions are still inserted individually
     * because their auto-increment ids are needed to attach options.
     *
     * Returns the number of questions created.
     */
    private function promoteChunk($rows): int {
        $now            = now();
        $optionPayload  = [];
        $questionIds    = [];
        $rowIdToQuestion = [];

        foreach ($rows as $row) {
            $question = BankQuestion::create([
                'category_id'     => $row->category_id,
                'sub_category_id' => $row->sub_category_id,
                'question_type'   => $row->question_type,
                'difficulty'      => $row->difficulty,
                'question_text'   => $row->question,
                'explanation'     => $row->explanation,
                'default_marks'   => 1,
                'status'          => 1,
            ]);

            $questionIds[]              = $question->id;
            $rowIdToQuestion[$row->id]  = $question->id;

            $correctLetters = array_filter(explode(',', (string) $row->correct_answer));
            $sortOrder      = 0;

            foreach ($row->optionMap() as $letter => $text) {
                if ($text === null || trim($text) === '') {
                    continue;
                }

                $optionPayload[] = [
                    'bank_question_id' => $question->id,
                    'option_text'      => $text,
                    'is_correct'       => in_array($letter, $correctLetters, true),
                    'sort_order'       => $sortOrder++,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
        }

        foreach (array_chunk($optionPayload, 500) as $batch) {
            BankOption::insert($batch);
        }

        // Resolve each question's first correct option in one query, then write
        // the pointers back with a single CASE update instead of one per row.
        $correctIds = BankOption::whereIn('bank_question_id', $questionIds)
            ->where('is_correct', true)
            ->orderBy('sort_order')
            ->get(['id', 'bank_question_id'])
            ->groupBy('bank_question_id')
            ->map(fn($group) => $group->first()->id);

        if ($correctIds->isNotEmpty()) {
            $cases = [];
            $bindings = [];
            foreach ($correctIds as $questionId => $optionId) {
                $cases[] = 'WHEN ? THEN ?';
                $bindings[] = $questionId;
                $bindings[] = $optionId;
            }

            DB::update(
                'UPDATE bank_questions SET correct_option_id = CASE id ' . implode(' ', $cases) . ' END WHERE id IN (' . implode(',', array_fill(0, $correctIds->count(), '?')) . ')',
                array_merge($bindings, $correctIds->keys()->all())
            );
        }

        // Mark the staged rows as promoted, again as one statement per chunk.
        foreach ($rowIdToQuestion as $rowId => $questionId) {
            QuestionImportRow::where('id', $rowId)->update([
                'bank_question_id'  => $questionId,
                'validation_status' => QuestionImportRow::STATUS_IMPORTED,
                'updated_at'        => $now,
            ]);
        }

        return count($questionIds);
    }

    // -------------------------------------------------------------- internals

    public function refreshCounts(QuestionImport $import): void {
        $counts = QuestionImportRow::where('import_id', $import->id)
            ->selectRaw('validation_status, COUNT(*) as total')
            ->groupBy('validation_status')
            ->pluck('total', 'validation_status');

        $import->valid_records     = (int) ($counts[QuestionImportRow::STATUS_VALID] ?? 0);
        $import->invalid_records   = (int) ($counts[QuestionImportRow::STATUS_INVALID] ?? 0);
        $import->duplicate_records = (int) ($counts[QuestionImportRow::STATUS_DUPLICATE] ?? 0);
        $import->failed_records    = (int) ($counts[QuestionImportRow::STATUS_FAILED] ?? 0);
        $import->save();
    }

    private function isBlankRow(?array $row): bool {
        if ($row === null || $row === [null]) {
            return true;
        }
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    private function duplicateKey(?string $question, ?int $categoryId): string {
        return mb_strtolower(trim((string) $question)) . '|' . ($categoryId ?? 0);
    }

    /** Bulk-loads question bank matches for a chunk, keyed for O(1) lookup. */
    private function existingQuestionMap(array $questions): array {
        if (!$questions) {
            return [];
        }

        $map = [];
        BankQuestion::whereIn('question_text', $questions)
            ->get(['id', 'question_text', 'category_id'])
            ->each(function ($q) use (&$map) {
                $map[$this->duplicateKey($q->question_text, $q->category_id)] = $q->id;
            });

        return $map;
    }

    /** Rows from earlier chunks of this same import, so cross-chunk repeats are caught. */
    private function alreadyStagedMap(QuestionImport $import, array $questions): array {
        if (!$questions) {
            return [];
        }

        $map = [];
        QuestionImportRow::where('import_id', $import->id)
            ->where('validation_status', QuestionImportRow::STATUS_VALID)
            ->whereIn('question', $questions)
            ->get(['question', 'category_id'])
            ->each(function ($r) use (&$map) {
                $map[$this->duplicateKey($r->question, $r->category_id)] = true;
            });

        return $map;
    }

    private function findExistingDuplicate(?string $question, ?int $categoryId, int $ignoreRowId): ?int {
        if (!$question) {
            return null;
        }

        return BankQuestion::where('question_text', $question)
            ->where('category_id', $categoryId)
            ->value('id');
    }

    private function findStagedDuplicate(QuestionImportRow $row): bool {
        if (!$row->question) {
            return false;
        }

        return QuestionImportRow::where('import_id', $row->import_id)
            ->where('id', '!=', $row->id)
            ->where('question', $row->question)
            ->where('category_id', $row->category_id)
            ->whereIn('validation_status', [
                QuestionImportRow::STATUS_VALID,
                QuestionImportRow::STATUS_IMPORTED,
            ])
            ->exists();
    }

    private function resolveParentCategory(string $name): ?int {
        if ($name === '') {
            return null;
        }

        if ($this->parentCategories === null) {
            $this->parentCategories = Category::whereNull('parent_id')
                ->pluck('id', 'name')
                ->mapWithKeys(fn($id, $catName) => [mb_strtolower(trim($catName)) => $id])
                ->all();
        }

        return $this->parentCategories[mb_strtolower(trim($name))] ?? null;
    }

    private function resolveChildCategory(string $name, ?int $parentId): ?int {
        if ($name === '' || !$parentId) {
            return null;
        }

        if ($this->childCategories === null) {
            $this->childCategories = [];
            Category::whereNotNull('parent_id')
                ->get(['id', 'name', 'parent_id'])
                ->each(function ($cat) {
                    $this->childCategories[$cat->parent_id . '|' . mb_strtolower(trim($cat->name))] = $cat->id;
                });
        }

        return $this->childCategories[$parentId . '|' . mb_strtolower(trim($name))] ?? null;
    }
}
