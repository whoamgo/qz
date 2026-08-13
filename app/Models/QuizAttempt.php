<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model {
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_ABANDONED   = 'abandoned';

    protected $fillable = [
        'user_id', 'quiz_id', 'status', 'total_questions', 'correct_count',
        'wrong_count', 'skipped_count', 'score', 'total_marks', 'percentage',
        'passed', 'time_taken', 'xp_awarded', 'xp_breakdown',
        'started_at', 'submitted_at',
    ];

    protected $casts = [
        'passed'       => 'boolean',
        'score'        => 'decimal:2',
        'total_marks'  => 'decimal:2',
        'percentage'   => 'decimal:2',
        'xp_breakdown' => 'array',
        'started_at'   => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function quiz() {
        return $this->belongsTo(Quiz::class);
    }

    public function answers() {
        return $this->hasMany(QuizAttemptAnswer::class, 'attempt_id')->orderBy('question_order');
    }

    public function scopeCompleted($query) {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeInProgress($query) {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function isCompleted(): bool {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** Seconds still available, or null when the quiz is untimed. */
    public function remainingSeconds(): ?int {
        $limit = (int) ($this->quiz->time_limit ?? 0);
        if ($limit <= 0) {
            return null;
        }

        $elapsed = $this->started_at ? now()->diffInSeconds($this->started_at) : 0;
        return max(0, ($limit * 60) - $elapsed);
    }

    public function answeredCount(): int {
        return $this->answers()->whereNotNull('selected_option_id')->count();
    }
}
