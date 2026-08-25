<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class BankOption extends Model {
    use HasTranslations;

    /** Fields with per-locale translation columns (option_text → option_text_hi). */
    protected array $translatable = ['option_text'];

    protected $fillable = [
        'bank_question_id', 'option_text', 'is_correct', 'sort_order',
        'option_text_hi',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function question() {
        return $this->belongsTo(BankQuestion::class, 'bank_question_id');
    }
}
