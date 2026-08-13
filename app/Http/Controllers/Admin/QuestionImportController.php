<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionImport;
use App\Models\QuestionImportRow;
use App\Services\QuestionImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionImportController extends Controller {
    public function __construct(private QuestionImportService $service) {}

    public function index() {
        $pageTitle = 'Import Questions';
        $imports = QuestionImport::with('admin')
            ->searchable(['file_name'])
            ->when(request('status'), fn($q) => $q->where('status', request('status')))
            ->latest()
            ->paginate(getPaginate());

        return view('admin.question_import.index', compact('pageTitle', 'imports'));
    }

    public function history() {
        $pageTitle = 'Import History';
        $imports = QuestionImport::with('admin')
            ->searchable(['file_name'])
            ->when(request('status'), fn($q) => $q->where('status', request('status')))
            ->latest()
            ->paginate(getPaginate());

        return view('admin.question_import.history', compact('pageTitle', 'imports'));
    }

    /** Streams a ready-to-fill CSV template with one worked example row. */
    public function template(): StreamedResponse {
        // Sample rows exist to show the expected shape. Each question type is
        // represented once so the admin can see how correct_answer differs.
        $sample = [
            [
                'General Knowledge', 'Indian Geography',
                'SAMPLE - replace this row. Which Indian state has the longest coastline?',
                'mcq_single',
                'Tamil Nadu', 'Gujarat', 'Andhra Pradesh', 'Kerala',
                'B',
                'Gujarat has the longest coastline of any Indian state, at roughly 1600 km.',
                'medium',
            ],
            [
                'General Knowledge', 'Indian Geography',
                'SAMPLE - replace this row. Which two of these are Himalayan rivers?',
                'mcq_multi',
                'Ganga', 'Godavari', 'Yamuna', 'Krishna',
                'A,C',
                'The Ganga and Yamuna both rise in the Himalayas; the Godavari and Krishna are peninsular rivers.',
                'hard',
            ],
            [
                'General Knowledge', 'Indian Geography',
                'SAMPLE - replace this row. The Tropic of Cancer passes through India.',
                'true_false',
                'True', 'False', '', '',
                'A',
                'The Tropic of Cancer crosses eight Indian states. Leave options C and D empty for true_false rows.',
                'easy',
            ],
        ];

        return response()->streamDownload(function () use ($sample) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens the file as UTF-8 rather than mangling accents.
            fwrite($out, "\xEF\xBB\xBF");
            QuestionImportService::writeCsvLine($out, QuestionImportService::HEADERS);
            foreach ($sample as $row) {
                QuestionImportService::writeCsvLine($out, $row);
            }
            fclose($out);
        }, 'question-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function upload(Request $request) {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt',
        ], [
            'file.mimes' => 'The file must be a CSV. Excel workbooks are not supported on this installation.',
            'file.max'   => 'The file may not be larger than 10 MB.',
        ]);

        $file = $request->file('file');
        // Pinned to the private disk: the default disk on this install is
        // 'public', which is served over the web.
        $path = $file->store('question-imports', QuestionImportService::DISK);

        $import = QuestionImport::create([
            'admin_id'   => auth('admin')->id(),
            'file_name'  => $file->getClientOriginalName(),
            'stored_file' => $path,
            'file_type'  => strtolower($file->getClientOriginalExtension() ?: 'csv'),
            'status'     => QuestionImport::STATUS_UPLOADED,
        ]);

        // Fail fast on a malformed header rather than staging garbage.
        try {
            $this->service->readHeaderMap($import);
            $import->total_records = $this->service->countDataRows($import);

            if ($import->total_records === 0) {
                throw new \RuntimeException('The file contains a header but no data rows.');
            }

            $import->save();
        } catch (\Throwable $e) {
            $import->status        = QuestionImport::STATUS_VALIDATION_FAILED;
            $import->error_message = $e->getMessage();
            $import->save();

            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }

        return to_route('admin.question-import.preview', $import->id);
    }

    /** AJAX: stages the next chunk and reports live progress. */
    public function process(Request $request, $id) {
        $import = QuestionImport::findOrFail($id);

        if (!$import->isProcessing()) {
            return response()->json([
                'success' => true,
                'done'    => true,
                'data'    => $this->progressPayload($import),
            ]);
        }

        try {
            $import = $this->service->processChunk($import);
        } catch (\Throwable $e) {
            $import->status        = QuestionImport::STATUS_VALIDATION_FAILED;
            $import->error_message = $e->getMessage();
            $import->save();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => $this->progressPayload($import),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'done'    => !$import->isProcessing(),
            'data'    => $this->progressPayload($import),
        ]);
    }

    /** AJAX: read-only progress poll. */
    public function status($id) {
        $import = QuestionImport::findOrFail($id);
        return response()->json(['success' => true, 'data' => $this->progressPayload($import)]);
    }

    private function progressPayload(QuestionImport $import): array {
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
            'imported'   => $import->imported_records,
            'error'      => $import->error_message,
            'canApprove' => $import->canApprove(),
        ];
    }

    public function preview($id) {
        $import = QuestionImport::with('admin')->findOrFail($id);
        $pageTitle = 'Import Preview: ' . $import->file_name;

        $filter = request('filter', QuestionImportRow::STATUS_VALID);
        $allowed = [
            QuestionImportRow::STATUS_VALID,
            QuestionImportRow::STATUS_INVALID,
            QuestionImportRow::STATUS_DUPLICATE,
            QuestionImportRow::STATUS_IMPORTED,
            QuestionImportRow::STATUS_REMOVED,
        ];
        if (!in_array($filter, $allowed, true)) {
            $filter = QuestionImportRow::STATUS_VALID;
        }

        $rows = $import->rows()
            ->with(['category', 'subCategory', 'duplicateOf'])
            ->where('validation_status', $filter)
            ->orderBy('row_number')
            ->paginate(getPaginate());

        return view('admin.question_import.preview', compact('pageTitle', 'import', 'rows', 'filter'));
    }

    /** Edits a staged row in place and re-runs validation on it. */
    public function updateRow(Request $request, $id, $rowId) {
        $import = QuestionImport::findOrFail($id);

        if (!$import->isReviewable()) {
            $notify[] = ['error', 'Rows can only be edited while the import is awaiting review.'];
            return back()->withNotify($notify);
        }

        $request->validate([
            'category_name'     => 'required|string|max:255',
            'sub_category_name' => 'nullable|string|max:255',
            'question'          => 'required|string',
            'question_type'     => 'required|in:mcq_single,mcq_multi,true_false',
            'option_a'          => 'nullable|string',
            'option_b'          => 'nullable|string',
            'option_c'          => 'nullable|string',
            'option_d'          => 'nullable|string',
            'correct_answer'    => 'required|string|max:10',
            'explanation'       => 'nullable|string|max:5000',
            'difficulty'        => 'required|in:easy,medium,hard',
        ]);

        $row = QuestionImportRow::where('import_id', $import->id)->findOrFail($rowId);
        // correct_answer is excluded here and normalised separately below.
        $row->fill($request->only(array_diff(
            QuestionImportRow::EDITABLE_FIELDS,
            ['correct_answer']
        )));
        $row->correct_answer = strtoupper(str_replace(' ', '', $request->correct_answer));
        $row->save();

        $row = $this->service->revalidateRow($row);

        $notify[] = $row->validation_status === QuestionImportRow::STATUS_VALID
            ? ['success', "Row {$row->row_number} is now valid."]
            : ['warning', "Row {$row->row_number} is still {$row->validation_status}: " . $row->errorList()];

        return back()->withNotify($notify);
    }

    /** Excludes a row from the import without deleting the staged original. */
    public function removeRow($id, $rowId) {
        $import = QuestionImport::findOrFail($id);

        if (!$import->isReviewable()) {
            $notify[] = ['error', 'Rows can only be removed while the import is awaiting review.'];
            return back()->withNotify($notify);
        }

        $row = QuestionImportRow::where('import_id', $import->id)->findOrFail($rowId);
        $row->validation_status = QuestionImportRow::STATUS_REMOVED;
        $row->save();

        $this->service->refreshCounts($import);

        $notify[] = ['success', "Row {$row->row_number} removed from this import."];
        return back()->withNotify($notify);
    }

    public function approve(Request $request, $id) {
        $import = QuestionImport::findOrFail($id);

        if (!$import->canApprove()) {
            $notify[] = ['error', 'This import has no valid rows to approve, or is not awaiting review.'];
            return back()->withNotify($notify);
        }

        // Guards against the count shifting between the confirmation dialog and
        // the POST, so the admin never approves a different number than shown.
        $expected = (int) $request->input('expected_valid');
        if ($expected && $expected !== $import->valid_records) {
            $notify[] = ['error', "The valid-row count changed from {$expected} to {$import->valid_records}. Review again before approving."];
            return back()->withNotify($notify);
        }

        try {
            $import = $this->service->approve($import);
        } catch (\Throwable $e) {
            $notify[] = ['error', 'Import failed and was rolled back. No questions were added. ' . $e->getMessage()];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', "{$import->imported_records} question(s) imported into the question bank."];
        return to_route('admin.question-import.preview', $import->id)->withNotify($notify);
    }

    public function cancel($id) {
        $import = QuestionImport::findOrFail($id);

        if ($import->status === QuestionImport::STATUS_COMPLETED) {
            $notify[] = ['error', 'A completed import cannot be cancelled.'];
            return back()->withNotify($notify);
        }

        $import->status = QuestionImport::STATUS_CANCELLED;
        $import->save();

        $notify[] = ['success', 'Import cancelled.'];
        return back()->withNotify($notify);
    }

    /**
     * Deletes the import, its staged rows and the uploaded file. Questions
     * already promoted into the bank are deliberately left in place.
     */
    public function destroy($id) {
        $import = QuestionImport::findOrFail($id);

        $disk = Storage::disk(QuestionImportService::DISK);
        if ($import->stored_file && $disk->exists($import->stored_file)) {
            $disk->delete($import->stored_file);
        }

        $import->rows()->delete();
        $import->forceDelete();

        $notify[] = ['success', 'Import deleted. Questions already imported into the bank were kept.'];
        return to_route('admin.question-import.index')->withNotify($notify);
    }

    /** Streams a CSV of every problem row, with the reason in the last column. */
    public function errorReport($id): StreamedResponse {
        $import = QuestionImport::findOrFail($id);

        $filename = 'import-' . $import->id . '-errors.csv';

        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            QuestionImportService::writeCsvLine($out, array_merge(["row_number"], QuestionImportService::HEADERS, ["status", "errors"]));

            $import->rows()
                ->whereIn('validation_status', [
                    QuestionImportRow::STATUS_INVALID,
                    QuestionImportRow::STATUS_DUPLICATE,
                    QuestionImportRow::STATUS_FAILED,
                ])
                ->orderBy('row_number')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        $reason = $row->validation_status === QuestionImportRow::STATUS_DUPLICATE
                            ? ($row->duplicate_question_id
                                ? 'Duplicate of existing question #' . $row->duplicate_question_id
                                : 'Duplicate of an earlier row in this same file')
                            : $row->errorList();

                        QuestionImportService::writeCsvLine($out, [
                            $row->row_number,
                            $row->category_name,
                            $row->sub_category_name,
                            $row->question,
                            $row->question_type,
                            $row->option_a,
                            $row->option_b,
                            $row->option_c,
                            $row->option_d,
                            $row->correct_answer,
                            $row->explanation,
                            $row->difficulty,
                            $row->validation_status,
                            $reason,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
