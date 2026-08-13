<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizXpSetting;
use App\Models\GamificationSetting;
use App\Models\XpRule;

class QuizXpCalculator {
    private Quiz $quiz;
    private QuizXpSetting $xpSettings;
    private GamificationSetting $gamificationSettings;
    private int $correctAnswers;
    private int $totalQuestions;
    private bool $passed;
    private bool $perfect;

    public function __construct(Quiz $quiz, int $correctAnswers, int $totalQuestions, bool $passed, bool $perfect = false) {
        $this->quiz = $quiz;
        $this->xpSettings = $quiz->xpSettings ?? new QuizXpSetting();
        $this->gamificationSettings = GamificationSetting::getInstance();
        $this->correctAnswers = $correctAnswers;
        $this->totalQuestions = $totalQuestions;
        $this->passed = $passed;
        $this->perfect = $perfect;
    }

    public function calculate(): array {
        $breakdown = [
            'quiz_completion' => 0,
            'correct_answers' => 0,
            'passing_bonus' => 0,
            'perfect_score_bonus' => 0,
            'first_attempt_bonus' => 0,
            'total' => 0,
        ];

        if (!$this->isXpEnabled()) {
            return $breakdown;
        }

        // Quiz completion XP
        $breakdown['quiz_completion'] = $this->getCompletionXp();

        // Correct answers XP (based on difficulty)
        $breakdown['correct_answers'] = $this->getCorrectAnswerXp();

        // Passing bonus
        if ($this->passed) {
            $breakdown['passing_bonus'] = $this->getPassingBonusXp();
        }

        // Perfect score bonus
        if ($this->perfect) {
            $breakdown['perfect_score_bonus'] = $this->getPerfectScoreBonusXp();
        }

        // First attempt bonus (will be set by caller)
        // $breakdown['first_attempt_bonus'] = ...

        $breakdown['total'] = array_sum([
            $breakdown['quiz_completion'],
            $breakdown['correct_answers'],
            $breakdown['passing_bonus'],
            $breakdown['perfect_score_bonus'],
            $breakdown['first_attempt_bonus'],
        ]);

        return $breakdown;
    }

    public function getCompletionXp(): int {
        if (!$this->xpSettings->xp_enabled) {
            return 0;
        }
        return (int) ($this->xpSettings->completion_xp ?? 0);
    }

    public function getPassingBonusXp(): int {
        if (!$this->xpSettings->xp_enabled) {
            return 0;
        }
        return (int) ($this->xpSettings->passing_bonus_xp ?? 0);
    }

    public function getPerfectScoreBonusXp(): int {
        if (!$this->xpSettings->xp_enabled) {
            return 0;
        }
        return (int) ($this->xpSettings->perfect_score_bonus_xp ?? 0);
    }

    public function getFirstAttemptBonusXp(): int {
        if (!$this->xpSettings->xp_enabled) {
            return 0;
        }
        return (int) ($this->xpSettings->first_attempt_bonus_xp ?? 0);
    }

    public function getCorrectAnswerXp(): int {
        if (!$this->xpSettings->xp_enabled || !$this->xpSettings->use_global_rules) {
            return 0;
        }

        $xpPerCorrectAnswer = 0;

        // Get XP rule for correct answers based on difficulty
        $difficulty = $this->quiz->difficulty;
        $baseXp = match($difficulty) {
            'easy' => 5,
            'medium' => 10,
            'hard' => 15,
            default => 10,
        };

        return $this->correctAnswers * $baseXp;
    }

    public function isXpEnabled(): bool {
        if (!$this->xpSettings->xp_enabled) {
            return false;
        }
        return $this->gamificationSettings->is_enabled ?? false;
    }
}
