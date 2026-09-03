<?php

namespace App\Http\Controllers\Admin\Social;

use App\Models\Social\SocialMedia;
use App\Services\Social\SocialAuditLogger;
use App\Services\Social\SocialMediaService;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;

class MediaController extends SocialBaseController {

    public function __construct(protected SocialMediaService $media) {}

    public function index(Request $request) {
        $pageTitle = 'Media Library';

        $query = SocialMedia::withCount('posts')->latest('id');

        if ($type = $request->type) {
            $query->where('type', $type);
        }
        if ($folder = $request->folder) {
            $query->where('folder', $folder);
        }
        if ($search = trim((string) $request->search)) {
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%$search%")
                    ->orWhere('title', 'like', "%$search%")
                    ->orWhere('tags', 'like', "%$search%");
            });
        }

        $items   = $query->paginate(getPaginate())->withQueryString();
        $folders = SocialMedia::whereNotNull('folder')->distinct()->pluck('folder');

        $totals = [
            'count'  => SocialMedia::count(),
            'images' => SocialMedia::images()->count(),
            'videos' => SocialMedia::videos()->count(),
            'bytes'  => (int) SocialMedia::sum('size'),
        ];

        $canProbe = $this->media->canProbeVideo();

        return view('admin.social.media.index', compact('pageTitle', 'items', 'folders', 'totals', 'canProbe'));
    }

    /**
     * Uploads one or more files.
     *
     * Validation lives in SocialMediaService, not in these rules: the request
     * rules are a first filter, but the extension allow-list, real MIME check
     * and magic-byte check are what actually decide, and they run on the bytes.
     */
    public function upload(Request $request) {
        $this->can(SocialPermission::MANAGE_MEDIA);

        $settings = $this->settings();
        $maxKb    = max($settings->max_image_mb, $settings->max_video_mb) * 1024;

        $request->validate([
            'files'   => 'required|array|max:20',
            'files.*' => "required|file|max:$maxKb",
            'folder'  => 'nullable|string|max:100',
        ]);

        $stored = [];
        $errors = [];

        foreach ($request->file('files') as $file) {
            try {
                $stored[] = $this->media->store($file, array_filter([
                    'folder' => $request->folder,
                ]));
            } catch (\RuntimeException $e) {
                $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
            } catch (\Throwable $e) {
                $errors[] = $file->getClientOriginalName() . ': the file could not be processed.';
            }
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => count($stored) > 0,
                'media'   => collect($stored)->map(fn ($m) => [
                    'id'        => $m->id,
                    'url'       => $m->url,
                    'thumb'     => $m->thumbnail_url,
                    'name'      => $m->original_name,
                    'type'      => $m->type,
                    'size'      => $m->size_for_humans,
                    'dimensions'=> $m->dimensions,
                    'duration'  => $m->duration,
                ]),
                'errors'  => $errors,
            ], $stored ? 200 : 422);
        }

        $notify = [];
        if ($stored) {
            $notify[] = ['success', count($stored) . ' file(s) uploaded.'];
        }
        foreach ($errors as $error) {
            $notify[] = ['error', $error];
        }

        return back()->withNotify($notify ?: [['error', 'Nothing was uploaded.']]);
    }

    public function update(Request $request, SocialMedia $media) {
        $this->can(SocialPermission::MANAGE_MEDIA);

        $data = $request->validate([
            'title'    => 'nullable|string|max:190',
            'alt_text' => 'nullable|string|max:255',
            'tags'     => 'nullable|string|max:500',
            'folder'   => 'nullable|string|max:100',
        ]);

        $media->update($data);

        $notify[] = ['success', 'Media details updated.'];
        return back()->withNotify($notify);
    }

    public function destroy(SocialMedia $media) {
        $this->can(SocialPermission::DELETE_MEDIA);

        // Deleting an asset a published post used would break its record, and
        // for platforms that fetch by URL it can break the live post too.
        if ($media->posts()->exists()) {
            $notify[] = ['error', 'This file is used by ' . $media->posts()->count() . ' post(s). Remove it from those posts first.'];
            return back()->withNotify($notify);
        }

        $name = $media->original_name;
        SocialAuditLogger::forModel('media.delete', $media, ['description' => 'Deleted media "' . $name . '"']);
        $media->purge();

        $notify[] = ['success', 'Deleted "' . $name . '".'];
        return back()->withNotify($notify);
    }

    /** JSON feed for the media picker inside the wizard. */
    public function browse(Request $request) {
        $query = SocialMedia::latest('id');

        if ($type = $request->type) {
            $query->where('type', $type);
        }
        if ($search = trim((string) $request->search)) {
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%$search%")->orWhere('title', 'like', "%$search%");
            });
        }

        return response()->json($query->limit(120)->get()->map(fn ($m) => [
            'id'         => $m->id,
            'url'        => $m->url,
            'thumb'      => $m->thumbnail_url,
            'name'       => $m->title ?: $m->original_name,
            'type'       => $m->type,
            'extension'  => $m->extension,
            'size'       => $m->size,
            'sizeLabel'  => $m->size_for_humans,
            'width'      => $m->width,
            'height'     => $m->height,
            'duration'   => $m->duration,
            'ratio'      => $m->aspect_ratio,
        ]));
    }
}
