<?php

namespace App\Policies;

use App\Models\EditionTeam;
use App\Models\User;

/**
 * Resource-specific authorization for Edition Team (team participation)
 * management. Mirrors EditionPolicy: a season's teams follow the editions
 * permissions (App\Support\Permissions) - `editions.view` lets a role look,
 * `editions.manage` lets it add and remove teams. The admin role holds both;
 * a scorer holds neither.
 *
 * No update() ability: EditionTeam has no editable attributes beyond its
 * own identity (edition_id, team_id) — see the Phase 3.7 report.
 */
class EditionTeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('editions.view');
    }

    public function view(User $user, EditionTeam $editionTeam): bool
    {
        return $user->hasPermission('editions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('editions.manage');
    }

    public function delete(User $user, EditionTeam $editionTeam): bool
    {
        return $user->hasPermission('editions.manage');
    }
}
