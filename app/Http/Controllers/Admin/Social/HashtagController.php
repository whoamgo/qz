<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialHashtagGroup;
use App\Services\Social\SocialAuditLogger;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HashtagController extends SocialBaseController {

    public function index(Request $request) {
        $pageTitle = 'Hashtag Library';

        $query = SocialHashtagGroup::latest('id');

        if ($search = trim((string) $request->search)) {
            $query->where('name', 'like', "%$search%");
        }

        $groups = $query->paginate(getPaginate())->withQueryString();

        return view('admin.social.hashtags.index', [
            'pageTitle' => $pageTitle,
            'groups'    => $groups,
            'platforms' => S::PLATFORMS,
        ]);
    }

    public function store(Request $request) {
        $this->can(SocialPermission::MANAGE_LIBRARY);

        $group = SocialHashtagGroup::create($this->validated($request) + [
            'created_by' => Auth::guard('admin')->id(),
        ]);

        SocialAuditLogger::forModel('hashtags.save', $group, [
            'description' => 'Created hashtag group "' . $group->name . '" (' . $group->hashtag_count . ' tags)',
        ]);

        $notify[] = ['success', 'Hashtag group saved with ' . $group->hashtag_count . ' tag(s).'];
        return back()->withNotify($notify);
    }

    public function update(Request $request, SocialHashtagGroup $group) {
        $this->can(SocialPermission::MANAGE_LIBRARY);

        $group->update($this->validated($request));

        $notify[] = ['success', 'Hashtag group updated.'];
        return back()->withNotify($notify);
    }

    public function destroy(SocialHashtagGroup $group) {
        $this->can(SocialPermission::MANAGE_LIBRARY);

        $name = $group->name;
        $group->delete();

        $notify[] = ['success', 'Deleted "' . $name . '".'];
        return back()->withNotify($notify);
    }

    protected function validated(Request $request): array {
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'hashtags'    => 'required|string|max:5000',
            'platform'    => ['nullable', Rule::in(array_keys(S::PLATFORMS))],
            'is_active'   => 'nullable|boolean',
        ]);

        // The model normalises on save; doing it here too means validation can
        // reject a group that turns out to be empty after cleaning.
        $tags = SocialHashtagGroup::normalise($data['hashtags']);

        if (!$tags) {
            abort(422, 'No usable hashtags were found in that text.');
        }

        $data['hashtags']  = $tags;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
