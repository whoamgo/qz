<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizRoomParticipant extends Model {

    const ROLE_HOST   = 'host';
    const ROLE_PLAYER = 'player';

    const STATUS_JOINED   = 'joined';
    const STATUS_PLAYING  = 'playing';
    const STATUS_FINISHED = 'finished';
    const STATUS_LEFT     = 'left';

    protected $fillable = [
        'room_id', 'user_id', 'role', 'status', 'quiz_attempt_id',
        'score', 'correct_answers', 'wrong_answers',
        'joined_at', 'left_at', 'completed_at',
    ];

    protected $casts = [
        'score'        => 'decimal:2',
        'joined_at'    => 'datetime',
        'left_at'      => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function room() {
        return $this->belongsTo(QuizRoom::class, 'room_id');
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function attempt() {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function isHost(): bool {
        return $this->role === self::ROLE_HOST;
    }
}
