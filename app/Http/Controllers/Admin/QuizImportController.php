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

        $sample = [
            ['SAMPLE Quiz One - replace this', 'sample-quiz-one', 'Short description shown on the quiz page.', $catId,
             'free', '0', 'medium', '15', '60', '1', '0', 'draft',
             'Which Indian state has the longest coastline?', 'mcq_single',
             'Tamil Nadu', 'Gujarat', 'Andhra Pradesh', 'Kerala', 'B',
             'Gujarat has the longest coastline of any Indian state, at roughly 1600 km.', 'medium'],

            ['SAMPLE Quiz One - replace this', 'sample-quiz-one', 'Short description shown on the quiz page.', $catId,
             'free', '0', 'medium', '15', '60', '1', '0', 'draft',
             'The Tropic of Cancer passes through India.', 'true_false',
             'True', 'False', '', '', 'A',
             'The Tropic of Cancer crosses eight Indian states. Leave options C and D empty for true_false rows.', 'easy'],

            ['SAMPLE Quiz Two - replace this', 'sample-quiz-two', 'A second quiz in the same file.', $catId,
             'free', '0', 'hard', '20', '50', '2', '0.5', 'draft',
             'Which two of these are Himalayan rivers?', 'mcq_multi',
             'Ganga', 'Godavari', 'Yamuna', 'Krishna', 'A,C',
             'The Ganga and Yamuna rise in the Himalayas; the Godavari and Krishna are peninsular rivers.', 'hard'],
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

        return view('admin.quiz_import.preview', compact('pageTitle', 'import', 'groups', 'filter'));
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
