<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QuizRoom extends Model {

    const STATUS_WAITING   = 'waiting';
    const STATUS_STARTED   = 'started';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED   = 'expired';

    const TYPE_PUBLIC  = 'public';
    const TYPE_PRIVATE = 'private';

    /** Room-code alphabet: no O/0, I/1, L to keep codes easy to read aloud. */
    const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    const CODE_PREFIX   = 'QZ';

    protected $fillable = [
        'quiz_id', 'category_id', 'host_user_id', 'room_code', 'status',
        'max_players', 'question_count', 'time_limit', 'room_type', 'settings',
        'started_at', 'ended_at', 'expires_at',
    ];

    protected $casts = [
        'settings'   => 'array',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ---------------------------------------------------------- relations --

    public function quiz() {
        return $this->belongsTo(Quiz::class);
    }

    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function host() {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function participants() {
        return $this->hasMany(QuizRoomParticipant::class, 'room_id');
    }

    /** Everyone who is still in the room (has not left). */
    public function activeParticipants() {
        return $this->participants()->where('status', '!=', QuizRoomParticipant::STATUS_LEFT);
    }

    // ------------------------------------------------------------ helpers --

    public function isHost(?User $user): bool {
        return $user && (int) $this->host_user_id === (int) $user->id;
    }

    public function currentPlayerCount(): int {
        return $this->activeParticipants()->count();
    }

    public function isFull(): bool {
        return $this->currentPlayerCount() >= $this->max_players;
    }

    public function isJoinable(): bool {
        return $this->status === self::STATUS_WAITING
            && !$this->isExpired();
    }

    public function isExpired(): bool {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * A unique, human-friendly code such as "QZ7K9P". Retries on the rare
     * collision; the DB unique index is the final guarantee.
     */
    public static function generateUniqueCode(): string {
        do {
            $code = self::CODE_PREFIX;
            for ($i = 0; $i < 4; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (self::where('room_code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string {
        return 'id';
    }
}
