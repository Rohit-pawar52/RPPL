<?php

namespace App\Policies;

use App\Models\GameMatch;
use App\Models\User;

/**
 * Resource-specific authorization for Match management.
 *
 * Deliberately finer-grained than the other admin policies: every ability
 * is its own permission (see App\Support\Permissions), so viewing fixtures
 * (`matches.view`), scheduling them (`matches.manage`), running match day
 * (`matches.run`), ball-by-ball scoring (`scoring.score`), finalizing a
 * result (`matches.finalize`) and reopening one (`matches.reopen`) can each
 * be granted on its own. By default a scorer may view fixtures and run,
 * score and finalize matches but may not create/update/delete/cancel them
 * or reopen a result — only admins manage fixture scheduling.
 */
class GameMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('matches.view');
    }

    public function view(User $user, GameMatch $gameMatch): bool
    {
        return $user->hasPermission('matches.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('matches.manage');
    }

    public function update(User $user, GameMatch $gameMatch): bool
    {
        return $user->hasPermission('matches.manage');
    }

    public function delete(User $user, GameMatch $gameMatch): bool
    {
        return $user->hasPermission('matches.manage');
    }

    /**
     * Match-day setup workflow (start toss, record/correct toss, start
     * match) is deliberately separate from update() above: `matches.run`
     * lets a role drive this workflow, but that must never widen into
     * being able to edit fixture scheduling through the normal GameMatch
     * CRUD, which stays behind `matches.manage` via update(). Admin and
     * scorer hold `matches.run` by default.
     */
    public function manageMatchFlow(User $user, GameMatch $gameMatch): bool
    {
        return $user->hasPermission('matches.run');
    }

    /**
     * Innings lifecycle (start first/second innings, complete an
     * innings) is scoring/match-day workflow, same reasoning as
     * manageMatchFlow() above (`matches.run`) — tied to the parent
     * GameMatch rather than to Innings itself, since starting an innings
     * has no Innings instance yet to authorize against.
     */
    public function manageInnings(User $user, GameMatch $gameMatch): bool
    {
        return $user->hasPermission('matches.run');
    }

    /**
     * Ball-by-ball scoring (view the scoring screen, record a delivery,
     * undo the latest delivery) is its own distinct capability
     * (`scoring.score`) from manageInnings() above — kept separate rather
     * than folded into it, since a role can be allowed to manage innings
     * lifecycle without also being able to score, or vice versa. Both
     * admin and scorer have it by default.
     */
    public function score(User $user, GameMatch $gameMatch): bool
    {
        return $user->hasPermission('scoring.score');
    }

    /**
     * Deriving and locking in the match's final result (Phase 3.15) is
     * its own distinct capability (`matches.finalize`) from
     * manageInnings()/score() above — kept separate rather than reusing
     * either, since a role can be allowed to manage innings/scoring
     * without being able to close out the match's result, or vice versa.
     * Both admin and scorer have it by default, same as the other
     * match-day abilities.
     */
    public function finalizeResult(User $user, GameMatch $gameMatch): bool
    {
        return $user->hasPermission('matches.finalize');
    }

    /**
     * Cancelling a scheduled fixture is administrative, not a match-day
     * scoring action — it needs `matches.manage` (fixture scheduling),
     * unlike manageMatchFlow() above, so a scorer cannot do it by
     * default.
     */
    public function cancelMatch(User $user, GameMatch $gameMatch): bool
    {
        return $user->hasPermission('matches.manage');
    }

    /**
     * Reopening an already-finalized match (frozen S02 rule 17) has its
     * own permission (`matches.reopen`), which only admin holds by
     * default, unlike every other match-day/scoring ability above —
     * undoing a locked-in result is a higher-stakes correction than
     * recording one in the first place.
     */
    public function reopenResult(User $user, GameMatch $gameMatch): bool
    {
        return $user->hasPermission('matches.reopen');
    }

    /**
     * Abandoning a match that has already entered match-day activity is
     * closer to the other match-day workflow abilities above (both
     * admin and scorer may need to call it off, so it is `matches.run`),
     * unlike cancelMatch().
     */
    public function abandonMatch(User $user, GameMatch $gameMatch): bool
    {
        return $user->hasPermission('matches.run');
    }
}
