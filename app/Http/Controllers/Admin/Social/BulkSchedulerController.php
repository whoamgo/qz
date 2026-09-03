<?php

namespace App\Http\Controllers\Admin\Social;

use App\Models\Social\SocialBulkImport;
use App\Services\Social\SocialBulkImportService;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;

class BulkSchedulerController extends SocialBaseController {

    public function __construct(protected SocialBulkImportService $service) {}

    public function index() {
        $pageTitle = 'Bulk Scheduler';

        $imports = SocialBulkImport::withCount('rows')->latest('id')->paginate(getPaginate());

        return view('admin.social.bulk.index', [
            'pageTitle' => $pageTitle,
            'imports'   => $imports,
            'columns'   => SocialBulkImportService::COLUMNS,
            'required'  => SocialBulkImportService::REQUIRED,
            'platforms' => $this->platformContext(),
        ]);
    }

    public function upload(Request $request) {
        $this->can(SocialPermission::SCHEDULE);

        $request->validate([
            // The extension check is a courtesy; the parser only ever treats
            // the contents as text, so a mislabelled file fails harmlessly.
            'file' => 'required|file|mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel|max:5120',
        ], [
            'file.mimetypes' => 'Upload a CSV file exported as plain text.',
        ]);

        $import = $this->service->preview($request->file('file'));

        if ($import->status === 'failed') {
            $notify[] = ['error', $import->error];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', "Read {$import->total_rows} row(s): {$import->valid_rows} ready, {$import->invalid_rows} with problems. Review before importing."];
        return to_route('admin.social.bulk.preview', $import->id)->withNotify($notify);
    }

    public function preview(SocialBulkImport $import) {
        $pageTitle = 'Bulk Import Preview';

        $rows = $import->rows()->orderBy('row_number')->paginate(50);

        return view('admin.social.bulk.preview', compact('pageTitle', 'import', 'rows'));
    }

    public function confirm(Request $request, SocialBulkImport $import) {
        $this->can(SocialPermission::SCHEDULE);

        if ($import->status === 'completed') {
            $notify[] = ['error', 'This import has already run.'];
            return back()->withNotify($notify);
        }

        if (!$import->valid_rows) {
            $notify[] = ['error', 'There are no valid rows to import. Fix the errors and upload again.'];
            return back()->withNotify($notify);
        }

        $schedule = $request->boolean('schedule', true);

        if ($schedule) {
            $this->can(SocialPermission::SCHEDULE);
        }

        $import = $this->service->process($import, $schedule);

        $notify[] = ['success', "Created {$import->created_posts} post(s)" . ($schedule ? ' and scheduled them.' : ' as drafts.')];
        return to_route('admin.social.posts.index')->withNotify($notify);
    }

    public function destroy(SocialBulkImport $import) {
        $this->can(SocialPermission::SCHEDULE);

        // Posts already created keep existing; only the import record goes.
        $import->delete();

        $notify[] = ['success', 'Import record removed.'];
        return to_route('admin.social.bulk.index')->withNotify($notify);
    }

    /** Downloads a ready-to-fill CSV with the expected headers. */
    public function template() {
        return response($this->service->templateCsv(), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="social-bulk-template.csv"',
        ]);
    }
}
