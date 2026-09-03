<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Category;
use App\Models\Social\SocialAutomation;
use App\Models\Social\SocialCampaign;
use App\Models\Social\SocialHashtagGroup;
use App\Models\Social\SocialTemplate;
use App\Services\Social\SocialAuditLogger;
use App\Services\Social\SocialAutomationRunner;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AutomationController extends SocialBaseController {

    public function __construct(protected SocialAutomationRunner $runner) {}

    public function index() {
        $pageTitle = 'Automations';

        $automations = SocialAutomation::with(['template', 'hashtagGroup', 'campaign'])->latest('id')->get();

        return view('admin.social.automations.index', [
            'pageTitle'      => $pageTitle,
            'automations'    => $automations,
            'platforms'      => $this->platformContext(),
            'templates'      => SocialTemplate::active()->orderBy('name')->get(),
            'hashtagGroups'  => SocialHashtagGroup::active()->orderBy('name')->get(),
            'campaigns'      => SocialCampaign::orderBy('name')->get(),
            'categories'     => Category::orderBy('name')->get(['id', 'name']),
            'sourceTypes'    => SocialAutomation::SOURCE_TYPES,
            'frequencies'    => SocialAutomation::FREQUENCIES,
            'anyActive'      => $automations->where('is_active', true)->isNotEmpty(),
        ]);
    }

    public function store(Request $request) {
        $this->can(SocialPermission::MANAGE_AUTOMATION);

        $automation = SocialAutomation::create($this->validated($request) + [
            'created_by' => Auth::guard('admin')->id(),
        ]);

        $this->runner->reschedule($automation);

        SocialAuditLogger::forModel('automation.save', $automation, [
            'description' => 'Created automation "' . $automation->name . '"',
        ]);

        $notify[] = ['success', 'Automation created. It is switched off until you enable it.'];
        return back()->withNotify($notify);
    }

    public function update(Request $request, SocialAutomation $automation) {
        $this->can(SocialPermission::MANAGE_AUTOMATION);

        $automation->update($this->validated($request));
        $this->runner->reschedule($automation);

        SocialAuditLogger::forModel('automation.save', $automation, [
            'description' => 'Updated automation "' . $automation->name . '"',
        ]);

        $notify[] = ['success', 'Automation updated.'];
        return back()->withNotify($notify);
    }

    public function toggle(SocialAutomation $automation) {
        $this->can(SocialPermission::MANAGE_AUTOMATION);

        $automation->is_active = !$automation->is_active;
        $automation->save();

        $this->runner->reschedule($automation);

        SocialAuditLogger::forModel('automation.toggle', $automation, [
            'description' => ($automation->is_active ? 'Enabled' : 'Disabled') . ' automation "' . $automation->name . '"',
        ]);

        $notify[] = ['success', $automation->is_active
            ? 'Enabled. Next run: ' . ($automation->next_run_at?->format('d M Y, h:i A') ?: 'not scheduled - check the frequency settings.')
            : 'Disabled. It will not run again until you switch it back on.'];

        return back()->withNotify($notify);
    }

    /** Runs one automation immediately, for testing the configuration. */
    public function runNow(SocialAutomation $automation) {
        $this->can(SocialPermission::MANAGE_AUTOMATION);

        try {
            $post = $this->runner->runOnce($automation);

            $notify[] = $post
                ? ['success', 'Created post #' . $post->id . ($automation->auto_publish ? ' and queued it for publishing.' : ' as a draft for review.')]
                : ['warning', $automation->last_error ?: 'The content source returned nothing to post.'];
        } catch (\Throwable $e) {
            $notify[] = ['error', 'Run failed: ' . $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    /**
     * The emergency stop: disables every automation at once.
     * Deliberately one click, because the situation it exists for is "this is
     * posting something it should not, right now".
     */
    public function stopAll() {
        $this->can(SocialPermission::MANAGE_AUTOMATION);

        $count = SocialAutomation::where('is_active', true)->update([
            'is_active'   => false,
            'next_run_at' => null,
        ]);

        SocialAuditLogger::record('automation.toggle', [
            'description' => "Emergency stop: disabled $count automation(s)",
        ]);

        $notify[] = ['success', "Stopped $count automation(s). Nothing will run until you re-enable them individually."];
        return back()->withNotify($notify);
    }

    public function destroy(SocialAutomation $automation) {
        $this->can(SocialPermission::MANAGE_AUTOMATION);

        $name = $automation->name;
        $automation->delete();

        $notify[] = ['success', 'Deleted "' . $name . '". Posts it already created were kept.'];
        return back()->withNotify($notify);
    }

    protected function validated(Request $request): array {
        $data = $request->validate([
            'name'                    => 'required|string|max:150',
            'description'             => 'nullable|string|max:1000',
            'source_type'             => ['required', Rule::in(array_keys(SocialAutomation::SOURCE_TYPES))],
            'source_config'           => 'nullable|array',
            'source_config.category_id'  => 'nullable|integer|exists:categories,id',
            'source_config.day_of_month' => 'nullable|integer|min:1|max:31',
            'frequency'               => ['required', Rule::in(array_keys(SocialAutomation::FREQUENCIES))],
            'days_of_week'            => 'nullable|array',
            'days_of_week.*'          => 'integer|min:0|max:6',
            'run_time'                => 'nullable|date_format:H:i',
            'interval_minutes'        => 'nullable|integer|min:5|max:10080',
            'timezone'                => 'nullable|timezone',
            'platforms'               => 'required|array|min:1',
            'platforms.*'             => Rule::in(array_keys(S::PLATFORMS)),
            'social_template_id'      => 'nullable|integer|exists:social_templates,id',
            'social_hashtag_group_id' => 'nullable|integer|exists:social_hashtag_groups,id',
            'social_campaign_id'      => 'nullable|integer|exists:social_campaigns,id',
            'auto_publish'            => 'nullable|boolean',
        ]);

        if ($data['frequency'] === 'weekly' && empty($data['days_of_week'])) {
            abort(422, 'Choose at least one day of the week for a weekly automation.');
        }
        if ($data['frequency'] === 'interval' && empty($data['interval_minutes'])) {
            abort(422, 'Set the interval in minutes.');
        }
        if (in_array($data['frequency'], ['daily', 'weekly', 'monthly'], true) && empty($data['run_time'])) {
            abort(422, 'Set the time of day this automation should run.');
        }

        $data['auto_publish'] = $request->boolean('auto_publish');
        $data['days_of_week'] = $data['days_of_week'] ?? [];

        return $data;
    }
}
