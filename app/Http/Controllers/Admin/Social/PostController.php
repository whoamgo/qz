<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Category;
use App\Models\Social\SocialCampaign;
use App\Models\Social\SocialHashtagGroup;
use App\Models\Social\SocialMedia;
use App\Models\Social\SocialPost;
use App\Models\Social\SocialPostPlatform;
use App\Models\Social\SocialTemplate;
use App\Services\Social\ContentComposer;
use App\Services\Social\MediaValidator;
use App\Services\Social\PlatformRegistry;
use App\Services\Social\SocialAuditLogger;
use App\Services\Social\SocialPermission;
use App\Services\Social\SocialPostService;
use App\Services\Social\SocialPublisher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The publishing wizard and the post list.
 *
 * The wizard posts everything in one submit and this controller decides what
 * happens next from the `action` field - draft, schedule or publish. Keeping
 * that decision server-side means a tampered form cannot turn "save draft" into
 * "publish to five platforms".
 */
class PostController extends SocialBaseController {

    public function __construct(
        protected SocialPostService $posts,
        protected SocialPublisher $publisher,
        protected ContentComposer $composer,
        protected MediaValidator $validator,
        protected PlatformRegistry $registry,
    ) {}

    /* ----------------------------------------------------------------- List */

    public function index(Request $request) {
        $pageTitle = 'Social Posts';

        $query = SocialPost::with(['targets', 'campaign', 'media'])->latest('id');

        if ($search = trim((string) $request->search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")->orWhere('caption', 'like', "%$search%");
            });
        }
        if ($status = $request->status) {
            $query->where('status', $status);
        }
        if ($platform = $request->platform) {
            $query->whereHas('targets', fn ($q) => $q->where('platform', $platform));
        }
        if ($campaign = $request->campaign) {
            $query->where('social_campaign_id', $campaign);
        }
        if ($type = $request->content_type) {
            $query->where('content_type', $type);
        }
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $posts     = $query->paginate(getPaginate())->withQueryString();
        $campaigns = SocialCampaign::orderBy('name')->get();

        return view('admin.social.posts.index', compact('pageTitle', 'posts', 'campaigns'));
    }

    /* --------------------------------------------------------------- Wizard */

    public function create(Request $request) {
        $this->can(SocialPermission::CREATE);

        $pageTitle = 'Create Social Post';

        // "Share to Social Media" from a quiz lands here with a prefilled seed.
        $seed = session('social.seed', []);
        session()->forget('social.seed');

        return view('admin.social.posts.form', $this->formData($seed) + compact('pageTitle'));
    }

    public function edit(SocialPost $post) {
        $this->can(SocialPermission::EDIT);

        if (!$post->isEditable()) {
            $notify[] = ['error', 'A post that is publishing or already published cannot be edited.'];
            return to_route('admin.social.posts.show', $post->id)->withNotify($notify);
        }

        $post->load('targets', 'media', 'campaign');
        $pageTitle = 'Edit Social Post';

        return view('admin.social.posts.form', $this->formData([], $post) + compact('pageTitle', 'post'));
    }

    public function store(Request $request) {
        $this->can(SocialPermission::CREATE);

        $data = $this->validatePost($request);

        $post = $this->posts->create(
            $data['post'],
            $data['platforms'],
            $data['customisations'],
            $data['media']
        );

        return $this->finish($request, $post, $data);
    }

    public function update(Request $request, SocialPost $post) {
        $this->can(SocialPermission::EDIT);

        if (!$post->isEditable()) {
            $notify[] = ['error', 'A post that is publishing or already published cannot be edited.'];
            return back()->withNotify($notify);
        }

        $data = $this->validatePost($request);

        $post = $this->posts->update(
            $post,
            $data['post'],
            $data['platforms'],
            $data['customisations'],
            $data['media']
        );

        return $this->finish($request, $post, $data);
    }

    /**
     * Applies the wizard's chosen action.
     *
     * Every branch re-checks the ability for that specific action rather than
     * relying on the create/edit check above - "may create a draft" and "may
     * publish to a live audience" are different permissions.
     */
    protected function finish(Request $request, SocialPost $post, array $data) {
        $action = $data['action'];

        try {
            if ($action === 'publish') {
                $this->can(SocialPermission::PUBLISH);
                $this->assertPublishable($post, $data['platforms']);

                $result = $this->publisher->dispatch($post);

                $notify[] = ['success', "Queued for publishing to {$result['jobs']} platform(s). Results appear as each platform completes."];
                foreach ($result['skipped'] as $platform => $reason) {
                    $notify[] = ['warning', S::platformName($platform) . ': ' . $reason];
                }

                return to_route('admin.social.posts.show', $post->id)->withNotify($notify);
            }

            if ($action === 'schedule') {
                $this->can(SocialPermission::SCHEDULE);
                $this->assertPublishable($post, $data['platforms']);

                $when = $data['scheduled_at'];
                if (!$when) {
                    $notify[] = ['error', 'Choose a publish date and time to schedule this post.'];
                    return back()->withInput()->withNotify($notify);
                }

                $result = $this->publisher->dispatch($post, $when);

                $notify[] = ['success', "Scheduled for {$when->format('d M Y, h:i A')} across {$result['jobs']} platform(s)."];
                return to_route('admin.social.posts.show', $post->id)->withNotify($notify);
            }

            if ($action === 'approval') {
                $this->posts->submitForApproval($post);
                $notify[] = ['success', 'Submitted for approval.'];
                return to_route('admin.social.posts.show', $post->id)->withNotify($notify);
            }
        } catch (\RuntimeException $e) {
            $notify[] = ['error', $e->getMessage()];
            return to_route('admin.social.posts.show', $post->id)->withNotify($notify);
        }

        $notify[] = ['success', 'Saved as a draft.'];
        return to_route('admin.social.posts.show', $post->id)->withNotify($notify);
    }

    /**
     * Blocks a publish that the media rules say cannot work.
     *
     * @throws \RuntimeException
     */
    protected function assertPublishable(SocialPost $post, array $platforms): void {
        $post->load('media');
        $blocking = MediaValidator::blocking($this->validator->validate($post, $platforms));

        if ($blocking) {
            throw new \RuntimeException(
                'This post cannot be published yet: ' . implode(' ', array_column($blocking, 'message'))
            );
        }
    }

    /* ----------------------------------------------------------------- View */

    public function show(SocialPost $post) {
        $post->load(['targets.account', 'targets.attemptLogs', 'media', 'campaign', 'creator', 'approver', 'jobs']);

        $pageTitle = 'Post Details';
        $issues    = $this->validator->validate($post, $post->targets->pluck('platform')->all());

        return view('admin.social.posts.show', compact('pageTitle', 'post', 'issues'));
    }

    /* -------------------------------------------------------------- Actions */

    public function publish(Request $request, SocialPost $post) {
        $this->can(SocialPermission::PUBLISH);

        try {
            $this->assertPublishable($post, $post->targets->pluck('platform')->all());
            $result = $this->publisher->dispatch($post);

            $notify[] = ['success', "Queued for publishing to {$result['jobs']} platform(s)."];
            foreach ($result['skipped'] as $platform => $reason) {
                $notify[] = ['warning', S::platformName($platform) . ': ' . $reason];
            }
        } catch (\RuntimeException $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    public function schedule(Request $request, SocialPost $post) {
        $this->can(SocialPermission::SCHEDULE);

        $request->validate(['scheduled_at' => 'required|date']);

        try {
            $when = $this->parseSchedule($request->scheduled_at);
            $this->assertPublishable($post, $post->targets->pluck('platform')->all());

            $this->publisher->dispatch($post, $when);
            $notify[] = ['success', 'Scheduled for ' . $when->format('d M Y, h:i A') . '.'];
        } catch (\RuntimeException $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    public function reschedule(Request $request, SocialPost $post) {
        $this->can(SocialPermission::SCHEDULE);

        $request->validate(['scheduled_at' => 'required|date']);
        $when = $this->parseSchedule($request->scheduled_at);

        // Cancelling first clears the old jobs, so the post does not fire at
        // both the old and the new time.
        $this->publisher->cancel($post);
        $post->refresh();

        foreach ($post->targets as $target) {
            if ($target->status === S::CANCELLED) {
                $target->update(['status' => S::DRAFT]);
            }
        }
        if ($post->status === S::CANCELLED) {
            $post->update(['status' => S::DRAFT]);
        }

        try {
            $this->publisher->dispatch($post->fresh('targets'), $when);
            SocialAuditLogger::forModel('post.reschedule', $post, [
                'description' => 'Rescheduled to ' . $when->toDateTimeString(),
            ]);
            $notify[] = ['success', 'Rescheduled for ' . $when->format('d M Y, h:i A') . '.'];
        } catch (\RuntimeException $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    public function cancel(SocialPost $post) {
        $this->can(SocialPermission::SCHEDULE);

        $this->publisher->cancel($post);

        $notify[] = ['success', 'Cancelled. Anything already published stays published.'];
        return back()->withNotify($notify);
    }

    public function duplicate(SocialPost $post) {
        $this->can(SocialPermission::CREATE);

        $copy = $this->posts->duplicate($post);

        $notify[] = ['success', 'Duplicated as a new draft.'];
        return to_route('admin.social.posts.edit', $copy->id)->withNotify($notify);
    }

    public function destroy(SocialPost $post) {
        $this->can(SocialPermission::DELETE);

        if ($post->status === S::PUBLISHING) {
            $notify[] = ['error', 'This post is publishing right now. Wait for it to finish before deleting it.'];
            return back()->withNotify($notify);
        }

        SocialAuditLogger::forModel('post.delete', $post, ['description' => 'Deleted post "' . $post->title . '"']);
        $post->delete();

        $notify[] = ['success', 'Post deleted. Anything already published on a platform is unaffected.'];
        return to_route('admin.social.posts.index')->withNotify($notify);
    }

    public function retryTarget(SocialPostPlatform $target) {
        $this->can(SocialPermission::RETRY);

        try {
            $this->publisher->retry($target);
            $notify[] = ['success', S::platformName($target->platform) . ' has been queued for another attempt.'];
        } catch (\Throwable $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    /** Full attempt history for one platform, shown in a drawer. */
    public function attempts(SocialPostPlatform $target) {
        $target->load('attemptLogs', 'post');

        return response()->json([
            'platform' => S::platformName($target->platform),
            'status'   => $target->status_name,
            'attempts' => $target->attemptLogs->map(fn ($a) => [
                'attempt'  => $a->attempt_no,
                'status'   => $a->status,
                'code'     => $a->error_code,
                'message'  => $a->error_message,
                'http'     => $a->response_code,
                'duration' => $a->duration_ms,
                'at'       => $a->created_at?->format('d M Y, h:i:s A'),
            ]),
        ]);
    }

    /* -------------------------------------------------------------- Approval */

    public function submitApproval(SocialPost $post) {
        $this->can(SocialPermission::CREATE);

        $this->posts->submitForApproval($post);

        $notify[] = ['success', 'Submitted for approval.'];
        return back()->withNotify($notify);
    }

    public function approve(SocialPost $post) {
        $this->can(SocialPermission::APPROVE);

        $this->posts->approve($post);

        $notify[] = ['success', 'Approved. It can now be scheduled or published.'];
        return back()->withNotify($notify);
    }

    public function reject(Request $request, SocialPost $post) {
        $this->can(SocialPermission::APPROVE);

        $request->validate(['reason' => 'nullable|string|max:500']);
        $this->posts->reject($post, $request->reason);

        $notify[] = ['success', 'Rejected and returned to the author.'];
        return back()->withNotify($notify);
    }

    /* --------------------------------------------------------- Live helpers */

    /**
     * Renders the platform previews for step 4 without saving anything.
     * Explicitly a simulation - the response says so and the view labels it.
     */
    public function preview(Request $request) {
        $request->validate([
            'platforms'   => 'required|array',
            'platforms.*' => Rule::in(array_keys(S::PLATFORMS)),
        ]);

        $post = new SocialPost($request->only([
            'title', 'caption', 'description', 'hashtags', 'cta', 'quiz_url', 'website_url', 'content_type',
        ]));

        $previews = [];
        foreach ($request->platforms as $platform) {
            $previews[$platform] = [
                'name'    => S::platformName($platform),
                'icon'    => S::platformIcon($platform),
                'color'   => S::platformColor($platform),
                'payload' => $this->composer->compose($post, $platform),
            ];
        }

        return response()->json([
            'simulated' => true,
            'note'      => 'This is an approximation rendered by the admin panel, not the platform itself.',
            'previews'  => $previews,
        ]);
    }

    /** Runs the platform pre-flight checks for the wizard's warning panel. */
    public function validateMedia(Request $request) {
        $request->validate([
            'platforms'   => 'array',
            'platforms.*' => Rule::in(array_keys(S::PLATFORMS)),
            'media'       => 'array',
        ]);

        $post = new SocialPost($request->only(['title', 'caption', 'hashtags', 'content_type']));
        $post->exists = false;

        $media = SocialMedia::whereIn('id', (array) $request->input('media', []))->get()
            ->each(fn ($m) => $m->setRelation('pivot', (object) ['role' => 'primary']));

        $issues = $this->validator->validate($post, (array) $request->input('platforms', []), $media);

        return response()->json([
            'issues'   => $issues,
            'blocking' => count(MediaValidator::blocking($issues)),
        ]);
    }

    /* ------------------------------------------------------------ Validation */

    /**
     * Validates the wizard submission.
     *
     * The platform list is validated against the registry and then against what
     * is actually connected, because the browser's copy of the capability matrix
     * is a convenience and not a source of truth.
     */
    protected function validatePost(Request $request): array {
        $validated = $request->validate([
            'title'              => 'nullable|string|max:190',
            'caption'            => 'required_without:description|nullable|string|max:20000',
            'description'        => 'nullable|string|max:60000',
            'hashtags'           => 'nullable|string|max:2000',
            'mentions'           => 'nullable|string|max:500',
            'cta'                => 'nullable|string|max:190',
            'website_url'        => 'nullable|url|max:500',
            'quiz_url'           => 'nullable|url|max:500',
            'social_campaign_id' => 'nullable|integer|exists:social_campaigns,id',
            'category_id'        => 'nullable|integer|exists:categories,id',
            'language'           => 'nullable|string|max:20',
            'content_type'       => ['nullable', Rule::in(array_keys(S::CONTENT_TYPES))],
            'scheduled_at'       => 'nullable|date',
            'utm_enabled'        => 'nullable|boolean',
            'idempotency_key'    => 'nullable|string|max:80',

            'platforms'          => 'required|array|min:1',
            'platforms.*'        => Rule::in(array_keys(S::PLATFORMS)),

            'accounts'           => 'nullable|array',
            'accounts.*'         => 'nullable|integer|exists:social_accounts,id',

            'media'              => 'nullable|array',
            'media.primary'      => 'nullable|array',
            'media.primary.*'    => 'integer|exists:social_media,id',
            'media.thumbnail'    => 'nullable|array',
            'media.thumbnail.*'  => 'integer|exists:social_media,id',

            'customisations'     => 'nullable|array',

            'action'             => ['required', Rule::in(['draft', 'schedule', 'publish', 'approval'])],
        ]);

        $platforms = array_values(array_unique($validated['platforms']));

        foreach ($platforms as $platform) {
            if (!$this->registry->make($platform)->isConfigured()) {
                abort(422, S::platformName($platform) . ' is not configured on this server.');
            }
        }

        // An account id supplied for one platform must actually belong to it.
        $accounts = [];
        foreach ((array) ($validated['accounts'] ?? []) as $platform => $accountId) {
            if (!$accountId || !in_array($platform, $platforms, true)) {
                continue;
            }
            $belongs = \App\Models\Social\SocialAccount::whereKey($accountId)->where('platform', $platform)->exists();
            if (!$belongs) {
                abort(422, 'The selected account does not belong to ' . S::platformName($platform) . '.');
            }
            $accounts[$platform] = (int) $accountId;
        }

        $customisations = $this->sanitiseCustomisations($validated['customisations'] ?? [], $platforms);

        return [
            'post' => array_merge(
                array_intersect_key($validated, array_flip([
                    'title', 'caption', 'description', 'hashtags', 'mentions', 'cta',
                    'website_url', 'quiz_url', 'social_campaign_id', 'category_id',
                    'language', 'content_type',
                ])),
                [
                    'utm_enabled'     => $request->boolean('utm_enabled', true),
                    'idempotency_key' => $validated['idempotency_key'] ?? null,
                    'accounts'        => $accounts,
                ]
            ),
            'platforms'      => $platforms,
            'customisations' => $customisations,
            'media'          => [
                'primary'   => array_values((array) ($validated['media']['primary'] ?? [])),
                'thumbnail' => array_values((array) ($validated['media']['thumbnail'] ?? [])),
            ],
            'action'       => $validated['action'],
            'scheduled_at' => !empty($validated['scheduled_at']) ? $this->parseSchedule($validated['scheduled_at']) : null,
        ];
    }

    /**
     * Keeps only fields the platform actually has, coercing types.
     *
     * The customisation payloads come straight from a dynamic form, so
     * accepting them wholesale would let anything be written into the JSON
     * column and handed to an adapter.
     */
    protected function sanitiseCustomisations(array $input, array $platforms): array {
        $allowed = [
            'caption', 'title', 'description', 'cta', 'first_comment', 'button_text',
            'button_url', 'visibility', 'playlist_id', 'category_id', 'as_short',
            'as_reel', 'share_to_feed', 'made_for_kids', 'location_id', 'template_name',
            'template_language', 'allow_freeform', 'recipients', 'hashtags', 'tags', 'thread',
        ];

        $booleans = ['as_short', 'as_reel', 'share_to_feed', 'made_for_kids', 'allow_freeform'];

        $out = [];

        foreach ($platforms as $platform) {
            $values = (array) ($input[$platform] ?? []);
            $clean  = [];

            foreach ($values as $key => $value) {
                if (!in_array($key, $allowed, true)) {
                    continue;
                }

                if (in_array($key, $booleans, true)) {
                    $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    continue;
                }

                if (in_array($key, ['hashtags', 'tags', 'thread'], true)) {
                    $clean[$key] = is_array($value)
                        ? array_values(array_filter(array_map(fn ($v) => trim((string) $v), $value)))
                        : array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $value) ?: [])));
                    continue;
                }

                if ($key === 'button_url' && $value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $clean[$key] = is_scalar($value) ? trim((string) $value) : null;
            }

            $out[$platform] = array_filter($clean, fn ($v) => $v !== null && $v !== '' && $v !== []);
        }

        return $out;
    }

    /**
     * Reads a submitted publish time.
     *
     * Times are parsed in the configured social timezone rather than the
     * server's, because "8 PM" means the audience's 8 PM.
     */
    protected function parseSchedule(string $value): Carbon {
        $tz = $this->settings()->timezone();

        return Carbon::parse($value, $tz)->setTimezone(config('app.timezone'));
    }

    /* --------------------------------------------------------------- Support */

    protected function formData(array $seed = [], ?SocialPost $post = null): array {
        return [
            'platforms'     => $this->platformContext(),
            'capabilities'  => $this->capabilitiesJson(),
            'campaigns'     => SocialCampaign::orderBy('name')->get(),
            'categories'    => Category::orderBy('name')->get(['id', 'name']),
            'templates'     => SocialTemplate::active()->orderBy('name')->get(),
            'hashtagGroups' => SocialHashtagGroup::active()->orderBy('name')->get(),
            'mediaLibrary'  => SocialMedia::latest('id')->limit(60)->get(),
            'seed'          => $seed,
            'aiEnabled'     => app(\App\Services\Social\SocialAiContentService::class)->isEnabled(),
            'settings'      => $this->settings(),
            // Always defined so the shared form view (which captures $post in a
            // closure via `use`) works on create too, where there is no post yet.
            'post'          => $post,
        ];
    }
}
