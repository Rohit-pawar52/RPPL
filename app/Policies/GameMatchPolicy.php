<?php

namespace App\Policies;

use App\Models\GameMatch;
use App\Models\User;

/**
 * Resource-specific authorization for Match management.
 *
 * Deliberately different from the other admin policies: scorers may
 * view fixtures (they will need to look up matches for scoring in a
 * later phase) but may not create/update/delete them — only admins
 * manage fixture scheduling.
 */
class GameMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrScorer($user);
    }

    public function view(User $user, GameMatch $gameMatch): bool
    {
        return $this->isAdminOrScorer($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, GameMatch $gameMatch): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, GameMatch $gameMatch): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Match-day setup workflow (start toss, record/correct toss, start
     * match) is deliberately separate from update() above: both admin
     * and scorer may drive this workflow, but that must never widen
     * into scorer being able to edit fixture scheduling through the
     * normal GameMatch CRUD, which stays admin-only via update().
     */
    public function manageMatchFlow(User $user, GameMatch $gameMatch): bool
    {
        return $this->isAdminOrScorer($user);
    }

    /**
     * Innings lifecycle (start first/second innings, complete an
     * innings) is scoring/match-day workflow, same reasoning as
     * manageMatchFlow() above — tied to the parent GameMatch rather
     * than to Innings itself, since starting an innings has no Innings
     * instance yet to authorize against.
     */
    public function manageInnings(User $user, GameMatch $gameMatch): bool
    {
        return $this->isAdminOrScorer($user);
    }

    /**
     * Ball-by-ball scoring (view the scoring screen, record a delivery,
     * undo the latest delivery) is its own distinct capability from
     * manageInnings() above — kept separate rather than folded into it,
     * since a future phase could plausibly want to let a role manage
     * innings lifecycle without also being able to score, or vice
     * versa. Both admin and scorer have it today.
     */
    public function score(User $user, GameMatch $gameMatch): bool
    {
        return $this->isAdminOrScorer($user);
    }

    /**
     * Deriving and locking in the match's final result (Phase 3.15) is
     * its own distinct capability from manageInnings()/score() above —
     * kept separate rather than reusing either, since a future phase
     * could plausibly want to let a role manage innings/scoring without
     * being able to close out the match's result, or vice versa. Both
     * admin and scorer have it today, same as the other match-day
     * abilities.
     */
    public function finalizeResult(User $user, GameMatch $gameMatch): bool
    {
        return $this->isAdminOrScorer($user);
    }

    /**
     * Cancelling a scheduled fixture is administrative, not a match-day
     * scoring action — kept admin-only, unlike manageMatchFlow() above.
     */
    public function cancelMatch(User $user, GameMatch $gameMatch): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Abandoning a match that has already entered match-day activity is
     * closer to the other match-day workflow abilities above (both
     * admin and scorer may need to call it off), unlike cancelMatch().
     */
    public function abandonMatch(User $user, GameMatch $gameMatch): bool
    {
        return $this->isAdminOrScorer($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }

    private function isAdminOrScorer(User $user): bool
    {
        return in_array($user->role?->slug, ['admin', 'scorer'], true);
    }
}
