<?php

namespace App\Services\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialAutomation;
use App\Models\Social\SocialPost;
use App\Models\Social\SocialSetting;

/**
 * Runs recurring campaigns.
 *
 * Two safety rules govern everything here:
 *
 *  1. `is_active` is checked immediately before each run, not just when the
 *     schedule was computed. Switching an automation off in the UI stops the
 *     next run even if it was already due.
 *  2. `auto_publish` is off by default. An automation creates a draft (or a
 *     pending-approval post) unless the admin has explicitly said it may
 *     publish on its own - so nothing reaches a live audience unreviewed
 *     because of a misconfigured schedule.
 */
class SocialAutomationRunner {

    public function __construct(
        protected QuizMitraContentSource $source,
        protected SocialPostService $posts,
        protected SocialPublisher $publisher,
    ) {}

    /** @return array{ran:int,created:int,published:int,skipped:int,errors:int} */
    public function runDue(): array {
        $stats = ['ran' => 0, 'created' => 0, 'published' => 0, 'skipped' => 0, 'errors' => 0];

        if (!SocialSetting::config()->enabled) {
            return $stats;
        }

        foreach (SocialAutomation::due()->get() as $automation) {
            // Re-read: the list was fetched a moment ago and the emergency stop
            // may have been hit since.
            $automation->refresh();
            if (!$automation->is_active) {
                $stats['skipped']++;
                continue;
            }

            $stats['ran']++;

            try {
                $post = $this->runOnce($automation);

                if ($post) {
                    $stats['created']++;
                    if ($automation->auto_publish) {
                        $stats['published']++;
                    }
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $automation->last_error = mb_substr($e->getMessage(), 0, 1000);
                $automation->save();

                SocialAuditLogger::forModel('automation.save', $automation, [
                    'result'      => 'failed',
                    'description' => 'Automation "' . $automation->name . '" failed: ' . $e->getMessage(),
                ]);
            } finally {
                $automation->last_run_at = now();
                $automation->next_run_at = $automation->calculateNextRun();
                $automation->run_count++;
                $automation->save();
            }
        }

        return $stats;
    }

    /**
     * Executes one automation.
     *
     * @return SocialPost|null null when the content source had nothing to post.
     */
    public function runOnce(SocialAutomation $automation): ?SocialPost {
        $seed = $this->source->resolveAutomationSource($automation->source_type, $automation->source_config ?? []);

        if (!$seed && $automation->source_type !== 'custom') {
            $automation->last_error = 'No content was available from the "' . $automation->source_name . '" source for this run.';
            $automation->save();
            return null;
        }

        $data = $this->applyTemplate($automation, $seed);

        // A deterministic key per automation per scheduled slot: if the runner
        // executes twice for the same slot (an overlapping cron, a retried
        // request), the second run returns the first run's post.
        $slot = ($automation->next_run_at ?: now())->format('YmdHi');
        $data['idempotency_key'] = 'auto:' . $automation->id . ':' . $slot;

        $platforms = array_values(array_filter(
            (array) $automation->platforms,
            fn ($p) => app(PlatformRegistry::class)->has($p)
        ));

        if (!$platforms) {
            $automation->last_error = 'No platforms are selected on this automation.';
            $automation->save();
            return null;
        }

        $post = $this->posts->create($data, $platforms);

        if ($seed['image_path'] ?? null) {
            // Existing Quiz Mitra artwork is referenced, not re-uploaded.
            $post->meta = array_merge($post->meta ?? [], ['source_image' => $seed['image_path']]);
            $post->save();
        }

        $automation->last_error = null;
        $automation->save();

        SocialAuditLogger::forModel('automation.save', $automation, [
            'description' => 'Automation "' . $automation->name . '" created post #' . $post->id,
            'context'     => ['platforms' => $platforms, 'auto_publish' => $automation->auto_publish],
        ]);

        if (!$automation->auto_publish) {
            // Leave it where a human will see it.
            if (SocialSetting::config()->require_approval) {
                $this->posts->submitForApproval($post);
            }
            return $post;
        }

        // Auto-publishing still respects the approval requirement - it is a
        // global gate, and an automation must not be a way around it.
        if (SocialSetting::config()->require_approval && $post->approval_status !== S::APPROVAL_APPROVED) {
            $this->posts->submitForApproval($post);
            return $post;
        }

        $this->publisher->dispatch($post);

        return $post;
    }

    /**
     * Renders the automation's caption template over the seed content.
     * With no template, the seed's own copy is used unchanged.
     */
    protected function applyTemplate(SocialAutomation $automation, array $seed): array {
        $hashtags = $automation->hashtagGroup?->asString() ?: ($seed['hashtags'] ?? '');

        $variables = array_merge($seed, [
            'hashtags'  => $hashtags,
            'date'      => now()->format('d M Y'),
            'time'      => now()->format('h:i A'),
            'site_name' => gs('site_name') ?: config('app.name'),
            'category'  => $seed['category'] ?? '',
            'cta'       => $seed['cta'] ?? '',
        ]);

        $template = $automation->template;

        $rendered = $template ? $template->render($variables) : [];

        if ($template) {
            $template->increment('usage_count');
        }

        return array_filter([
            'title'              => ($rendered['title'] ?? '') ?: ($seed['title'] ?? null),
            'caption'            => ($rendered['caption'] ?? '') ?: ($seed['caption'] ?? null),
            'description'        => ($rendered['description'] ?? '') ?: ($seed['description'] ?? null),
            'hashtags'           => ($rendered['hashtags'] ?? '') ?: $hashtags,
            'cta'                => ($rendered['cta'] ?? '') ?: ($seed['cta'] ?? null),
            'quiz_url'           => $seed['quiz_url'] ?? null,
            'website_url'        => $seed['website_url'] ?? null,
            'category_id'        => $seed['category_id'] ?? null,
            'content_type'       => $seed['content_type'] ?? S::CONTENT_TEXT,
            'source_type'        => $seed['source_type'] ?? 'automation',
            'source_id'          => $seed['source_id'] ?? $automation->id,
            'social_campaign_id' => $automation->social_campaign_id,
            'utm_enabled'        => true,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Recomputes next_run_at, e.g. after the schedule was edited. */
    public function reschedule(SocialAutomation $automation): void {
        $automation->next_run_at = $automation->is_active ? $automation->calculateNextRun() : null;
        $automation->save();
    }
}
