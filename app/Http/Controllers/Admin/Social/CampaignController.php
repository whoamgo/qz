<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialCampaign;
use App\Services\Social\SocialAnalyticsService;
use App\Services\Social\SocialAuditLogger;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CampaignController extends SocialBaseController {

    public function index() {
        $pageTitle = 'Campaigns';

        $campaigns = SocialCampaign::withCount('posts')->latest('id')->paginate(getPaginate());
        $platforms = S::PLATFORMS;

        return view('admin.social.campaigns.index', compact('pageTitle', 'campaigns', 'platforms'));
    }

    public function show(SocialCampaign $campaign, SocialAnalyticsService $analytics) {
        $pageTitle = 'Campaign: ' . $campaign->name;

        $posts = $campaign->posts()->with('targets')->latest('id')->paginate(getPaginate());

        // Roll the campaign's own posts up rather than reusing the global
        // summary, which covers everything published in a date range.
        $targets = \App\Models\Social\SocialPostPlatform::whereIn(
            'social_post_id',
            $campaign->posts()->pluck('id')
        )->where('status', S::PUBLISHED)->get();

        $sum = fn (string $key) => $targets->sum(fn ($t) => (int) $t->metric($key, 0));
        $reach = $sum('reach') ?: $sum('impressions');

        $stats = [
            'posts'       => $campaign->posts()->count(),
            'published'   => $targets->count(),
            'reach'       => $reach,
            'views'       => $sum('views'),
            'likes'       => $sum('likes'),
            'comments'    => $sum('comments'),
            'shares'      => $sum('shares'),
            'clicks'      => $sum('clicks'),
            'engagement_rate' => $reach > 0
                ? round((($sum('likes') + $sum('comments') + $sum('shares')) / $reach) * 100, 2)
                : null,
        ];

        return view('admin.social.campaigns.show', compact('pageTitle', 'campaign', 'posts', 'stats'));
    }

    public function store(Request $request) {
        $this->can(SocialPermission::MANAGE_LIBRARY);

        $data = $this->validated($request);
        $data['created_by'] = Auth::guard('admin')->id();

        $campaign = SocialCampaign::create($data);

        SocialAuditLogger::forModel('campaign.save', $campaign, [
            'description' => 'Created campaign "' . $campaign->name . '"',
        ]);

        $notify[] = ['success', 'Campaign created.'];
        return back()->withNotify($notify);
    }

    public function update(Request $request, SocialCampaign $campaign) {
        $this->can(SocialPermission::MANAGE_LIBRARY);

        $campaign->update($this->validated($request, $campaign));

        SocialAuditLogger::forModel('campaign.save', $campaign, [
            'description' => 'Updated campaign "' . $campaign->name . '"',
        ]);

        $notify[] = ['success', 'Campaign updated.'];
        return back()->withNotify($notify);
    }

    public function destroy(SocialCampaign $campaign) {
        $this->can(SocialPermission::MANAGE_LIBRARY);

        // Posts survive - the foreign key nulls out, so the campaign can be
        // removed without taking its content with it.
        $name = $campaign->name;
        $campaign->delete();

        $notify[] = ['success', 'Deleted campaign "' . $name . '". Its posts were kept.'];
        return to_route('admin.social.campaigns.index')->withNotify($notify);
    }

    protected function validated(Request $request, ?SocialCampaign $campaign = null): array {
        $data = $request->validate([
            'name'         => 'required|string|max:150',
            'description'  => 'nullable|string|max:2000',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'platforms'    => 'nullable|array',
            'platforms.*'  => Rule::in(array_keys(S::PLATFORMS)),
            'hashtags'     => 'nullable|string|max:2000',
            'utm_campaign' => 'nullable|string|max:100|regex:/^[A-Za-z0-9_\-]+$/',
            'status'       => ['required', Rule::in(array_keys(SocialCampaign::STATUSES))],
        ], [
            'utm_campaign.regex' => 'The UTM campaign may only contain letters, numbers, hyphens and underscores.',
        ]);

        $data['platforms'] = $data['platforms'] ?? [];

        return $data;
    }
}
