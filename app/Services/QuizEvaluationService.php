<?php

namespace App\Services;

use App\Models\User;
use App\Models\Quiz;
use App\Models\Exam;
use App\Models\AttendExam;
use App\Models\ExamAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizEvaluationService {
    /**
     * Evaluate exam answers and calculate score
     */
    public static function evaluateExam(AttendExam $attendExam): array {
        $exam = $attendExam->exam;
        $user = $attendExam->user;
        $answers = $attendExam->examAnswer()->pluck('option_id', 'question_id')->toArray();

        $totalQuestions = $exam->questions()->count();
        $correctAnswers = 0;
        $skippedAnswers = $totalQuestions - count($answers);

        foreach ($answers as $questionId => $selectedOptionId) {
            $question = $exam->questions()->find($questionId);
            if ($question && $question->result_option_id == $selectedOptionId) {
                $correctAnswers++;
            }
        }

        $percentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100) : 0;
        $passed = $percentage >= $exam->pass_percentage;
        $perfect = $correctAnswers === $totalQuestions;

        // Update AttendExam with calculated results
        $attendExam->update([
            'correct_count' => $correctAnswers,
            'incorrect_count' => ($totalQuestions - $correctAnswers - $skippedAnswers),
            'pass_percentage' => $percentage,
            'status' => 1, // EXAM_COMPLETED
        ]);

        return [
            'correct_answers' => $correctAnswers,
            'total_questions' => $totalQuestions,
            'percentage' => $percentage,
            'passed' => $passed,
            'perfect' => $perfect,
        ];
    }

    /**
     * Evaluate exam and award XP
     */
    public static function evaluateExamWithXp(AttendExam $attendExam, bool $forceEvaluate = false): array {
        try {
            // Skip if already evaluated
            if ($attendExam->status != 0 && !$forceEvaluate) { // 0 = WAITING_RESULT
                return self::getEvaluationResult($attendExam);
            }

            // Evaluate exam
            $evaluation = self::evaluateExam($attendExam);

            $user = $attendExam->user;
            $exam = $attendExam->exam;

            // Try to find corresponding quiz by matching on exam ID or title
            // For now, we'll create a virtual quiz from exam data
            $quiz = self::getOrCreateQuizFromExam($exam);

            // Award XP if quiz found and XP system enabled
            $xpResult = [];
            if ($quiz) {
                $gamificationService = new GamificationService($user);
                $xpResult = $gamificationService->processQuizCompletion(
                    $quiz,
                    $evaluation['correct_answers'],
                    $evaluation['total_questions'],
                    $evaluation['passed'],
                    true // isFirstAttempt
                );

                // Store XP awarded in AttendExam
                if (!empty($xpResult['total_xp_earned'])) {
                    $attendExam->update(['xp_awarded' => $xpResult['total_xp_earned']]);
                }
            }

            return [
                'evaluation' => $evaluation,
                'xp_result' => $xpResult,
            ];
        } catch (\Exception $e) {
            Log::error('QuizEvaluationService::evaluateExamWithXp failed: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'evaluation' => self::getEvaluationResult($attendExam),
                'xp_result' => [],
            ];
        }
    }

    /**
     * Get or create a Quiz from an Exam for XP purposes
     * Since exams and quizzes are separate, we use the exam data directly
     */
    private static function getOrCreateQuizFromExam(Exam $exam): ?Quiz {
        // Try to find a matching quiz
        $quiz = Quiz::where('title', $exam->title)->first();

        if (!$quiz) {
            // Create a virtual quiz object from exam data for compatibility
            // This allows XP system to work with existing exams
            $quiz = new Quiz();
            $quiz->id = $exam->id;
            $quiz->title = $exam->title;
            $quiz->slug = $exam->slug ?? str()->slug($exam->title);
            $quiz->difficulty = $exam->difficulty ?? 'medium';
            $quiz->pass_percentage = $exam->pass_percentage ?? 70;

            // Create XP settings for this exam if needed
            $xpSettings = \App\Models\QuizXpSetting::firstOrCreate(
                ['quiz_id' => $exam->id],
                [
                    'xp_enabled' => true,
                    'use_global_rules' => true,
                    'completion_xp' => 50,
                    'passing_bonus_xp' => 25,
                    'perfect_score_bonus_xp' => 100,
                    'first_attempt_bonus_xp' => 25,
                ]
            );

            $quiz->setRelation('xpSettings', $xpSettings);
        }

        return $quiz;
    }

    /**
     * Get evaluation result from AttendExam model
     */
    private static function getEvaluationResult(AttendExam $attendExam): array {
        $totalQuestions = $attendExam->exam->questions()->count();
        return [
            'correct_answers' => $attendExam->correct_count ?? 0,
            'total_questions' => $totalQuestions,
            'percentage' => $attendExam->pass_percentage ?? 0,
            'passed' => ($attendExam->pass_percentage ?? 0) >= $attendExam->exam->pass_percentage,
            'perfect' => ($attendExam->correct_count ?? 0) === $totalQuestions,
        ];
    }
}
