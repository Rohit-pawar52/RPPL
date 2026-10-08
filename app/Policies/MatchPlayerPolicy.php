<?php

namespace App\Policies;

use App\Models\MatchPlayer;
use App\Models\User;

/**
 * Resource-specific authorization for Playing XI (MatchPlayer)
 * management.
 *
 * Deliberately different from TeamPlayerPolicy/EditionTeamPolicy: Playing
 * XI is part of match setup that happens immediately before scoring, so it
 * follows the match permissions (App\Support\Permissions) rather than the
 * teams/editions ones — `matches.view` lets a role look at it and
 * `matches.run` lets it select and change it. Admin and scorer hold both
 * by default, as the scorer is the one who will need to prepare the match.
 * This policy only answers "may this role perform MatchPlayer management
 * at all?" — whether a given match may currently have its Playing XI
 * changed is a separate, per-match question answered by
 * MatchPlayerService::canModifyPlayingXI(), not here.
 */
class MatchPlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('matches.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('matches.run');
    }

    public function update(User $user, MatchPlayer $matchPlayer): bool
    {
        return $user->hasPermission('matches.run');
    }
}
