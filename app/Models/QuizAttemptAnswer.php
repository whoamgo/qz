<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttemptAnswer extends Model {
    protected $fillable = [
        'attempt_id', 'bank_question_id', 'selected_option_id',
        'is_correct', 'marked_for_review', 'marks_awarded', 'question_order',
    ];

    protected $casts = [
        'is_correct'        => 'boolean',
        'marked_for_review' => 'boolean',
        'marks_awarded'     => 'decimal:2',
    ];

    public function attempt() {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function question() {
        return $this->belongsTo(BankQuestion::class, 'bank_question_id');
    }

    public function selectedOption() {
        return $this->belongsTo(BankOption::class, 'selected_option_id');
    }

    public function isSkipped(): bool {
        return $this->selected_option_id === null;
    }
}
