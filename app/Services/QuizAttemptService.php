<?php

namespace App\Services;

use App\Models\BankOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns the lifecycle of a public quiz attempt: start, answer, submit.
 *
 * Scoring is computed here, server-side, from bank_options.is_correct. The
 * browser never sends a score and correct answers are never serialised to the
 * page before submission.
 */
class QuizAttemptService {
    public function __construct(
        private XpService $xpService,
        private StreakService $streakService,
        private BadgeService $badgeService,
    ) {}

    /**
     * Returns the user's open attempt for this quiz, or starts a new one.
     * Reusing the open attempt is what makes "Continue Learning" work.
     */
    public function startOrResume(User $user, Quiz $quiz): QuizAttempt {
        $existing = QuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->inProgress()
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $quiz) {
            $questions = $quiz->questions()->get();

            $attempt = QuizAttempt::create([
                'user_id'         => $user->id,
                'quiz_id'         => $quiz->id,
                'status'          => QuizAttempt::STATUS_IN_PROGRESS,
                'total_questions' => $questions->count(),
                'started_at'      => now(),
            ]);

            // Seed one row per question so navigation state and "marked for
            // review" survive a page reload.
            $order = 0;
            $rows  = [];
            foreach ($questions as $question) {
                $rows[] = [
                    'attempt_id'       => $attempt->id,
                    'bank_question_id' => $question->id,
                    'question_order'   => ++$order,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }

            if ($rows) {
                QuizAttemptAnswer::insert($rows);
            }

            return $attempt;
        });
    }

    /**
     * Records a selection. is_correct is resolved here and stored, but is not
     * returned to the caller - the attempt page must not learn the answer.
     */
    public function saveAnswer(QuizAttempt $attempt, int $questionId, ?int $optionId, bool $markedForReview = false): bool {
        // A submitted attempt is immutable.
        if ($attempt->isCompleted()) {
            return false;
        }

        $answer = QuizAttemptAnswer::where('attempt_id', $attempt->id)
            ->where('bank_question_id', $questionId)
            ->first();

        if (!$answer) {
            return false;
        }

        // The option must belong to this question, otherwise a crafted request
        // could point at another question's correct option.
        $isCorrect = false;
        if ($optionId !== null) {
            $option = BankOption::where('id', $optionId)
                ->where('bank_question_id', $questionId)
                ->first();

            if (!$option) {
                return false;
            }

            $isCorrect = (bool) $option->is_correct;
        }

        $answer->selected_option_id = $optionId;
        $answer->is_correct         = $isCorrect;
        $answer->marked_for_review  = $markedForReview;
        $answer->save();

        return true;
    }

    public function toggleReview(QuizAttempt $attempt, int $questionId, bool $marked): bool {
        return (bool) QuizAttemptAnswer::where('attempt_id', $attempt->id)
            ->where('bank_question_id', $questionId)
            ->update(['marked_for_review' => $marked]);
    }

    /**
     * Grades the attempt and awards XP, streak and badges through the existing
     * gamification services. Idempotent: a completed attempt is returned as-is.
     */
    public function submit(QuizAttempt $attempt, ?int $timeTaken = null): QuizAttempt {
        if ($attempt->isCompleted()) {
            return $attempt;
        }

        $quiz = $attempt->quiz;
        $user = $attempt->user;

        DB::transaction(function () use ($attempt, $quiz, $timeTaken) {
            $answers = $attempt->answers()->get();

            $correct = $answers->where('is_correct', true)->count();
            $skipped = $answers->whereNull('selected_option_id')->count();
            $wrong   = $answers->count() - $correct - $skipped;

            $marksPerCorrect = (float) ($quiz->marks_per_correct ?: 1);
            $negative        = (float) ($quiz->negative_marking ?: 0);

            $score = ($correct * $marksPerCorrect) - ($wrong * $negative);
            $score = max(0, $score);
            $totalMarks = $answers->count() * $marksPerCorrect;
            $percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0;

            // Persist per-answer marks so the review screen can show them.
            foreach ($answers as $answer) {
                $answer->marks_awarded = $answer->is_correct
                    ? $marksPerCorrect
                    : ($answer->isSkipped() ? 0 : -$negative);
                $answer->save();
            }

            $attempt->fill([
                'status'        => QuizAttempt::STATUS_COMPLETED,
                'correct_count' => $correct,
                'wrong_count'   => $wrong,
                'skipped_count' => $skipped,
                'score'         => $score,
                'total_marks'   => $totalMarks,
                'percentage'    => $percentage,
                'passed'        => $percentage >= (float) $quiz->pass_percentage,
                'time_taken'    => $timeTaken ?? ($attempt->started_at ? now()->diffInSeconds($attempt->started_at) : 0),
                'submitted_at'  => now(),
            ]);
            $attempt->save();
        });

        $attempt->refresh();

        // Gamification runs outside the scoring transaction: an XP failure must
        // not roll back a legitimately graded attempt.
        $this->awardGamification($attempt, $user, $quiz);

        return $attempt->fresh();
    }

    private function awardGamification(QuizAttempt $attempt, User $user, Quiz $quiz): void {
        try {
            $isFirstAttempt = QuizAttempt::where('user_id', $user->id)
                ->where('quiz_id', $quiz->id)
                ->completed()
                ->count() <= 1;

            $perfect = $attempt->total_questions > 0
                && $attempt->correct_count === $attempt->total_questions;

            $result = $this->xpService->awardQuizCompletionXp(
                $user,
                $quiz->id,
                $attempt->correct_count,
                $attempt->total_questions,
                (bool) $attempt->passed,
                $perfect,
                $isFirstAttempt
            );

            $attempt->xp_awarded   = $result['total_xp'] ?? 0;
            $attempt->xp_breakdown = $result;
            $attempt->save();

            $this->streakService->updateStreak($user);
            $this->badgeService->checkAndAwardBadges($user);
        } catch (\Throwable $e) {
            // The attempt itself is already saved and correct; log and move on.
            Log::error('QuizAttemptService gamification failed: ' . $e->getMessage());
        }
    }

    /**
     * Questions shaped for the attempt screen. Deliberately omits is_correct
     * and correct_option_id so the answer key is never in the HTML source.
     */
    public function questionsForAttempt(QuizAttempt $attempt): array {
        $quiz = $attempt->quiz;

        $answers = $attempt->answers()->with(['question.options'])->get();
        $payload = [];

        foreach ($answers as $answer) {
            $question = $answer->question;
            if (!$question) {
                continue;
            }

            $options = $question->options->map(fn($o) => [
                'id'   => $o->id,
                'text' => $o->option_text,
            ])->values()->all();

            if ($quiz->randomize_options) {
                shuffle($options);
            }

            $payload[] = [
                'id'                => $question->id,
                'order'             => $answer->question_order,
                'text'              => $question->question_text,
                'hint'              => $question->hint,
                'type'              => $question->question_type,
                'options'           => $options,
                'selected_option_id' => $answer->selected_option_id,
                'marked_for_review' => (bool) $answer->marked_for_review,
            ];
        }

        if ($quiz->randomize_questions) {
            shuffle($payload);
        }

        return $payload;
    }
}
