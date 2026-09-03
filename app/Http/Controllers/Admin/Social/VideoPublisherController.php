<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialCampaign;
use App\Models\Social\SocialMedia;
use App\Models\Social\SocialPost;
use App\Services\Social\MediaValidator;
use App\Services\Social\SocialMediaService;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The video-first entry point into the wizard.
 *
 * Same pipeline as a normal post - it just starts from the video, defaults to
 * the platforms that take video, and runs the vertical-video checks up front
 * where they are cheap to act on.
 */
class VideoPublisherController extends SocialBaseController {

    public function index(SocialMediaService $mediaService) {
        $this->can(SocialPermission::CREATE);

        $pageTitle = 'Video Publisher';

        $videoPlatforms = array_values(array_filter(
            array_keys(S::PLATFORMS),
            fn ($p) => \App\Services\Social\PlatformCapability::supports($p, 'video')
        ));

        $shortPlatforms = array_values(array_filter(
            array_keys(S::PLATFORMS),
            fn ($p) => \App\Services\Social\PlatformCapability::supports($p, 'shorts')
                || \App\Services\Social\PlatformCapability::supports($p, 'reels')
        ));

        return view('admin.social.video.index', [
            'pageTitle'      => $pageTitle,
            'platforms'      => $this->platformContext(),
            'capabilities'   => $this->capabilitiesJson(),
            'videoPlatforms' => $videoPlatforms,
            'shortPlatforms' => $shortPlatforms,
            'videos'         => SocialMedia::videos()->latest('id')->limit(40)->get(),
            'thumbnails'     => SocialMedia::images()->latest('id')->limit(40)->get(),
            'campaigns'      => SocialCampaign::orderBy('name')->get(),
            'canProbe'       => $mediaService->canProbeVideo(),
            'settings'       => $this->settings(),
        ]);
    }

    /**
     * Pre-flight check for a chosen video against chosen platforms.
     *
     * Answers "will this work?" before an upload is attempted, which on a large
     * video is the difference between a warning and ten minutes wasted.
     */
    public function check(Request $request, MediaValidator $validator) {
        $request->validate([
            'media_id'    => 'required|integer|exists:social_media,id',
            'platforms'   => 'required|array|min:1',
            'platforms.*' => Rule::in(array_keys(S::PLATFORMS)),
            'as_short'    => 'nullable|boolean',
        ]);

        $video = SocialMedia::findOrFail($request->media_id);

        if ($video->type !== 'video') {
            return response()->json([
                'issues'   => [['severity' => 'error', 'platform' => '', 'message' => 'That file is not a video.']],
                'blocking' => 1,
            ], 422);
        }

        $post = new SocialPost([
            'title'        => $request->input('title'),
            'caption'      => $request->input('caption'),
            'hashtags'     => $request->input('hashtags'),
            'content_type' => $request->boolean('as_short') ? S::CONTENT_SHORT : S::CONTENT_VIDEO,
        ]);

        $video->setRelation('pivot', (object) ['role' => 'primary']);

        $issues = $validator->validate($post, $request->platforms, collect([$video]));

        return response()->json([
            'issues'   => $issues,
            'blocking' => count(MediaValidator::blocking($issues)),
            'video'    => [
                'name'     => $video->original_name,
                'size'     => $video->size_for_humans,
                'duration' => $video->duration,
                'ratio'    => $video->aspect_ratio,
                'width'    => $video->width,
                'height'   => $video->height,
            ],
        ]);
    }
}
