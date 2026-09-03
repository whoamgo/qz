<?php

namespace App\Services\Social;

use App\Constants\SocialStatus as S;
use RuntimeException;

/**
 * The one place that decides whether a post may move from state A to state B.
 *
 * Everything that changes a post's status goes through here, so an invalid
 * transition (re-publishing an already published post, cancelling something
 * mid-upload) fails loudly instead of corrupting the record.
 */
class PostStateMachine {

    /** Allowed transitions for the master post. */
    const POST_TRANSITIONS = [
        S::DRAFT => [S::PENDING_APPROVAL, S::APPROVED, S::SCHEDULED, S::QUEUED, S::CANCELLED],
        S::PENDING_APPROVAL => [S::APPROVED, S::REJECTED, S::DRAFT, S::CANCELLED],
        S::REJECTED => [S::DRAFT, S::PENDING_APPROVAL, S::CANCELLED],
        S::APPROVED => [S::SCHEDULED, S::QUEUED, S::DRAFT, S::CANCELLED],
        S::SCHEDULED => [S::QUEUED, S::DRAFT, S::CANCELLED, S::APPROVED],
        S::QUEUED => [S::PUBLISHING, S::CANCELLED, S::FAILED],
        S::PUBLISHING => [S::PUBLISHED, S::PARTIALLY_PUBLISHED, S::FAILED, S::RETRYING],
        S::RETRYING => [S::PUBLISHING, S::PUBLISHED, S::PARTIALLY_PUBLISHED, S::FAILED, S::CANCELLED],
        // A partial publish can still be completed later by retrying the
        // platforms that failed, so it is not terminal.
        S::PARTIALLY_PUBLISHED => [S::RETRYING, S::QUEUED, S::PUBLISHING, S::PUBLISHED, S::FAILED],
        S::FAILED => [S::RETRYING, S::QUEUED, S::DRAFT, S::CANCELLED, S::SCHEDULED],
        S::PUBLISHED => [],
        S::CANCELLED => [S::DRAFT],
    ];

    /** Allowed transitions for one platform target of a post. */
    const TARGET_TRANSITIONS = [
        S::DRAFT      => [S::SCHEDULED, S::QUEUED, S::CANCELLED],
        S::SCHEDULED  => [S::QUEUED, S::DRAFT, S::CANCELLED],
        S::QUEUED     => [S::PUBLISHING, S::CANCELLED, S::FAILED],
        S::PUBLISHING => [S::PUBLISHED, S::FAILED, S::RETRYING],
        S::RETRYING   => [S::PUBLISHING, S::QUEUED, S::FAILED, S::PUBLISHED, S::CANCELLED],
        S::FAILED     => [S::RETRYING, S::QUEUED, S::CANCELLED, S::SCHEDULED],
        S::PUBLISHED  => [],
        S::CANCELLED  => [S::DRAFT, S::QUEUED],
    ];

    public static function canTransition(string $from, string $to, bool $isTarget = false): bool {
        if ($from === $to) {
            return true;
        }
        $map = $isTarget ? self::TARGET_TRANSITIONS : self::POST_TRANSITIONS;
        return in_array($to, $map[$from] ?? [], true);
    }

    /**
     * @throws RuntimeException when the move is not allowed.
     */
    public static function assert(string $from, string $to, bool $isTarget = false): void {
        if (!self::canTransition($from, $to, $isTarget)) {
            throw new RuntimeException(sprintf(
                'Invalid %s state transition: %s -> %s.',
                $isTarget ? 'platform' : 'post',
                S::statusName($from),
                S::statusName($to)
            ));
        }
    }

    /**
     * Derives the master post's state from its platform targets.
     *
     * A post is only "published" when every target that was actually attempted
     * succeeded - one failure downgrades it to partially_published so the UI can
     * never show a green tick over a broken publish.
     *
     * @param array<int,string> $targetStates
     */
    public static function rollUp(array $targetStates): string {
        $states = array_values(array_filter($targetStates, fn ($s) => $s !== S::CANCELLED));

        if (!$states) {
            return S::CANCELLED;
        }

        $published = count(array_filter($states, fn ($s) => $s === S::PUBLISHED));
        $failed    = count(array_filter($states, fn ($s) => $s === S::FAILED));
        $working   = count(array_filter($states, fn ($s) => in_array($s, [S::PUBLISHING, S::RETRYING], true)));
        $queued    = count(array_filter($states, fn ($s) => $s === S::QUEUED));
        $scheduled = count(array_filter($states, fn ($s) => $s === S::SCHEDULED));
        $total     = count($states);

        if ($published === $total)          return S::PUBLISHED;
        if ($failed === $total)             return S::FAILED;
        if ($working > 0)                   return S::PUBLISHING;
        // Some already landed while others are still waiting their turn: this is
        // in-flight, not partial, until nothing is left to do.
        if ($published > 0 && ($queued > 0 || $scheduled > 0)) return S::PUBLISHING;
        if ($published > 0 && $failed > 0)  return S::PARTIALLY_PUBLISHED;
        if ($queued > 0)                    return S::QUEUED;
        if ($scheduled > 0)                 return S::SCHEDULED;

        return S::DRAFT;
    }
}
