<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialComment;
use App\Services\Social\PlatformCapability;
use App\Services\Social\SocialInboxService;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;

/**
 * The unified inbox.
 *
 * Only platforms whose official API exposes comments or mentions appear here.
 * The rest are listed with the reason they are absent rather than shown as an
 * empty tab, so the gap reads as a platform limitation and not a broken sync.
 */
class InboxController extends SocialBaseController {

    public function index(Request $request, SocialInboxService $inbox) {
        $pageTitle = 'Social Inbox';

        $query = SocialComment::with(['account', 'target.post'])->latest('posted_at');

        if ($platform = $request->platform) {
            $query->where('platform', $platform);
        }
        if ($request->filter === 'unread') {
            $query->where('is_read', false);
        }
        if ($search = trim((string) $request->search)) {
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%$search%")->orWhere('author_name', 'like', "%$search%");
            });
        }

        $comments = $query->paginate(getPaginate())->withQueryString();

        $supported   = $inbox->supportedPlatforms();
        $unsupported = collect(S::PLATFORMS)
            ->keys()
            ->reject(fn ($p) => PlatformCapability::supports($p, 'inbox'))
            ->values();

        $unread = SocialComment::unread()->count();

        return view('admin.social.inbox.index', compact(
            'pageTitle', 'comments', 'supported', 'unsupported', 'unread'
        ));
    }

    public function sync(SocialInboxService $inbox) {
        $this->can(SocialPermission::MANAGE_INBOX);

        $stats = $inbox->syncAll();

        $notify[] = $stats['accounts']
            ? ['success', "Fetched {$stats['synced']} new item(s) from {$stats['accounts']} account(s)."]
            : ['info', 'No connected account exposes comments through its API.'];

        if ($stats['errors']) {
            $notify[] = ['warning', "{$stats['errors']} account(s) could not be reached."];
        }

        return back()->withNotify($notify);
    }

    public function markRead(SocialComment $comment) {
        $this->can(SocialPermission::MANAGE_INBOX);

        $comment->update(['is_read' => true]);

        if (request()->ajax()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    public function markAllRead() {
        $this->can(SocialPermission::MANAGE_INBOX);

        $count = SocialComment::unread()->update(['is_read' => true]);

        $notify[] = ['success', "Marked $count item(s) as read."];
        return back()->withNotify($notify);
    }
}
