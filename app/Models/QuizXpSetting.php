<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizXpSetting extends Model {
    protected $table = 'quiz_xp_settings';
    protected $fillable = [
        'quiz_id',
        'xp_enabled',
        'use_global_rules',
        'completion_xp',
        'passing_bonus_xp',
        'perfect_score_bonus_xp',
        'first_attempt_bonus_xp',
        'metadata',
    ];

    protected $casts = [
        'xp_enabled' => 'boolean',
        'use_global_rules' => 'boolean',
        'metadata' => 'json',
    ];

    public function quiz(): BelongsTo {
        return $this->belongsTo(Quiz::class);
    }
}
