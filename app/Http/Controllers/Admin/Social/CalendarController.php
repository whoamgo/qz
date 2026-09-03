<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialPost;
use App\Services\Social\SocialPermission;
use App\Services\Social\SocialPublisher;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends SocialBaseController {

    public function index() {
        $pageTitle = 'Content Calendar';

        $legend = [
            S::PUBLISHED           => ['label' => 'Published', 'colour' => '#28a745'],
            S::SCHEDULED           => ['label' => 'Scheduled', 'colour' => '#ffc107'],
            S::DRAFT               => ['label' => 'Draft',     'colour' => '#17a2b8'],
            S::FAILED              => ['label' => 'Failed',    'colour' => '#dc3545'],
            S::PARTIALLY_PUBLISHED => ['label' => 'Partial',   'colour' => '#fd7e14'],
            S::CANCELLED           => ['label' => 'Cancelled', 'colour' => '#adb5bd'],
        ];

        return view('admin.social.calendar.index', compact('pageTitle', 'legend'));
    }

    /** Feed for the calendar grid. */
    public function events(Request $request) {
        $request->validate([
            'start' => 'required|date',
            'end'   => 'required|date',
        ]);

        $start = Carbon::parse($request->start)->startOfDay();
        $end   = Carbon::parse($request->end)->endOfDay();

        $posts = SocialPost::with('targets')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('scheduled_at', [$start, $end])
                    ->orWhereBetween('published_at', [$start, $end])
                    // Drafts with no date still belong on the day they were made,
                    // otherwise they are invisible on the calendar.
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('scheduled_at')
                            ->whereNull('published_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->get();

        return response()->json($posts->map(function (SocialPost $post) {
            $when = $post->published_at ?: ($post->scheduled_at ?: $post->created_at);

            return [
                'id'        => $post->id,
                'title'     => $post->title ?: strLimit(strip_tags((string) $post->caption), 40),
                'start'     => $when?->toIso8601String(),
                'date'      => $when?->toDateString(),
                'time'      => $when?->format('H:i'),
                'status'    => $post->status,
                'statusName'=> $post->status_name,
                'colour'    => $this->colourFor($post->status),
                'platforms' => $post->targets->map(fn ($t) => [
                    'key'    => $t->platform,
                    'icon'   => S::platformIcon($t->platform),
                    'colour' => S::platformColor($t->platform),
                    'status' => $t->status,
                ])->values(),
                'thumb'     => $post->previewImage(),
                'url'       => route('admin.social.posts.show', $post->id),
                // Only future, not-yet-live posts can be dragged.
                'movable'   => in_array($post->status, [S::DRAFT, S::SCHEDULED, S::APPROVED, S::PENDING_APPROVAL, S::FAILED], true),
            ];
        }));
    }

    /**
     * Drag-and-drop reschedule.
     *
     * The server re-derives everything: it re-checks the ability, re-checks the
     * post is movable and re-parses the time. A dragged card is a request, not
     * an instruction.
     */
    public function move(Request $request, SocialPublisher $publisher) {
        $this->can(SocialPermission::SCHEDULE);

        $request->validate([
            'post_id'      => 'required|integer|exists:social_posts,id',
            'scheduled_at' => 'required|date',
        ]);

        $post = SocialPost::findOrFail($request->post_id);

        if (in_array($post->status, [S::PUBLISHED, S::PUBLISHING, S::QUEUED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This post is already ' . $post->status_name . ' and cannot be moved.',
            ], 422);
        }

        $when = Carbon::parse($request->scheduled_at, $this->settings()->timezone())
            ->setTimezone(config('app.timezone'));

        if ($when->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'That time is in the past. Pick a future slot, or publish the post now.',
            ], 422);
        }

        try {
            $publisher->cancel($post);
            $post->refresh();

            foreach ($post->targets as $target) {
                if ($target->status === S::CANCELLED) {
                    $target->update(['status' => S::DRAFT]);
                }
            }
            if ($post->status === S::CANCELLED) {
                $post->update(['status' => S::DRAFT]);
            }

            $publisher->dispatch($post->fresh('targets'), $when);

            return response()->json([
                'success' => true,
                'message' => 'Moved to ' . $when->format('d M Y, h:i A') . '.',
            ]);
        } catch (\RuntimeException $e) {
            // Not fatal - the post keeps its previous state and the grid reverts.
            $post->update(['scheduled_at' => $when]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    protected function colourFor(string $status): string {
        return match ($status) {
            S::PUBLISHED           => '#28a745',
            S::SCHEDULED, S::QUEUED, S::APPROVED => '#ffc107',
            S::DRAFT, S::PENDING_APPROVAL        => '#17a2b8',
            S::FAILED, S::REJECTED               => '#dc3545',
            S::PARTIALLY_PUBLISHED, S::RETRYING  => '#fd7e14',
            S::PUBLISHING                        => '#007bff',
            default                              => '#adb5bd',
        };
    }
}
