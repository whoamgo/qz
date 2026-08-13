<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class QuizBankQuestion extends Pivot {
    protected $table = 'quiz_bank_question';

    protected $fillable = [
        'quiz_id', 'bank_question_id', 'question_order', 'marks'
    ];

    protected $casts = [
        'marks' => 'decimal:2',
    ];

    public function quiz() {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function bankQuestion() {
        return $this->belongsTo(BankQuestion::class, 'bank_question_id');
    }
}
