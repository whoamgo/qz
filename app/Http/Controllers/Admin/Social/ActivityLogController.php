<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Admin;
use App\Models\Social\SocialAuditLog;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;

class ActivityLogController extends SocialBaseController {

    public function index(Request $request) {
        $this->can(SocialPermission::VIEW_LOGS);

        $pageTitle = 'Activity Logs';

        $query = SocialAuditLog::with('admin')->latest('id');

        if ($action = $request->action) {
            $query->where('action', $action);
        }
        if ($platform = $request->platform) {
            $query->where('platform', $platform);
        }
        if ($adminId = $request->admin_id) {
            $query->where('admin_id', $adminId);
        }
        if ($result = $request->result) {
            $query->where('result', $result);
        }
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($search = trim((string) $request->search)) {
            $query->where('description', 'like', "%$search%");
        }

        $logs = $query->paginate(getPaginate())->withQueryString();

        return view('admin.social.logs.index', [
            'pageTitle' => $pageTitle,
            'logs'      => $logs,
            'actions'   => SocialAuditLog::ACTIONS,
            'admins'    => Admin::orderBy('name')->get(['id', 'name']),
            'platforms' => S::PLATFORMS,
        ]);
    }
}
