<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankOption extends Model {
    protected $fillable = [
        'bank_question_id', 'option_text', 'is_correct', 'sort_order'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function question() {
        return $this->belongsTo(BankQuestion::class, 'bank_question_id');
    }
}
