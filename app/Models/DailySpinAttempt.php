<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Daily Spin per user per day. The (user_id, spin_date) unique index is the
 * authority on the daily limit — see the migration. Nothing here is trusted from
 * the browser; the controller/service always scope by the authenticated user.
 */
class DailySpinAttempt extends Model
{
    protected $table = 'daily_spin_attempts';

    const STATUS_SPUN     = 'spun';      // wheel resolved, awaiting an answer
    const STATUS_ANSWERED = 'answered';  // answer submitted (terminal)

    protected $fillable = [
        'user_id', 'spin_date', 'segment_key', 'segment_index', 'category_id',
        'question_id', 'selected_option_id', 'is_correct', 'xp_awarded',
        'status', 'answered_at',
        // Multi-question spin (10 per spin, submitted together).
        'question_ids', 'answers', 'total_questions', 'correct_count',
    ];

    protected $casts = [
        'spin_date'       => 'date',
        'is_correct'      => 'boolean',
        'xp_awarded'      => 'integer',
        'answered_at'     => 'datetime',
        'question_ids'    => 'array',
        'answers'         => 'array',
        'total_questions' => 'integer',
        'correct_count'   => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(BankQuestion::class, 'question_id');
    }

    public function isAnswered(): bool
    {
        return $this->status === self::STATUS_ANSWERED;
    }
}
