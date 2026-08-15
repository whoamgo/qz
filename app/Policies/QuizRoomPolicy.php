<?php

namespace App\Policies;

use App\Models\QuizRoom;
use App\Models\QuizRoomParticipant;
use App\Models\User;

/**
 * Room authorization. Hosts control the room lifecycle; participants may only
 * view a room they belong to and leave it. Nobody can act on another player.
 */
class QuizRoomPolicy {

    /** View the waiting room / status: must be a participant (host included). */
    public function view(User $user, QuizRoom $room): bool {
        return $room->participants()->where('user_id', $user->id)->exists();
    }

    public function start(User $user, QuizRoom $room): bool {
        return $room->isHost($user) && $room->status === QuizRoom::STATUS_WAITING;
    }

    public function cancel(User $user, QuizRoom $room): bool {
        return $room->isHost($user)
            && in_array($room->status, [QuizRoom::STATUS_WAITING, QuizRoom::STATUS_STARTED], true);
    }

    /** Leave: any current participant of the room. */
    public function leave(User $user, QuizRoom $room): bool {
        return $room->participants()
            ->where('user_id', $user->id)
            ->where('status', '!=', QuizRoomParticipant::STATUS_LEFT)
            ->exists();
    }
}
