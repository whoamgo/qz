<?php

namespace App\Observers;

use App\Models\Quiz;
use App\Models\Social\SocialAccount;
use App\Models\Social\SocialSetting;
use App\Services\Social\QuizMitraContentSource;
use App\Services\Social\SocialPostService;
use Illuminate\Support\Facades\Log;

/**
 * Creates a social draft when a quiz is published.
 *
 * Strictly a *draft*: it never schedules and never publishes, whatever the
 * automation settings say. Publishing something to a live audience as a side
 * effect of saving a quiz would be a surprise, and surprises are the one thing
 * a publishing system must not do.
 *
 * Off by default - controlled by social_settings.auto_draft_from_quiz.
 */
class QuizSocialObserver {

    public function saved(Quiz $quiz): void {
        // Only on the transition into published, not on every subsequent save
        // of an already-published quiz.
        if ($quiz->status !== Quiz::STATUS_PUBLISHED) {
            return;
        }

        if (!$quiz->wasChanged('status') && !$quiz->wasRecentlyCreated) {
            return;
        }

        try {
            $settings = SocialSetting::config();

            if (!$settings->enabled || !$settings->auto_draft_from_quiz) {
                return;
            }

            $platforms = SocialAccount::connected()->pluck('platform')->unique()->values()->all();
            if (!$platforms) {
                return;
            }

            $seed = app(QuizMitraContentSource::class)->fromQuiz($quiz);
            if (!$seed) {
                return;
            }

            app(SocialPostService::class)->create(array_merge($seed, [
                // Deterministic: re-saving the quiz cannot produce a second draft.
                'idempotency_key' => 'quiz-auto:' . $quiz->id,
            ]), $platforms);
        } catch (\Throwable $e) {
            // A failure here must never block saving the quiz itself.
            Log::warning('[social] auto-draft from quiz failed', [
                'quiz'  => $quiz->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
