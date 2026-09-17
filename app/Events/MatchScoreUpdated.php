<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A pure invalidation signal — "the publicly visible live state for this
 * match has changed" — never the score itself. Deliberately carries only
 * a primitive match id, never a GameMatch/Innings/Delivery/User model:
 * the browser reacts by re-fetching the existing, already privacy-safe
 * public.matches.live-data JSON endpoint (LiveMatchService), which
 * remains the sole canonical source of live match presentation data.
 * This event must never duplicate that calculation or carry any score,
 * player, or account data itself.
 *
 * ShouldBroadcast (queued), not ShouldBroadcastNow: this app already
 * runs a queue worker in local dev (see composer.json's "dev" script),
 * and only the queued path supports the $afterCommit guarantee below.
 */
class MatchScoreUpdated implements ShouldBroadcast
{
    use Dispatchable;

    /**
     * Defers the actual broadcast until the enclosing DB transaction (if
     * any) commits — read by Illuminate\Broadcasting\BroadcastEvent, the
     * queued job Laravel dispatches for ShouldBroadcast events — so a
     * later-rolled-back scoring operation can never announce a change
     * that didn't actually happen. Defense-in-depth: callers are still
     * expected to dispatch this only after their own transaction has
     * already returned successfully.
     */
    public bool $afterCommit = true;

    public function __construct(public readonly int $matchId) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("public-match.{$this->matchId}"),
        ];
    }

    /**
     * A stable, PHP-namespace-independent contract for the browser —
     * changing the class name/namespace later must never require a
     * matching frontend change.
     */
    public function broadcastAs(): string
    {
        return 'match.score.updated';
    }

    /**
     * Exactly the match id — nothing else. No score, status, innings,
     * player, team, timestamp, or user/scorer identity.
     *
     * @return array{match_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->matchId,
        ];
    }
}
