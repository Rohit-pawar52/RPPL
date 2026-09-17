<?php

namespace App\Policies;

use App\Models\MatchPlayer;
use App\Models\User;

/**
 * Resource-specific authorization for Playing XI (MatchPlayer)
 * management.
 *
 * Deliberately different from TeamPlayerPolicy/EditionTeamPolicy: both
 * admin and scorer get full management rights here. Playing XI is part
 * of match setup that happens immediately before scoring, and the
 * scorer is the one who will need to prepare the match. This policy
 * only answers "may this role perform MatchPlayer management at all?" —
 * whether a given match may currently have its Playing XI changed is a
 * separate, per-match question answered by
 * MatchPlayerService::canModifyPlayingXI(), not here.
 */
class MatchPlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrScorer($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrScorer($user);
    }

    public function update(User $user, MatchPlayer $matchPlayer): bool
    {
        return $this->isAdminOrScorer($user);
    }

    public function delete(User $user, MatchPlayer $matchPlayer): bool
    {
        return $this->isAdminOrScorer($user);
    }

    private function isAdminOrScorer(User $user): bool
    {
        return in_array($user->role?->slug, ['admin', 'scorer'], true);
    }
}
