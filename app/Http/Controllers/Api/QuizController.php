<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendExam;
use App\Models\Quiz;
use App\Services\GamificationService;
use App\Services\QuizEvaluationService;
use Illuminate\Http\Request;

class QuizController extends Controller {
    /**
     * Get quiz result with XP breakdown and gamification data
     */
    public function getResult($examId) {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $attendExam = AttendExam::where('id', $examId)
            ->where('user_id', $user->id)
            ->with('exam', 'examAnswer')
            ->firstOrFail();

        // Make sure exam is evaluated
        if ($attendExam->status != 1) { // 1 = EXAM_COMPLETED
            QuizEvaluationService::evaluateExamWithXp($attendExam, true);
        }

        $exam = $attendExam->exam;
        $totalQuestions = $exam->questions()->count();

        // Get evaluation result
        $evaluation = [
            'correct_answers' => $attendExam->correct_count ?? 0,
            'wrong_answers' => $attendExam->incorrect_count ?? 0,
            'skipped_answers' => $totalQuestions - ($attendExam->correct_count ?? 0) - ($attendExam->incorrect_count ?? 0),
            'total_questions' => $totalQuestions,
            'score_percentage' => $attendExam->pass_percentage ?? 0,
            'passed' => ($attendExam->pass_percentage ?? 0) >= $exam->pass_percentage,
        ];

        // Get gamification data
        $gamificationService = new GamificationService($user);
        $gamificationStatus = $gamificationService->getUserGamificationStatus();

        // Get XP breakdown from transactions if available
        $xpBreakdown = $this->getXpBreakdown($user, $attendExam);

        return response()->json([
            'success' => true,
            'data' => [
                'exam_id' => $exam->id,
                'exam_title' => $exam->title,
                'result' => $evaluation,
                'xp' => [
                    'total_earned' => $attendExam->xp_awarded ?? 0,
                    'breakdown' => $xpBreakdown,
                ],
                'gamification' => [
                    'total_xp' => $gamificationStatus['total_xp'],
                    'current_level' => $gamificationStatus['current_level'],
                    'next_level' => $gamificationStatus['next_level'],
                    'current_streak' => $gamificationStatus['streak'],
                    'newly_unlocked_badges' => $this->getNewlyUnlockedBadges($user, $attendExam),
                ],
            ],
        ], 200);
    }

    /**
     * Get XP breakdown from transactions
     */
    private function getXpBreakdown($user, AttendExam $attendExam) {
        $transactions = \App\Models\XpTransaction::where('user_id', $user->id)
            ->where('reference_type', 'quiz')
            ->where('reference_id', $attendExam->exam_id)
            ->where('direction', 'earned')
            ->where('created_at', '>=', $attendExam->updated_at->subSeconds(60))
            ->get();

        $breakdown = [
            'quiz_completion' => 0,
            'correct_answers' => 0,
            'passing_bonus' => 0,
            'perfect_score_bonus' => 0,
            'first_attempt_bonus' => 0,
        ];

        foreach ($transactions as $tx) {
            switch ($tx->event_type) {
                case 'quiz_completed':
                    $breakdown['quiz_completion'] = $tx->xp_amount;
                    break;
                case 'correct_answer':
                    $breakdown['correct_answers'] = $tx->xp_amount;
                    break;
                case 'quiz_passed':
                    $breakdown['passing_bonus'] = $tx->xp_amount;
                    break;
                case 'perfect_score':
                    $breakdown['perfect_score_bonus'] = $tx->xp_amount;
                    break;
                case 'first_attempt':
                    $breakdown['first_attempt_bonus'] = $tx->xp_amount;
                    break;
            }
        }

        return $breakdown;
    }

    /**
     * Get newly unlocked badges from this quiz
     */
    private function getNewlyUnlockedBadges($user, AttendExam $attendExam) {
        // Get badges earned within the last minute of exam completion
        $badges = $user->badges()
            ->whereHas('pivot', function ($query) use ($attendExam) {
                $query->where('created_at', '>=', $attendExam->updated_at->subSeconds(60));
            })
            ->get(['badges.*'])
            ->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'icon' => $b->icon,
                'color' => $b->color,
                'reward_xp' => $b->reward_xp,
            ])
            ->toArray();

        return $badges;
    }

    /**
     * Get user's gamification profile
     */
    public function getGamificationProfile() {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $gamificationService = new GamificationService($user);
        $profile = $gamificationService->getUserGamificationStatus();

        return response()->json([
            'success' => true,
            'data' => $profile,
        ], 200);
    }
}
