<?php

namespace App\Services\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialMedia;
use App\Models\Social\SocialPost;
use App\Models\Social\SocialSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creating, updating and approving posts.
 *
 * Kept out of the controllers so the wizard, the bulk importer, the automations
 * and the "Share to Social" buttons all build posts the same way - including the
 * approval rules, which must not depend on which screen created the post.
 */
class SocialPostService {

    public function __construct(protected SocialPublisher $publisher) {}

    /**
     * Creates a post.
     *
     * `idempotency_key` comes from the form. Submitting the same form twice -
     * a double click, or a browser retry on a flaky connection - hits the
     * unique index and returns the post that already exists instead of a
     * duplicate.
     */
    public function create(array $data, array $platforms = [], array $customisations = [], array $mediaIds = []): SocialPost {
        $key = $data['idempotency_key'] ?? null;

        if ($key && $existing = SocialPost::where('idempotency_key', $key)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($data, $platforms, $customisations, $mediaIds, $key) {
            $settings = SocialSetting::config();

            $post = new SocialPost();
            $post->fill($this->fillable($data));

            $post->uuid            = (string) Str::uuid();
            $post->idempotency_key = $key;
            $post->created_by      = Auth::guard('admin')->id();
            $post->status          = S::DRAFT;
            $post->approval_status = $settings->require_approval ? S::APPROVAL_PENDING : S::APPROVAL_NOT_REQUIRED;

            $post->save();

            $this->syncMedia($post, $mediaIds);

            if ($platforms) {
                $this->publisher->syncTargets($post, $platforms, $customisations, $data['accounts'] ?? []);
            }

            SocialAuditLogger::forModel('post.create', $post, [
                'description' => 'Created post "' . $post->title . '"',
                'context'     => ['platforms' => $platforms],
            ]);

            return $post->fresh(['targets', 'media']);
        });
    }

    public function update(SocialPost $post, array $data, array $platforms = [], array $customisations = [], array $mediaIds = []): SocialPost {
        if (!$post->isEditable()) {
            throw new \RuntimeException('A post that is publishing or already published cannot be edited.');
        }

        return DB::transaction(function () use ($post, $data, $platforms, $customisations, $mediaIds) {
            $post->fill($this->fillable($data));

            // Editing content that was already approved invalidates that
            // approval - otherwise "approved" could be made to mean anything.
            if ($post->approval_status === S::APPROVAL_APPROVED && SocialSetting::config()->require_approval) {
                $post->approval_status = S::APPROVAL_PENDING;
                $post->approved_by     = null;
                $post->approved_at     = null;
                if ($post->status === S::APPROVED) {
                    $post->status = S::DRAFT;
                }
            }

            $post->save();

            if ($mediaIds !== null) {
                $this->syncMedia($post, $mediaIds);
            }

            if ($platforms) {
                $this->publisher->syncTargets($post, $platforms, $customisations, $data['accounts'] ?? []);
            }

            SocialAuditLogger::forModel('post.update', $post, [
                'description' => 'Updated post "' . $post->title . '"',
            ]);

            return $post->fresh(['targets', 'media']);
        });
    }

    /** Copies a post as a fresh draft, including its platform customisations. */
    public function duplicate(SocialPost $post): SocialPost {
        return DB::transaction(function () use ($post) {
            $copy = $post->replicate([
                'uuid', 'status', 'approval_status', 'scheduled_at', 'published_at',
                'idempotency_key', 'approved_by', 'approved_at',
            ]);

            $copy->uuid            = (string) Str::uuid();
            $copy->title           = Str::limit('Copy of ' . $post->title, 180, '');
            $copy->status          = S::DRAFT;
            $copy->approval_status = SocialSetting::config()->require_approval ? S::APPROVAL_PENDING : S::APPROVAL_NOT_REQUIRED;
            $copy->idempotency_key = null;
            $copy->created_by      = Auth::guard('admin')->id();
            $copy->save();

            foreach ($post->media as $media) {
                $copy->media()->attach($media->id, [
                    'role'     => $media->pivot->role,
                    'order'    => $media->pivot->order,
                    'platform' => $media->pivot->platform,
                ]);
            }

            foreach ($post->targets as $target) {
                $copy->targets()->create([
                    'platform'          => $target->platform,
                    'social_account_id' => $target->social_account_id,
                    'payload'           => $target->payload,
                    'status'            => S::DRAFT,
                    'idempotency_key'   => (string) Str::uuid(),
                ]);
            }

            SocialAuditLogger::forModel('post.duplicate', $copy, [
                'description' => 'Duplicated post #' . $post->id,
            ]);

            return $copy->fresh(['targets', 'media']);
        });
    }

    /* ------------------------------------------------------------- Approval */

    public function submitForApproval(SocialPost $post): void {
        $post->approval_status = S::APPROVAL_PENDING;
        $post->transitionTo(S::PENDING_APPROVAL);

        SocialAuditLogger::forModel('post.submit_approval', $post, [
            'description' => 'Submitted "' . $post->title . '" for approval',
        ]);
    }

    public function approve(SocialPost $post): void {
        $post->approval_status = S::APPROVAL_APPROVED;
        $post->approved_by     = Auth::guard('admin')->id();
        $post->approved_at     = now();

        // Approving something already scheduled keeps its slot; approving a
        // pending draft just clears it for scheduling.
        $post->transitionTo($post->scheduled_at && $post->scheduled_at->isFuture() ? S::SCHEDULED : S::APPROVED);

        SocialAuditLogger::forModel('post.approve', $post, [
            'description' => 'Approved "' . $post->title . '"',
        ]);
    }

    public function reject(SocialPost $post, ?string $reason = null): void {
        $post->approval_status = S::APPROVAL_REJECTED;
        $post->meta            = array_merge($post->meta ?? [], ['rejection_reason' => $reason]);
        $post->transitionTo(S::REJECTED);

        SocialAuditLogger::forModel('post.reject', $post, [
            'description' => 'Rejected "' . $post->title . '"' . ($reason ? ': ' . $reason : ''),
        ]);
    }

    /* -------------------------------------------------------------- Helpers */

    /**
     * Attaches media by id.
     *
     * @param array $mediaIds ['primary' => [1,2], 'thumbnail' => [3]]
     */
    public function syncMedia(SocialPost $post, array $mediaIds): void {
        $attach = [];
        $order  = 0;

        foreach (['primary', 'thumbnail', 'extra'] as $role) {
            foreach ((array) ($mediaIds[$role] ?? []) as $id) {
                if (!$id) {
                    continue;
                }
                // Ignore ids that do not exist rather than failing the save -
                // the id came from a form and may reference deleted media.
                if (!SocialMedia::whereKey($id)->exists()) {
                    continue;
                }
                $attach[$id] = ['role' => $role, 'order' => $order++, 'platform' => null];
            }
        }

        $post->media()->sync($attach);
        $post->load('media');

        $this->refreshContentType($post);
    }

    /** Keeps content_type honest about what is actually attached. */
    protected function refreshContentType(SocialPost $post): void {
        $media = $post->media->filter(fn ($m) => ($m->pivot->role ?? 'primary') === 'primary');

        $type = match (true) {
            $media->contains(fn ($m) => $m->type === 'video') => $this->videoKind($post, $media),
            $media->isNotEmpty()                              => S::CONTENT_IMAGE,
            (bool) ($post->quiz_url || $post->website_url)    => S::CONTENT_LINK,
            default                                           => S::CONTENT_TEXT,
        };

        if ($post->content_type !== $type) {
            // A vertical video the admin explicitly marked as a reel keeps that
            // marking; only the generic classification is recomputed.
            if (!in_array($post->content_type, [S::CONTENT_REEL, S::CONTENT_SHORT], true) || $type !== S::CONTENT_VIDEO) {
                $post->content_type = $type;
                $post->save();
            }
        }
    }

    /** A portrait video defaults to short-form, which is what it is for. */
    protected function videoKind(SocialPost $post, $media): string {
        $video = $media->firstWhere('type', 'video');
        $ratio = $video?->ratio();

        return ($ratio !== null && $ratio < 1.0) ? S::CONTENT_REEL : S::CONTENT_VIDEO;
    }

    protected function fillable(array $data): array {
        return array_intersect_key($data, array_flip([
            'title', 'caption', 'description', 'hashtags', 'mentions', 'cta',
            'website_url', 'quiz_url', 'social_campaign_id', 'category_id',
            'language', 'content_type', 'source_type', 'source_id',
            'utm_enabled', 'ai_generated', 'meta',
        ]));
    }
}
