<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model {
    protected $fillable = ['user_id', 'quiz_id', 'bank_question_id', 'note'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function quiz() {
        return $this->belongsTo(Quiz::class);
    }

    public function question() {
        return $this->belongsTo(BankQuestion::class, 'bank_question_id');
    }

    public function scopeQuizzes($query) {
        return $query->whereNotNull('quiz_id');
    }

    public function scopeQuestions($query) {
        return $query->whereNotNull('bank_question_id');
    }
}
