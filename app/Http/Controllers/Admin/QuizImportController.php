<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\QuizImport;
use App\Models\QuizImportRow;
use App\Services\QuizImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuizImportController extends Controller {
    public function __construct(private QuizImportService $service) {}

    public function index() {
        $pageTitle = 'Import Quizzes';
        $imports = QuizImport::with('admin')
            ->searchable(['file_name'])
            ->when(request('status'), fn($q) => $q->where('status', request('status')))
            ->latest()
            ->paginate(getPaginate());

        $categories = Category::whereNull('parent_id')->where('status', 1)->orderBy('name')->get();

        return view('admin.quiz_import.index', compact('pageTitle', 'imports', 'categories'));
    }

    public function history() {
        $pageTitle = 'Quiz Import History';
        $imports = QuizImport::with('admin')
            ->searchable(['file_name'])
            ->when(request('status'), fn($q) => $q->where('status', request('status')))
            ->latest()
            ->paginate(getPaginate());

        return view('admin.quiz_import.history', compact('pageTitle', 'imports'));
    }

    /** Sample CSV showing two quizzes of two questions each. */
    public function template(): StreamedResponse {
        $category = Category::whereNull('parent_id')->where('status', 1)->orderBy('id')->first();
        $catId    = $category?->id ?? 1;

        // Pick a live category (and one of its sub-categories, if it has any)
        // so the downloaded template is valid for THIS installation.
        $category = Category::whereNull('parent_id')->where('status', 1)->orderBy('id')->first();
        $catId    = $category?->id ?? 1;
        $sub      = $category
            ? Category::where('parent_id', $category->id)->where('status', 1)->orderBy('id')->first()
            : null;
        $subId    = $sub?->id ?? '';

        $sample = [
            ['SAMPLE Quiz One - replace this', 'sample-quiz-one', 'Short description shown on the quiz page.',
             $catId, $subId, 'free', '0', 'medium', '15', '0', '60', '1', '0', 'draft',
             'Which Indian state has the longest coastline?', 'mcq_single',
             'Tamil Nadu', 'Gujarat', 'Andhra Pradesh', 'Kerala', 'B',
             'Gujarat has the longest coastline of any Indian state, at roughly 1600 km.', 'medium'],

            ['SAMPLE Quiz One - replace this', 'sample-quiz-one', 'Short description shown on the quiz page.',
             $catId, $subId, 'free', '0', 'medium', '15', '0', '60', '1', '0', 'draft',
             'The Tropic of Cancer passes through India.', 'true_false',
             'True', 'False', '', '', 'A',
             'Leave options C and D empty for true_false rows.', 'easy'],

            ['SAMPLE Quiz Two - replace this', 'sample-quiz-two', 'A second quiz in the same file.',
             $catId, $subId, 'free', '0', 'hard', '20', '10', '50', '2', '0.5', 'draft',
             'Which two of these are Himalayan rivers?', 'mcq_multi',
             'Ganga', 'Godavari', 'Yamuna', 'Krishna', 'A,C',
             'question_limit 10 means each attempt serves 10 random questions from this quiz.', 'hard'],
        ];

        return response()->streamDownload(function () use ($sample) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            QuizImportService::writeCsvLine($out, QuizImportService::HEADERS);
            foreach ($sample as $row) {
                QuizImportService::writeCsvLine($out, $row);
            }
            fclose($out);
        }, 'quiz-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function upload(Request $request) {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt',
        ], [
            'file.mimes' => 'The file must be a CSV.',
            'file.max'   => 'The file may not be larger than 10 MB.',
        ]);

        $file = $request->file('file');
        $path = $file->store('quiz-imports', QuizImportService::DISK);

        $import = QuizImport::create([
            'admin_id'    => auth('admin')->id(),
            'file_name'   => $file->getClientOriginalName(),
            'stored_file' => $path,
            'file_type'   => strtolower($file->getClientOriginalExtension() ?: 'csv'),
            'status'      => QuizImport::STATUS_UPLOADED,
        ]);

        try {
            $this->service->readHeaderMap($import);
            $import->total_records = $this->service->countDataRows($import);

            if ($import->total_records === 0) {
                throw new \RuntimeException('The file contains a header but no data rows.');
            }

            $import->save();
        } catch (\Throwable $e) {
            $import->status        = QuizImport::STATUS_VALIDATION_FAILED;
            $import->error_message = $e->getMessage();
            $import->save();

            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }

        return to_route('admin.quiz-import.preview', $import->id);
    }

    /** AJAX: stage the next chunk and report live progress. */
    public function process(Request $request, $id) {
        $import = QuizImport::findOrFail($id);

        if (!$import->isProcessing()) {
            return response()->json(['success' => true, 'done' => true, 'data' => $this->payload($import)]);
        }

        try {
            $import = $this->service->processChunk($import);
        } catch (\Throwable $e) {
            $import->status        = QuizImport::STATUS_VALIDATION_FAILED;
            $import->error_message = $e->getMessage();
            $import->save();

            return response()->json([
                'success' => false, 'message' => $e->getMessage(), 'data' => $this->payload($import),
            ], 422);
        }

        return response()->json([
            'success' => true, 'done' => !$import->isProcessing(), 'data' => $this->payload($import),
        ]);
    }

    public function status($id) {
        return response()->json(['success' => true, 'data' => $this->payload(QuizImport::findOrFail($id))]);
    }

    private function payload(QuizImport $import): array {
        return [
            'id'         => $import->id,
            'status'     => $import->status,
            'percent'    => $import->progressPercent(),
            'total'      => $import->total_records,
            'processed'  => $import->processed_records,
            'valid'      => $import->valid_records,
            'invalid'    => $import->invalid_records,
            'duplicate'  => $import->duplicate_records,
            'failed'     => $import->failed_records,
            'quizzes'    => $import->total_quizzes,
            'validQuizzes' => $import->valid_quizzes,
            'error'      => $import->error_message,
            'canApprove' => $import->canApprove(),
        ];
    }

    public function preview($id) {
        $import = QuizImport::with('admin')->findOrFail($id);
        $pageTitle = 'Quiz Import Preview: ' . $import->file_name;

        $filter  = request('status');
        $allowed = [
            QuizImportRow::STATUS_VALID, QuizImportRow::STATUS_INVALID,
            QuizImportRow::STATUS_DUPLICATE, QuizImportRow::STATUS_IMPORTED,
            QuizImportRow::STATUS_REMOVED,
        ];

        // Rows are shown grouped by quiz, which is how they will be created.
        $rows = $import->rows()
            ->with(['category', 'quiz'])
            ->when(in_array($filter, $allowed, true), fn($q) => $q->where('validation_status', $filter))
            ->orderBy('row_number')
            ->get();

        $groups = $rows->groupBy('quiz_key');

        // Extra breakdown for the review screen. Computed from the staged rows,
        // so it reflects exactly what will be created on approval.
        $all   = $import->rows()->get();
        $stats = [
            'rows'        => $all->count(),
            'quizzes'     => $all->pluck('quiz_key')->filter()->unique()->count(),
            'questions'   => $all->whereIn('validation_status', ['valid', 'imported'])->count(),
            'categories'  => $all->pluck('category_id')->filter()->unique()->values()->all(),
            'subCategories' => $all->pluck('sub_category_id')->filter()->unique()->values()->all(),
            'invalid'     => $all->where('validation_status', 'invalid')->count(),
            'duplicates'  => $all->where('duplicate_flag', true)->count(),
            'missingFields' => $all->filter(fn($r) => collect($r->validation_errors ?? [])
                ->contains(fn($e) => str_contains(strtolower($e), 'required')))->count(),
        ];

        return view('admin.quiz_import.preview', compact('pageTitle', 'import', 'groups', 'filter', 'stats'));
    }

    public function updateRow(Request $request, $id, $rowId) {
        $import = QuizImport::findOrFail($id);

        if (!$import->isReviewable()) {
            $notify[] = ['error', 'Rows can only be edited while the import is awaiting review.'];
            return back()->withNotify($notify);
        }

        $request->validate([
            'quiz_title'          => 'required|string|max:255',
            'category_raw'        => 'required|string|max:100',
            'question'            => 'required|string',
            'question_type'       => 'required|in:mcq_single,mcq_multi,true_false',
            'correct_answer'      => 'required|string|max:10',
            'quiz_difficulty'     => 'nullable|in:easy,medium,hard',
            'question_difficulty' => 'nullable|in:easy,medium,hard',
            'quiz_type'           => 'nullable|in:free,paid,subscription',
            'quiz_status'         => 'nullable|in:draft,published,archived',
        ]);

        $row = QuizImportRow::where('import_id', $import->id)->findOrFail($rowId);
        $row->fill($request->only(QuizImportRow::EDITABLE_FIELDS));
        $row->correct_answer = strtoupper(str_replace(' ', '', (string) $request->correct_answer));
        $row->save();

        $row = $this->service->revalidateRow($row);

        $notify[] = $row->validation_status === QuizImportRow::STATUS_VALID
            ? ['success', "Row {$row->row_number} is now valid."]
            : ['warning', "Row {$row->row_number} is still invalid: " . $row->errorList()];

        return back()->withNotify($notify);
    }

    public function removeRow($id, $rowId) {
        $import = QuizImport::findOrFail($id);

        if (!$import->isReviewable()) {
            $notify[] = ['error', 'Rows can only be removed while the import is awaiting review.'];
            return back()->withNotify($notify);
        }

        $row = QuizImportRow::where('import_id', $import->id)->findOrFail($rowId);
        $row->validation_status = QuizImportRow::STATUS_REMOVED;
        $row->save();

        $this->service->refreshCounts($import);

        $notify[] = ['success', "Row {$row->row_number} removed from this import."];
        return back()->withNotify($notify);
    }

    public function approve(Request $request, $id) {
        $import = QuizImport::findOrFail($id);

        if (!$import->canApprove()) {
            $notify[] = ['error', 'This import has no valid rows to approve, or is not awaiting review.'];
            return back()->withNotify($notify);
        }

        $expected = (int) $request->input('expected_valid');
        if ($expected && $expected !== $import->valid_records) {
            $notify[] = ['error', "The valid-row count changed from {$expected} to {$import->valid_records}. Review again before approving."];
            return back()->withNotify($notify);
        }

        try {
            $result = $this->service->approve($import);
        } catch (\Throwable $e) {
            $notify[] = ['error', 'Import failed and was rolled back. No quizzes were created. ' . $e->getMessage()];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', "{$result['quizzes']} quiz(zes) created with {$result['questions']} question(s)."];
        return to_route('admin.quiz-import.preview', $import->id)->withNotify($notify);
    }

    public function cancel($id) {
        $import = QuizImport::findOrFail($id);

        if ($import->status === QuizImport::STATUS_COMPLETED) {
            $notify[] = ['error', 'A completed import cannot be cancelled.'];
            return back()->withNotify($notify);
        }

        $import->status = QuizImport::STATUS_CANCELLED;
        $import->save();

        $notify[] = ['success', 'Import cancelled.'];
        return back()->withNotify($notify);
    }

    public function destroy($id) {
        $import = QuizImport::findOrFail($id);

        $disk = Storage::disk(QuizImportService::DISK);
        if ($import->stored_file && $disk->exists($import->stored_file)) {
            $disk->delete($import->stored_file);
        }

        $import->rows()->delete();
        $import->forceDelete();

        $notify[] = ['success', 'Import deleted. Quizzes already created were kept.'];
        return to_route('admin.quiz-import.index')->withNotify($notify);
    }


    /**
     * Builds an AI prompt for generating import-ready quiz questions.
     *
     * The column list is read from QuizImportService::HEADERS at request time,
     * never hard-coded here — so if the importer's schema changes, every prompt
     * generated afterwards describes the new schema automatically.
     */
    public function generatePrompt(Request $request) {
        $validator = validator($request->all(), [
            'category_id'     => 'required|integer|exists:categories,id',
            'sub_category_id' => 'nullable|integer|exists:categories,id',
            'question_count'  => 'required|integer|min:1|max:5000',
            'question_type'   => 'required|in:' . implode(',', QuizImportService::QUESTION_TYPES),
        ], [
            'category_id.required'    => 'Please choose a category.',
            'question_count.required' => 'Please enter how many questions you need.',
            'question_count.max'      => 'Please request 5000 questions or fewer per prompt.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $category = Category::find($request->category_id);

        if ($category->parent_id !== null) {
            return response()->json(['success' => false, 'message' => 'Please choose a top-level category.'], 422);
        }

        // Mirrors the importer's own rule: a category that HAS sub-categories
        // must have one chosen, otherwise the generated CSV would fail import.
        $hasChildren = Category::where('parent_id', $category->id)->where('status', 1)->exists();
        $sub = null;

        if ($hasChildren) {
            if (!$request->sub_category_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This category has sub-categories, so please choose one.',
                ], 422);
            }

            $sub = Category::where('id', $request->sub_category_id)
                ->where('parent_id', $category->id)->first();

            if (!$sub) {
                return response()->json([
                    'success' => false,
                    'message' => 'That sub-category does not belong to the selected category.',
                ], 422);
            }
        } elseif ($request->sub_category_id) {
            return response()->json([
                'success' => false,
                'message' => 'This category has no sub-categories, so leave the sub-category blank.',
            ], 422);
        }

        $count = (int) $request->question_count;
        $type  = $request->question_type;

        return response()->json([
            'success' => true,
            'summary' => [
                'category'      => $category->name,
                'category_id'   => $category->id,
                'sub_category'  => $sub?->name,
                'sub_category_id' => $sub?->id,
                'count'         => $count,
                'type'          => $type,
                'columns'       => count(QuizImportService::HEADERS),
            ],
            'filename' => 'ai-prompt-' . $category->slug . ($sub ? '-' . $sub->slug : '') . '-' . $count . '.txt',
            'prompt'   => $this->buildPromptText($category, $sub, $count, $type),
        ]);
    }

    /** Assembles the prompt text from live schema + the admin's choices. */
    private function buildPromptText(Category $category, ?Category $sub, int $count, string $type): string {
        $headers = QuizImportService::HEADERS;
        $topic   = $sub?->name ?? $category->name;
        $subLine = $sub
            ? "Sub-category ID must be {$sub->id} ({$sub->name}) in every row."
            : "Leave sub_category_id EMPTY in every row — this category has no sub-categories.";

        $typeLabel = [
            'mcq_single' => 'multiple-choice questions with exactly ONE correct option',
            'mcq_multi'  => 'multiple-choice questions with TWO OR MORE correct options',
            'true_false' => 'True/False questions',
        ][$type] ?? $type;

        $answerRule = match ($type) {
            'mcq_multi'  => 'correct_answer must list the correct letters separated by commas, e.g. "A,C". At least two letters.',
            'true_false' => 'option_a must be "True", option_b must be "False", and option_c / option_d must be EMPTY. correct_answer is a single letter, A or B.',
            default      => 'correct_answer must be a single letter: A, B, C or D.',
        };

        $columnList = '';
        foreach ($headers as $i => $h) {
            $columnList .= sprintf("%2d. %s\n", $i + 1, $h);
        }

        $sample        = implode(',', $headers);
        $headers_count = count($headers);
        $subValue      = $sub
            ? "Always {$sub->id}."
            : 'Leave empty — this category has no sub-categories.';

        return <<<PROMPT
Create exactly {$count} high-quality {$typeLabel} about "{$topic}" under the "{$category->name}" category.

CATEGORY AND SUB-CATEGORY
Category ID must be {$category->id} ({$category->name}) in every row.
{$subLine}
Every question must be directly and specifically about {$topic}. Do not drift into
adjacent topics.

HOW MANY
Generate exactly {$count} questions — no more, no fewer.
Every question must be unique. Do not repeat or paraphrase a question you have
already written.

OUTPUT FORMAT
Return the result as CSV only. No commentary before or after the CSV.
The first line must be this exact header row:

{$sample}

There are {$headers_count} columns. Use these exact column names, in exactly this
order. Do not add, remove, rename, reorder or translate any column:

{$columnList}
FIELD RULES
- quiz_title            The quiz these questions belong to. Rows sharing a title
                        are grouped into one quiz on import.
- quiz_slug             Lowercase, hyphenated version of quiz_title. Same for every
                        row of the same quiz.
- category_id           Always {$category->id}.
- sub_category_id       {$subValue}
- question_type         Always "{$type}".
- option_a … option_d   Four distinct, plausible options. No "Option A" style
                        placeholders — write real answer text.
- correct_answer        {$answerRule}
- explanation           One to three sentences saying why the answer is correct.
                        Never leave this empty.
- question_difficulty   One of: easy, medium, hard.
- quiz_difficulty       One of: easy, medium, hard.
- quiz_type             One of: free, paid, subscription. Use "free" unless told
                        otherwise. If "paid", price must be greater than 0.
- quiz_status           One of: draft, published, archived.
- time_limit            Whole minutes. 0 means no limit.
- question_limit        How many questions each attempt serves, chosen at random.
                        0 means serve all of them.
- pass_percentage       A number from 0 to 100.

QUALITY REQUIREMENTS
- Questions must be factually accurate. If you are not confident a fact is
  correct, write a different question instead.
- No placeholder or filler text anywhere.
- No duplicate questions and no duplicate options within a question.
- Exactly the required number of correct answers per question — no more.
- Every question must be answerable from the question text alone.
- Wrap any field containing a comma in double quotes, so the CSV parses cleanly.

The output must import directly into the existing Quiz Import system without edits.
PROMPT;
    }

    /** CSV of every problem row with the reason in the last column. */
    public function errorReport($id): StreamedResponse {
        $import = QuizImport::findOrFail($id);

        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            QuizImportService::writeCsvLine($out, array_merge(['row_number'], QuizImportService::HEADERS, ['status', 'errors']));

            $import->rows()
                ->whereIn('validation_status', [
                    QuizImportRow::STATUS_INVALID, QuizImportRow::STATUS_DUPLICATE, QuizImportRow::STATUS_FAILED,
                ])
                ->orderBy('row_number')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        QuizImportService::writeCsvLine($out, [
                            $r->row_number, $r->quiz_title, $r->quiz_slug, $r->quiz_description,
                            $r->category_raw, $r->quiz_type, $r->price, $r->quiz_difficulty,
                            $r->time_limit, $r->pass_percentage, $r->marks_per_correct,
                            $r->negative_marking, $r->quiz_status, $r->question, $r->question_type,
                            $r->option_a, $r->option_b, $r->option_c, $r->option_d,
                            $r->correct_answer, $r->explanation, $r->question_difficulty,
                            $r->validation_status,
                            $r->validation_status === QuizImportRow::STATUS_DUPLICATE
                                ? $r->duplicate_reason
                                : $r->errorList(),
                        ]);
                    }
                });

            fclose($out);
        }, 'quiz-import-' . $import->id . '-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
